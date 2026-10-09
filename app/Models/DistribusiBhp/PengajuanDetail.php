<?php

namespace App\Models\DistribusiBhp;

use App\Models\DistribusiBhp\Concerns\HasSumberDanaTable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(Pengajuan::class, 'bhp_pengajuan_id');
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }
}
