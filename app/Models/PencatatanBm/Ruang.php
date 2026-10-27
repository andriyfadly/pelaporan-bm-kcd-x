<?php

namespace App\Models\PencatatanBm;

use App\Models\Master\Sekolah;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ruang extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'inventaris_ruang';

    protected $fillable = ['sekolah_id', 'nama_ruang', 'kode'];

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class, 'sekolah_id');
    }
}
