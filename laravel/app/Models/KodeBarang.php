<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KodeBarang extends Model
{
    protected $connection = 'inventory';

    protected $table = 'kode_barang';

    public $timestamps = false;

    protected $fillable = [];
}
