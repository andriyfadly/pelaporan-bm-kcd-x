<?php

namespace App\Models\DistribusiBhp;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $nama
 * @property string|null $alamat
 * @property string|null $telepon
 * @property string|null $email
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Penerimaan> $penerimaan
 */
class Penyedia extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'bhp_penyedia';

    protected $fillable = [
        'nama',
        'alamat',
        'telepon',
        'email',
    ];

    /** @return HasMany<Penerimaan, $this> */
    public function penerimaan(): HasMany
    {
        return $this->hasMany(Penerimaan::class, 'penyedia_id');
    }
}
