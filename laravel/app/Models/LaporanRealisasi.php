<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class LaporanRealisasi extends Model
{
    use HasPublicId;

    protected $table = 'laporan_realisasi';

    public $timestamps = false;

    protected $fillable = ['bulan'];

    protected function casts(): array
    {
        return ['tanggal_kirim' => 'datetime'];
    }
}
