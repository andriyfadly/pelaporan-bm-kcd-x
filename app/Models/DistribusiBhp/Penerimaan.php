<?php

namespace App\Models\DistribusiBhp;

use App\Models\DistribusiBhp\Concerns\HasSumberDanaTable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $source_id
 * @property string $sekolah_id
 * @property string|null $no_nota_invoice
 * @property string|null $no_bast
 * @property Carbon|null $tanggal_bast
 * @property string|null $no_kwitansi
 * @property Carbon|null $tanggal_pembayaran
 * @property string|null $penyedia_id
 * @property Carbon|null $tanggal_titimangsa
 * @property bool $is_saldo_awal
 * @property int|null $periode_tahun
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Penyedia|null $penyedia
 * @property-read Collection<int, PenerimaanDetail> $details
 */
class Penerimaan extends Model
{
    use HasFactory, HasSumberDanaTable, HasUuids;

    protected $fillable = [
        'source_id',
        'sekolah_id',
        'no_nota_invoice',
        'no_bast',
        'tanggal_bast',
        'no_kwitansi',
        'tanggal_pembayaran',
        'penyedia_id',
        'tanggal_titimangsa',
        'is_saldo_awal',
        'periode_tahun',
    ];

    public static function suffixTabel(): string
    {
        return 'penerimaan';
    }

    protected function casts(): array
    {
        return [
            'tanggal_bast' => 'date',
            'tanggal_titimangsa' => 'date',
            'tanggal_pembayaran' => 'date',
            'is_saldo_awal' => 'boolean',
            'periode_tahun' => 'integer',
        ];
    }

    /**
     * Tanggal efektif penerimaan: awal tahun periode untuk saldo awal, tanggal
     * BAST untuk penerimaan biasa, tanggal titimangsa bila BAST kosong, dan
     * tanggal input sebagai cadangan data lama. Urutan ini SAMA dengan
     * {@see tanggalEfektif()} supaya laporan per periode dan kartu persediaan
     * tidak pernah berbeda hitungan.
     *
     * @param  Builder<Penerimaan>  $query
     * @return Builder<Penerimaan>
     */
    public function scopeEfektifSampai(Builder $query, string $tanggal): Builder
    {
        $tahun = (int) substr($tanggal, 0, 4);

        return $query->where(function (Builder $q) use ($tanggal, $tahun) {
            // Saldo awal adalah posisi AWAL tahun periodenya: jangan ikut
            // tanggal input, dan jangan muncul di periode sebelum tahunnya.
            $q->where(function (Builder $saldoAwal) use ($tahun) {
                $saldoAwal->where('is_saldo_awal', true)
                    ->whereNotNull('periode_tahun')
                    ->where('periode_tahun', '<=', $tahun);
            })->orWhere(function (Builder $biasa) use ($tanggal) {
                $biasa->where(function (Builder $bukanSaldoAwalBerperiode) {
                    // Saldo awal tanpa periode (data lama) mengikuti tanggalnya.
                    $bukanSaldoAwalBerperiode->where('is_saldo_awal', false)
                        ->orWhereNull('periode_tahun');
                })->where(function (Builder $d) use ($tanggal) {
                    $d->whereDate('tanggal_bast', '<=', $tanggal)
                        ->orWhere(fn (Builder $tanpaBast) => $tanpaBast
                            ->whereNull('tanggal_bast')
                            ->whereDate('tanggal_titimangsa', '<=', $tanggal))
                        ->orWhere(fn (Builder $tanpaTanggal) => $tanpaTanggal
                            ->whereNull('tanggal_bast')
                            ->whereNull('tanggal_titimangsa')
                            ->whereDate('created_at', '<=', $tanggal));
                });
            });
        });
    }

    /**
     * Tanggal efektif penerimaan (lihat {@see scopeEfektifSampai()}): tanggal
     * BAST, awal tahun periode untuk saldo awal, tanggal titimangsa, lalu
     * tanggal input.
     */
    public function tanggalEfektif(): ?CarbonImmutable
    {
        if ($this->is_saldo_awal && $this->periode_tahun) {
            return CarbonImmutable::create((int) $this->periode_tahun, 1, 1)->startOfDay();
        }

        foreach ([$this->tanggal_bast, $this->tanggal_titimangsa] as $tanggal) {
            if ($tanggal) {
                return CarbonImmutable::parse($tanggal)->startOfDay();
            }
        }

        return $this->created_at ? CarbonImmutable::parse($this->created_at)->startOfDay() : null;
    }

    /** @return BelongsTo<Penyedia, $this> */
    public function penyedia(): BelongsTo
    {
        return $this->belongsTo(Penyedia::class, 'penyedia_id');
    }

    /** @return HasMany<PenerimaanDetail, $this> */
    public function details(): HasMany
    {
        return $this->hasMany(PenerimaanDetail::class, 'bhp_penerimaan_id');
    }
}
