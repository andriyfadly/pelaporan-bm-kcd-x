<?php

namespace App\Models\Master;

use App\Models\DistribusiBhp\Divisi;
use App\Models\PelaporanBm\Acuan;
use App\Models\PelaporanBm\KunciLaporan;
use App\Models\PelaporanBm\Realisasi;
use App\Models\PelaporanBm\Spj;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int|null $no_urut
 * @property string $nama_sekolah
 * @property string|null $npsn
 * @property string $kota_kab
 * @property string|null $alamat
 * @property string|null $telepon
 * @property string|null $website
 * @property string|null $email
 * @property string|null $kode_sub_pengguna
 * @property string|null $kode_wilayah
 * @property int|null $id_sekolah_lama
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, User> $users
 * @property-read Collection<int, Divisi> $divisiBhp
 * @property-read Collection<int, Acuan> $acuan
 * @property-read Collection<int, Spj> $spj
 * @property-read Collection<int, Realisasi> $realisasi
 * @property-read Collection<int, KunciLaporan> $kunciLaporan
 */
class Sekolah extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'master_data_sekolah';

    protected $fillable = [
        'no_urut',
        'nama_sekolah',
        'npsn',
        'kota_kab',
        'alamat',
        'telepon',
        'website',
        'email',
        'kode_sub_pengguna',
        'kode_wilayah',
        'id_sekolah_lama',
    ];

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'sekolah_id');
    }

    /** @return HasMany<Divisi, $this> */
    public function divisiBhp(): HasMany
    {
        return $this->hasMany(Divisi::class, 'sekolah_id');
    }

    /** @return HasMany<Acuan, $this> */
    public function acuan(): HasMany
    {
        return $this->hasMany(Acuan::class, 'sekolah_id');
    }

    /** @return HasMany<Spj, $this> */
    public function spj(): HasMany
    {
        return $this->hasMany(Spj::class, 'sekolah_id');
    }

    /** @return HasMany<Realisasi, $this> */
    public function realisasi(): HasMany
    {
        return $this->hasMany(Realisasi::class, 'sekolah_id');
    }

    /** @return HasMany<KunciLaporan, $this> */
    public function kunciLaporan(): HasMany
    {
        return $this->hasMany(KunciLaporan::class, 'sekolah_id');
    }
}
