<?php

namespace App\Models\PelaporanBm;

use App\Models\Master\Sekolah;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Acuan extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'pelaporan_bm_acuan';

    protected $fillable = [
        'sekolah_id',
        'satuan_pendidikan',
        'npsn',
        'tanggal',
        'kodering',
        'bku',
        'uraian',
        'nominal',
        'bulan',
        'id_acuan_lama',
    ];

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class, 'sekolah_id');
    }

    public function spj(): HasMany
    {
        return $this->hasMany(Spj::class, 'acuan_id');
    }

    public function realisasi(): HasMany
    {
        return $this->hasMany(Realisasi::class, 'acuan_id');
    }
}
