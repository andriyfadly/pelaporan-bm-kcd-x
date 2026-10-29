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
 * @property string|null $barang_id
 * @property int $jumlah_diminta
 * @property int|null $jumlah_disetujui
 * @property int|null $harga_satuan
 * @property string|null $keperluan
 * @property string|null $keterangan
 * @property string $status_reservasi
 * @property int $jumlah_direservasi
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Pengajuan|null $pengajuan
 * @property-read Barang|null $barang
 */
class PengajuanDetail extends Model
{
    use HasFactory, HasSumberDanaTable, HasUuids;

    protected $fillable = [
        'bhp_pengajuan_id',
        'barang_id',
        'jumlah_diminta',
        'jumlah_disetujui',
        'harga_satuan',
        'keperluan',
        'keterangan',
        'status_reservasi',
        'jumlah_direservasi',
    ];

    public static function suffixTabel(): string
    {
        return 'pengajuan_detail';
    }

    protected function casts(): array
    {
        return [
            'jumlah_diminta' => 'integer',
            'jumlah_disetujui' => 'integer',
            'harga_satuan' => 'integer',
            'jumlah_direservasi' => 'integer',
        ];
    }

    /** @return BelongsTo<Pengajuan, $this> */
    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(Pengajuan::class, 'bhp_pengajuan_id');
    }

    /** @return BelongsTo<Barang, $this> */
    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }
}
