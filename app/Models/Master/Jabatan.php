<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Jabatan sekolah (master, bukan enum): kepala sekolah, wakasek, bendahara,
 * operator, dan jabatan internal lain. Kode dipakai sebagai kunci stabil yang
 * tidak berubah walau nama jabatan diperbaiki.
 *
 * @property string $id
 * @property string $sekolah_id
 * @property string $kode
 * @property string $nama
 * @property string|null $kategori
 * @property bool $aktif
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read bool $wajib
 * @property-read Sekolah|null $sekolah
 * @property-read Collection<int, PenugasanPegawai> $penugasan
 */
class Jabatan extends Model
{
    use HasUuids, SoftDeletes;

    /** Kode jabatan yang wajib ada sebagai penandatangan dokumen. */
    public const KODE_PENANDATANGAN = ['kepala_sekolah', 'wakasek_sarpras', 'pembantu_pengurus_barang'];

    public const LABEL_PENANDATANGAN = [
        'kepala_sekolah' => 'Kepala Sekolah',
        'wakasek_sarpras' => 'Wakasek Sarpras',
        'pembantu_pengurus_barang' => 'Pembantu Pengurus Barang',
    ];

    public const JENIS_PENUGASAN = ['definitif', 'plt', 'plh'];

    public const KATEGORI = ['pimpinan', 'wakil_pimpinan', 'pengelola', 'lainnya'];

    protected $table = 'master_data_jabatan';

    protected $fillable = ['sekolah_id', 'kode', 'nama', 'kategori', 'aktif'];

    protected $attributes = ['aktif' => true];

    protected $appends = ['wajib'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    /** @return BelongsTo<Sekolah, $this> */
    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class, 'sekolah_id');
    }

    /** @return HasMany<PenugasanPegawai, $this> */
    public function penugasan(): HasMany
    {
        return $this->hasMany(PenugasanPegawai::class, 'jabatan_id');
    }

    /**
     * Jabatan aktif satu sekolah; dipakai untuk membangun daftar pilihan.
     *
     * @param  Builder<Jabatan>  $query
     * @return Builder<Jabatan>
     */
    public function scopeTersedia(Builder $query, ?string $sekolahId = null): Builder
    {
        return $query->when($sekolahId, fn (Builder $q) => $q->where('sekolah_id', $sekolahId))
            ->where('aktif', true)
            ->orderBy('nama');
    }

    /** Jabatan penandatangan dokumen: wajib ada, tidak boleh dihapus/diubah namanya. */
    public function isWajib(): bool
    {
        return in_array($this->kode, self::KODE_PENANDATANGAN, true);
    }

    public function getWajibAttribute(): bool
    {
        return $this->isWajib();
    }

    /** Buat tiga jabatan wajib bila belum ada; aman dijalankan berulang. */
    public static function pastikanWajib(string $sekolahId): void
    {
        foreach (self::KODE_PENANDATANGAN as $kode) {
            self::withTrashed()->firstOrCreate(
                ['sekolah_id' => $sekolahId, 'kode' => $kode],
                ['nama' => self::LABEL_PENANDATANGAN[$kode]],
            )->restore();
        }
    }
}
