<?php

namespace App\Models\Master;

use App\Models\PelaporanBm\Acuan;
use App\Models\PelaporanBm\KunciLaporan;
use App\Models\PelaporanBm\Realisasi;
use App\Models\PelaporanBm\Spj;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sekolah extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'master_data_sekolah';

    protected $fillable = [
        'no_urut',
        'nama_sekolah',
        'kota_kab',
        'kode_sub_pengguna',
        'kode_wilayah',
        'id_sekolah_lama',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'sekolah_id');
    }

    public function acuan(): HasMany
    {
        return $this->hasMany(Acuan::class, 'sekolah_id');
    }

    public function spj(): HasMany
    {
        return $this->hasMany(Spj::class, 'sekolah_id');
    }

    public function realisasi(): HasMany
    {
        return $this->hasMany(Realisasi::class, 'sekolah_id');
    }

    public function kunciLaporan(): HasMany
    {
        return $this->hasMany(KunciLaporan::class, 'sekolah_id');
    }
}
