<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Master pejabat sekolah (generic, lintas domain): sumber penandatangan
 * dokumen. Riwayat masa jabatan disimpan; snapshot per dokumen tersimpan di
 * payload pemanggil (mis. bhp_dokumen.payload.penandatangan).
 */
class PejabatSekolah extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public const JABATAN = ['kepala_sekolah', 'wakasek_sarpras', 'pembantu_pengurus_barang'];

    public const JENIS_JABATAN = ['definitif', 'plt', 'plh'];

    public const LABEL_JABATAN = [
        'kepala_sekolah' => 'Kepala Sekolah',
        'wakasek_sarpras' => 'Wakasek Sarana Prasarana',
        'pembantu_pengurus_barang' => 'Pembantu Pengurus Barang',
    ];

    protected $table = 'pejabat_sekolah';

    protected $fillable = [
        'sekolah_id',
        'jabatan',
        'jenis_jabatan',
        'nama',
        'pangkat_golongan',
        'nip',
        'mulai_menjabat',
        'selesai_menjabat',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'mulai_menjabat' => 'date',
            'selesai_menjabat' => 'date',
        ];
    }

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class, 'sekolah_id');
    }

    /** Label jabatan siap cetak, termasuk awalan Plt./Plh. */
    public function labelJabatan(): string
    {
        $label = self::LABEL_JABATAN[$this->jabatan] ?? $this->jabatan;

        return match ($this->jenis_jabatan) {
            'plt' => 'Plt. '.$label,
            'plh' => 'Plh. '.$label,
            default => $label,
        };
    }

    /** Pejabat aktif (masih menjabat) untuk sekolah + jabatan tertentu. */
    public function scopeAktif($query, string $sekolahId, string $jabatan)
    {
        return $query->where('sekolah_id', $sekolahId)
            ->where('jabatan', $jabatan)
            ->whereNull('selesai_menjabat')
            ->orderByDesc('mulai_menjabat');
    }

    /**
     * Pejabat yang menjabat efektif pada sebuah tanggal (untuk approval
     * backdate): mulai <= tanggal dan (selesai null atau selesai >= tanggal).
     */
    public function scopeBerlakuPada($query, string $sekolahId, string $jabatan, string $tanggal)
    {
        return $query->where('sekolah_id', $sekolahId)
            ->where('jabatan', $jabatan)
            ->whereDate('mulai_menjabat', '<=', $tanggal)
            ->where(fn ($q) => $q->whereNull('selesai_menjabat')->orWhereDate('selesai_menjabat', '>=', $tanggal))
            ->orderByDesc('mulai_menjabat');
    }

    /** Representasi snapshot untuk payload dokumen. */
    public function toSnapshot(): array
    {
        return [
            'official_id' => $this->id,
            'jabatan' => $this->labelJabatan(),
            'nama' => $this->nama,
            'pangkat_golongan' => $this->pangkat_golongan,
            'nip' => $this->nip,
            'mulai_menjabat' => $this->mulai_menjabat?->toDateString(),
            'selesai_menjabat' => $this->selesai_menjabat?->toDateString(),
        ];
    }
}
