<?php

namespace App\Models\DistribusiBhp;

use App\Models\DistribusiBhp\Concerns\HasSumberDanaTable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $kode
 * @property string $nama
 * @property string|null $kode_barang
 * @property string|null $uraian_kode_barang
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
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
}
