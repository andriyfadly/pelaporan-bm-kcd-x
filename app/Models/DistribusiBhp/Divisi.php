<?php

namespace App\Models\DistribusiBhp;

use App\Models\Master\Sekolah;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Divisi extends Model
{
    use HasUuids;

    protected $table = 'bhp_divisi';

    protected $fillable = [
        'sekolah_id',
        'nama',
    ];

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class, 'sekolah_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'divisi_id');
    }

    public function pengajuan(): HasMany
    {
        return $this->hasMany(Pengajuan::class, 'divisi_id');
    }
}
