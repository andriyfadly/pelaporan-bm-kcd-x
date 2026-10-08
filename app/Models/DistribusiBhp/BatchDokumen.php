<?php

namespace App\Models\DistribusiBhp;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatchDokumen extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'bhp_batch_dokumen';

    protected $fillable = [
        'jenis',
        'sumber_dana',
        'bhp_pengajuan_id',
        'status',
        'total',
        'berhasil',
        'gagal',
        'files',
        'error_message',
        'path',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'files' => 'array',
            'total' => 'integer',
            'berhasil' => 'integer',
            'gagal' => 'integer',
        ];
    }

    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(Pengajuan::class, 'bhp_pengajuan_id');
    }
}
