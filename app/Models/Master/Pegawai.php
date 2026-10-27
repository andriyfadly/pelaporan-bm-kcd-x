<?php

namespace App\Models\Master;

use App\Models\PencatatanBm\InventarisUnit;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Master pegawai sekolah: identitas orang saja. Jabatan tidak disimpan di
 * sini karena berganti sepanjang waktu — lihat PenugasanPegawai.
 */
class Pegawai extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'master_data_pegawai';

    protected $fillable = ['sekolah_id', 'nama_lengkap', 'nip', 'pangkat_golongan', 'aktif'];

    protected $attributes = ['aktif' => true];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class, 'sekolah_id');
    }

    public function penugasan(): HasMany
    {
        return $this->hasMany(PenugasanPegawai::class, 'pegawai_id');
    }

    public function unitAktif(): HasMany
    {
        return $this->hasMany(InventarisUnit::class, 'pegawai_id')
            ->where('status', InventarisUnit::STATUS_AKTIF);
    }
}
