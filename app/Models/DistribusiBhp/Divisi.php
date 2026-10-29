<?php

namespace App\Models\DistribusiBhp;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $sekolah_id
 * @property string $nama
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, User> $users
 * @property-read Collection<int, Pengajuan> $pengajuan
 */
class Divisi extends Model
{
    use HasUuids;

    protected $table = 'bhp_divisi';

    protected $fillable = [
        'sekolah_id',
        'nama',
    ];

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'divisi_id');
    }

    /** @return HasMany<Pengajuan, $this> */
    public function pengajuan(): HasMany
    {
        return $this->hasMany(Pengajuan::class, 'divisi_id');
    }
}
