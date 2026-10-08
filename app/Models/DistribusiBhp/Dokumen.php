<?php

namespace App\Models\DistribusiBhp;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dokumen extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'bhp_dokumen';

    protected $fillable = [
        'bhp_pengajuan_id',
        'jenis',
        'nomor',
        'tanggal_titimangsa',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_titimangsa' => 'date',
            'payload' => 'array',
        ];
    }

    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(Pengajuan::class, 'bhp_pengajuan_id');
    }
}
