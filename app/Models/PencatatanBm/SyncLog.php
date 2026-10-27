<?php

namespace App\Models\PencatatanBm;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Observability sinkronisasi, bukan sumber kebenaran bisnis.
 */
class SyncLog extends Model
{
    use HasUuids;

    protected $table = 'inventaris_sync_logs';

    protected $fillable = [
        'sekolah_id',
        'realisasi_id',
        'status',
        'dibuat',
        'tetap',
        'surplus',
        'perlu_review',
        'error',
        'actor_id',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'dibuat' => 'integer',
            'tetap' => 'integer',
            'surplus' => 'integer',
            'perlu_review' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
