<?php

namespace App\Models\DistribusiBhp;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HargaBarang extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'bhp_harga_barang';

    protected $fillable = [
        'barang_id',
        'harga_satuan',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'harga_satuan' => 'integer',
        ];
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }
}
