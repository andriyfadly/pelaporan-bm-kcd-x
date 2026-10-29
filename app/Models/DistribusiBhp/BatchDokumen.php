<?php

namespace App\Models\DistribusiBhp;

use App\Models\DistribusiBhp\Concerns\HasSumberDanaTable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $jenis
 * @property string|null $bhp_pengajuan_id
 * @property string $status
 * @property int $total
 * @property int $berhasil
 * @property int $gagal
 * @property array<string, mixed>|null $files
 * @property string|null $error_message
 * @property string|null $path
 * @property string|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Pengajuan|null $pengajuan
 */
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

    /** @return BelongsTo<Pengajuan, $this> */
    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(Pengajuan::class, 'bhp_pengajuan_id');
    }
}
