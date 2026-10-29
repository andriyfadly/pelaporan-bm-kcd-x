<?php

namespace App\Models\PencatatanBm;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Observability sinkronisasi, bukan sumber kebenaran bisnis.
 *
 * @property string $id
 * @property string|null $sekolah_id
 * @property string|null $realisasi_id
 * @property string $status
 * @property int $dibuat
 * @property int $tetap
 * @property int $surplus
 * @property int $perlu_review
 * @property string|null $error
 * @property string|null $actor_id
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
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
