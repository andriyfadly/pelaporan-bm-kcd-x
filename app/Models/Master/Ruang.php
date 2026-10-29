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
 * Master ruang sekolah: tempat fisik penempatan unit inventaris. Soft delete
 * dipakai supaya histori mutasi tetap bisa menunjuk ruang yang sudah tidak
 * dipakai.
 *
 * @property string $id
 * @property string $sekolah_id
 * @property string $nama_ruang
 * @property string|null $kode
 * @property bool $aktif
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Sekolah|null $sekolah
 * @property-read Collection<int, InventarisUnit> $unitAktif
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

    /** @return BelongsTo<Sekolah, $this> */
    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class, 'sekolah_id');
    }

    /** @return HasMany<InventarisUnit, $this> */
    public function unitAktif(): HasMany
    {
        return $this->hasMany(InventarisUnit::class, 'ruang_id')
            ->where('status', InventarisUnit::STATUS_AKTIF);
    }
}
