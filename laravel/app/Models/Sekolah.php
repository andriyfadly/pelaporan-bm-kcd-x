<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class Sekolah extends Model
{
    use HasPublicId;

    protected $table = 'kode_sekolah';

    public $timestamps = false;

    protected $fillable = [
        'no_urut',
        'nama_sekolah',
        'kota_kab',
        'kode_sub_pengguna',
        'kode_wilayah',
    ];
}
