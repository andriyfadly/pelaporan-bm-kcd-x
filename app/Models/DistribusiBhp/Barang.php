<?php

namespace App\Models\DistribusiBhp;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Barang extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'bhp_barang';

    protected $fillable = [
        'kode_barang',
        'nama_barang',
        'spesifikasi_barang_nusp',
        'satuan',
        'kode_rekening_id',
        'sumber_dana',
    ];

    public function kodeRekening(): BelongsTo
    {
        return $this->belongsTo(KodeRekening::class, 'kode_rekening_id');
    }

    public function harga(): HasMany
    {
        return $this->hasMany(HargaBarang::class, 'barang_id');
    }
}
