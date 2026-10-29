<?php

namespace App\Models\PencatatanBm;

use App\Models\Master\Pegawai;
use App\Models\Master\Ruang;
use App\Models\Master\Sekolah;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Histori perpindahan/koreksi unit. Tidak pernah dihapus; perubahan state
 * unit saat ini selalu meninggalkan satu baris mutasi.
 */
class Mutasi extends Model
{
    use HasUuids;

    public const JENIS = ['penyaluran', 'mutasi', 'pengembalian', 'koreksi'];

    protected $table = 'inventaris_mutasi';

    protected $fillable = [
        'sekolah_id',
        'inventaris_unit_id',
        'jenis',
        'asal_ruang_id',
        'asal_pegawai_id',
        'asal_kondisi',
        'tujuan_ruang_id',
        'tujuan_pegawai_id',
        'tujuan_kondisi',
        'tanggal',
        'keterangan',
        'user_id',
    ];

    protected function casts(): array
    {
        return ['tanggal' => 'date'];
    }

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class, 'sekolah_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(InventarisUnit::class, 'inventaris_unit_id');
    }

    public function asalRuang(): BelongsTo
    {
        return $this->belongsTo(Ruang::class, 'asal_ruang_id');
    }

    public function tujuanRuang(): BelongsTo
    {
        return $this->belongsTo(Ruang::class, 'tujuan_ruang_id');
    }

    public function asalPegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'asal_pegawai_id');
    }

    public function tujuanPegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'tujuan_pegawai_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
