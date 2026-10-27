<?php

namespace App\Models\DistribusiBhp;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function penerimaan(): HasMany
    {
        return $this->hasMany(Penerimaan::class, 'penyedia_id');
    }
}
