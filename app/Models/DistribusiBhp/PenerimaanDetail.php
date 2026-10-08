<?php

namespace App\Models\DistribusiBhp;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PenerimaanDetail extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'bhp_penerimaan_detail';

    protected $fillable = [
        'bhp_penerimaan_id',
        'barang_id',
        'harga_barang_id',
        'jumlah',
        'harga_satuan',
        'nilai_total',
        'merek_type',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'jumlah' => 'integer',
            'harga_satuan' => 'integer',
            'nilai_total' => 'integer',
        ];
    }

    public function penerimaan(): BelongsTo
    {
        return $this->belongsTo(Penerimaan::class, 'bhp_penerimaan_id');
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }
}
