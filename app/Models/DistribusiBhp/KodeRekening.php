<?php

namespace App\Models\DistribusiBhp;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KodeRekening extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'bhp_kode_rekening';

    protected $fillable = [
        'kode',
        'nama',
        'kode_barang',
        'uraian_kode_barang',
        'sumber_dana',
    ];

    public function barang(): HasMany
    {
        return $this->hasMany(Barang::class, 'kode_rekening_id');
    }

    /**
     * Hanya menerima sumber dana BHP yang didukung.
     */
    public static function sumberDanaValid(): array
    {
        return ['BOS', 'BOPD'];
    }
}
