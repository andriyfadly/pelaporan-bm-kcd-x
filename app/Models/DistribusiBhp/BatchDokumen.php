<?php

namespace App\Models\DistribusiBhp;

use App\Models\DistribusiBhp\Concerns\HasSumberDanaTable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatchDokumen extends Model
{
    use HasFactory, HasSumberDanaTable, HasUuids;

    protected $fillable = [
        'jenis',
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

    public static function suffixTabel(): string
    {
        return 'batch_dokumen';
    }

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
