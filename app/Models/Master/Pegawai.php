<?php

namespace App\Models\Master;

use App\Models\PencatatanBm\InventarisUnit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Master pegawai sekolah: identitas orang saja. Jabatan tidak disimpan di
 * sini karena berganti sepanjang waktu — lihat PenugasanPegawai.
 *
 * @property string $id
 * @property string $sekolah_id
 * @property string $nama_lengkap
 * @property string|null $nip
 * @property string|null $pangkat_golongan
 * @property bool $aktif
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Sekolah|null $sekolah
 * @property-read Collection<int, PenugasanPegawai> $penugasan
 * @property-read Collection<int, InventarisUnit> $unitAktif
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

    /** @return BelongsTo<Sekolah, $this> */
    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class, 'sekolah_id');
    }

    /** @return HasMany<PenugasanPegawai, $this> */
    public function penugasan(): HasMany
    {
        return $this->hasMany(PenugasanPegawai::class, 'pegawai_id');
    }

    /** @return HasMany<InventarisUnit, $this> */
    public function unitAktif(): HasMany
    {
        return $this->hasMany(InventarisUnit::class, 'pegawai_id')
            ->where('status', InventarisUnit::STATUS_AKTIF);
    }
}
