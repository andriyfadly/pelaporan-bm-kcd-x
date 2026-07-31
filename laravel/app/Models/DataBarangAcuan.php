<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class DataBarangAcuan extends Model
{
    use HasPublicId;

    protected $table = 'data_barang_acuan';

    public $timestamps = false;

    protected $fillable = [
        'satuan_pendidikan',
        'npsn',
        'tanggal',
        'kodering',
        'bku',
        'uraian',
        'nominal',
        'bulan',
    ];

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'nominal' => 'decimal:2'];
    }
}
