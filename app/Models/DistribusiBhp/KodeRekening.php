<?php

namespace App\Models\DistribusiBhp;

use App\Models\DistribusiBhp\Concerns\HasSumberDanaTable;
use App\Support\DistribusiBhp\SumberDana;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KodeRekening extends Model
{
    use HasFactory, HasSumberDanaTable, HasUuids;

    protected $fillable = [
        'kode',
        'nama',
        'kode_barang',
        'uraian_kode_barang',
    ];

    public static function suffixTabel(): string
    {
        return 'kode_rekening';
    }

    public function barang(): HasMany
    {
        return $this->hasMany(Barang::class, 'kode_rekening_id');
    }

    /**
     * Hanya menerima sumber dana BHP yang didukung.
     */
    public static function sumberDanaValid(): array
    {
        return SumberDana::SEMUA;
    }
}
