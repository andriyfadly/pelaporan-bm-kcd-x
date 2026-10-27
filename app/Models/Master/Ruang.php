<?php

namespace App\Models\Master;

use App\Models\PencatatanBm\InventarisUnit;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Master ruang sekolah: tempat fisik penempatan unit inventaris. Soft delete
 * dipakai supaya histori mutasi tetap bisa menunjuk ruang yang sudah tidak
 * dipakai.
 */
class Ruang extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'master_data_ruang';

    protected $fillable = ['sekolah_id', 'nama_ruang', 'kode', 'aktif'];

    protected $attributes = ['aktif' => true];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class, 'sekolah_id');
    }

    public function unitAktif(): HasMany
    {
        return $this->hasMany(InventarisUnit::class, 'ruang_id')
            ->where('status', InventarisUnit::STATUS_AKTIF);
    }
}
