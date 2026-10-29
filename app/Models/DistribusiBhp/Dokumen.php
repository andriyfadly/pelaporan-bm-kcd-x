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
 * @property string $bhp_pengajuan_id
 * @property string $jenis
 * @property string $nomor
 * @property Carbon|null $tanggal_titimangsa
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Pengajuan|null $pengajuan
 */
class Dokumen extends Model
{
    use HasFactory, HasSumberDanaTable, HasUuids;

    protected $fillable = [
        'bhp_pengajuan_id',
        'jenis',
        'nomor',
        'tanggal_titimangsa',
        'payload',
    ];

    public static function suffixTabel(): string
    {
        return 'dokumen';
    }

    protected function casts(): array
    {
        return [
            'tanggal_titimangsa' => 'date',
            'payload' => 'array',
        ];
    }

    /** @return BelongsTo<Pengajuan, $this> */
    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(Pengajuan::class, 'bhp_pengajuan_id');
    }
}
