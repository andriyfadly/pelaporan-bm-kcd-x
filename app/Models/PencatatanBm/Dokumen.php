<?php

namespace App\Models\PencatatanBm;

use App\Models\Master\Sekolah;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Salinan dokumen cetak. `payload` menyimpan snapshot agar cetak ulang tetap
 * sama setelah data master berubah.
 */
class Dokumen extends Model
{
    use HasUuids;

    protected $table = 'inventaris_dokumen';

    protected $fillable = [
        'sekolah_id',
        'jenis',
        'nomor',
        'tanggal',
        'inventaris_unit_id',
        'inventaris_pegawai_id',
        'inventaris_ruang_id',
        'payload',
        'file_path',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'payload' => 'array',
        ];
    }

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class, 'sekolah_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
