<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Riwayat penugasan pegawai pada sebuah jabatan. Satu baris = satu periode.
 * Pegawai lama tidak pernah dikosongkan saat mutasi: periodenya ditutup
 * (selesai_menjabat) dan baris baru dibuat untuk penggantinya.
 *
 * @property string $id
 * @property string $sekolah_id
 * @property string $pegawai_id
 * @property string $jabatan_id
 * @property string $jenis_penugasan
 * @property Carbon $mulai_menjabat
 * @property Carbon|null $selesai_menjabat
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Sekolah|null $sekolah
 * @property-read Pegawai|null $pegawai
 * @property-read Jabatan|null $jabatan
 */
class PenugasanPegawai extends Model
{
    use HasUuids;

    public const LABEL_JENIS = [
        'definitif' => '',
        'plt' => 'Plt. ',
        'plh' => 'Plh. ',
    ];

    /** Urutan prioritas saat data lama membuat satu tanggal punya >1 kandidat. */
    private const PRIORITAS_JENIS = ['definitif' => 0, 'plt' => 1, 'plh' => 2];

    protected $table = 'master_data_penugasan_pegawai';

    protected $fillable = [
        'sekolah_id',
        'pegawai_id',
        'jabatan_id',
        'jenis_penugasan',
        'mulai_menjabat',
        'selesai_menjabat',
        'created_by',
        'updated_by',
    ];

    protected $attributes = ['jenis_penugasan' => 'definitif'];

    protected function casts(): array
    {
        return [
            'mulai_menjabat' => 'date',
            'selesai_menjabat' => 'date',
        ];
    }

    /** @return BelongsTo<Sekolah, $this> */
    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class, 'sekolah_id');
    }

    /** @return BelongsTo<Pegawai, $this> */
    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    /** @return BelongsTo<Jabatan, $this> */
    public function jabatan(): BelongsTo
    {
        return $this->belongsTo(Jabatan::class, 'jabatan_id');
    }

    /**
     * Penugasan yang masih berjalan pada jabatan tertentu.
     *
     * @param  Builder<PenugasanPegawai>  $query
     * @return Builder<PenugasanPegawai>
     */
    public function scopeAktif(Builder $query, string $sekolahId, string $jabatanId): Builder
    {
        return $query->where('sekolah_id', $sekolahId)
            ->where('jabatan_id', $jabatanId)
            ->whereNull('selesai_menjabat')
            ->orderByDesc('mulai_menjabat');
    }

    /**
     * Kandidat pejabat yang menjabat pada sebuah tanggal (dokumen lampau).
     * Prioritas definitif > plt > plh, lalu mulai terbaru.
     *
     * @param  Builder<PenugasanPegawai>  $query
     * @return Builder<PenugasanPegawai>
     */
    public function scopeBerlakuPada(Builder $query, string $sekolahId, string $jabatanId, string $tanggal): Builder
    {
        return $query->where('sekolah_id', $sekolahId)
            ->where('jabatan_id', $jabatanId)
            ->whereDate('mulai_menjabat', '<=', $tanggal)
            ->where(fn (Builder $q) => $q
                ->whereNull('selesai_menjabat')
                ->orWhereDate('selesai_menjabat', '>=', $tanggal))
            ->orderByRaw("CASE jenis_penugasan WHEN 'definitif' THEN 0 WHEN 'plt' THEN 1 ELSE 2 END")
            ->orderByDesc('mulai_menjabat');
    }

    /** Label jabatan siap cetak, termasuk awalan Plt./Plh. */
    public function labelJabatan(): string
    {
        $nama = $this->jabatan->nama ?? '';

        return (self::LABEL_JENIS[$this->jenis_penugasan] ?? '').$nama;
    }

    /**
     * Snapshot yang disimpan ke payload dokumen. Dibuat sekali saat dokumen
     * dibuat; perubahan master setelahnya tidak boleh mengubah isinya.
     *
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        return [
            'pegawai_id' => $this->pegawai_id,
            'nama' => $this->pegawai?->nama_lengkap,
            'nip' => $this->pegawai?->nip,
            'pangkat_golongan' => $this->pegawai?->pangkat_golongan,
            'jabatan_id' => $this->jabatan_id,
            'jabatan' => $this->labelJabatan(),
            'jenis_penugasan' => $this->jenis_penugasan,
            'mulai_menjabat' => $this->mulai_menjabat->toDateString(),
            'selesai_menjabat' => $this->selesai_menjabat?->toDateString(),
        ];
    }

    /** Prioritas jenis untuk pemilihan kandidat di memori. */
    public static function prioritasJenis(string $jenis): int
    {
        return self::PRIORITAS_JENIS[$jenis] ?? 99;
    }
}
