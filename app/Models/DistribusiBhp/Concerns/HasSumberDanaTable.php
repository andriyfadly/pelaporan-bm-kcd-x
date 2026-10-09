<?php

namespace App\Models\DistribusiBhp\Concerns;

use App\Support\DistribusiBhp\SumberDana;
use Illuminate\Database\Eloquent\Builder;

/**
 * Model dengan tabel fisik terpisah per sumber dana (`bhp_bos_*`/`bhp_bopd_*`).
 *
 * Tabel di-resolve runtime dari {@see SumberDana::aktif()}. Untuk query yang
 * menyebut sumber dana eksplisit, gunakan scope {@see scopeSumberDana()} —
 * scope itu sekaligus menetapkan tabel yang benar untuk request berjalan.
 *
 * Catatan: `sumber_dana` TIDAK ada sebagai kolom — nilainya implisit dari nama
 * tabel. Model ini mengekspos accessor {@see getSumberDanaAttribute()} sehingga
 * `$model->sumber_dana` tetap bisa dibaca (untuk serialisasi/UI).
 */
trait HasSumberDanaTable
{
    /** Suffix logis tabel, mis. 'penerimaan'. Wajib di-override model. */
    abstract public static function suffixTabel(): string;

    /** Sumber dana instance ini; null = ikuti sumber dana aktif. */
    protected ?string $sumberDanaInstance = null;

    public static function tabelUntuk(?string $sumberDana = null): string
    {
        return SumberDana::tabel(static::suffixTabel(), $sumberDana);
    }

    public function getTable(): string
    {
        return SumberDana::tabel(static::suffixTabel(), $this->sumberDanaInstance);
    }

    /**
     * `sumber_dana` bukan kolom, tetapi harus "fillable" agar dapat diberikan
     * saat create/fill dan diterjemahkan menjadi tabel oleh setAttribute().
     */
    public function getFillable(): array
    {
        return array_values(array_unique([...parent::getFillable(), 'sumber_dana']));
    }

    public function getAppends(): array
    {
        return array_values(array_unique([...parent::getAppends(), 'sumber_dana']));
    }

    /**
     * Kaitkan instance ini ke sumber dana tertentu (memindahkan tabelnya).
     *
     * Sekaligus menjadikannya sumber dana aktif request agar relasi/model yang
     * dibuat setelahnya (mis. `$penerimaan->details()->create([...])`) menunjuk
     * tabel yang sama.
     */
    public function untukSumberDana(?string $sumberDana): static
    {
        $this->sumberDanaInstance = $sumberDana === null
            ? null
            : SumberDana::normalisasi($sumberDana);

        if ($this->sumberDanaInstance !== null) {
            SumberDana::setAktif($this->sumberDanaInstance);
        }

        return $this;
    }

    /**
     * `sumber_dana` bukan kolom fisik — ia hanya menetapkan tabel instance ini.
     * Menerimanya di fill/create membuat kode pemanggil dapat menyatakan sumber
     * dana secara eksplisit (mis. `Barang::create(['sumber_dana' => 'BOPD'])`).
     */
    public function setAttribute($key, $value)
    {
        if ($key === 'sumber_dana') {
            if ($value !== null && $value !== '') {
                $this->untukSumberDana((string) $value);
            }

            return $this;
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * Scope: query pada tabel fisik sumber dana tertentu, dan jadikan sumber
     * dana itu sebagai konteks aktif pada request berjalan.
     */
    public function scopeSumberDana(Builder $query, string $sumberDana): Builder
    {
        SumberDana::setAktif($sumberDana);

        return $query->from(static::tabelUntuk($sumberDana));
    }

    /** Sumber dana dibaca dari nama tabel (implisit). */
    public function getSumberDanaAttribute(): ?string
    {
        return str_starts_with($this->getTable(), 'bhp_bopd_')
            ? SumberDana::BOPD
            : SumberDana::BOS;
    }

    /**
     * Route model binding lintas tabel sumber dana: cari di tabel BOS lalu BOPD
     * (UUID unik, jadi tidak ambigu), dan set sumber dana aktif + instance agar
     * relasi berikutnya menunjuk tabel yang benar.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $field ??= $this->getRouteKeyName();

        foreach (SumberDana::SEMUA as $dana) {
            $model = $this->newQuery()->from(static::tabelUntuk($dana))
                ->where($field, $value)
                ->first();

            if ($model !== null) {
                SumberDana::setAktif($dana);
                $model->untukSumberDana($dana);

                return $model;
            }
        }

        return null;
    }
}
