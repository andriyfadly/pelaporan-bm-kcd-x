<?php

namespace App\Models\PencatatanBm;

use App\Models\Master\Pegawai;
use App\Models\Master\Ruang;
use App\Models\Master\Sekolah;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Histori perpindahan/koreksi unit. Tidak pernah dihapus; perubahan state
 * unit saat ini selalu meninggalkan satu baris mutasi.
 *
 * @property string $id
 * @property string $sekolah_id
 * @property string $inventaris_unit_id
 * @property string $jenis
 * @property string|null $asal_ruang_id
 * @property string|null $asal_pegawai_id
 * @property string|null $asal_kondisi
 * @property string|null $tujuan_ruang_id
 * @property string|null $tujuan_pegawai_id
 * @property string|null $tujuan_kondisi
 * @property Carbon $tanggal
 * @property string|null $keterangan
 * @property string|null $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Sekolah|null $sekolah
 * @property-read InventarisUnit|null $unit
 * @property-read Ruang|null $asalRuang
 * @property-read Ruang|null $tujuanRuang
 * @property-read Pegawai|null $asalPegawai
 * @property-read Pegawai|null $tujuanPegawai
 * @property-read User|null $user
 */
class Mutasi extends Model
{
    use HasUuids;

    public const JENIS = ['penyaluran', 'mutasi', 'pengembalian', 'koreksi'];

    protected $table = 'inventaris_mutasi';

    protected $fillable = [
        'sekolah_id',
        'inventaris_unit_id',
        'jenis',
        'asal_ruang_id',
        'asal_pegawai_id',
        'asal_kondisi',
        'tujuan_ruang_id',
        'tujuan_pegawai_id',
        'tujuan_kondisi',
        'tanggal',
        'keterangan',
        'user_id',
    ];

    protected function casts(): array
    {
        return ['tanggal' => 'date'];
    }

    /** @return BelongsTo<Sekolah, $this> */
    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class, 'sekolah_id');
    }

    /** @return BelongsTo<InventarisUnit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(InventarisUnit::class, 'inventaris_unit_id');
    }

    /** @return BelongsTo<Ruang, $this> */
    public function asalRuang(): BelongsTo
    {
        return $this->belongsTo(Ruang::class, 'asal_ruang_id');
    }

    /** @return BelongsTo<Ruang, $this> */
    public function tujuanRuang(): BelongsTo
    {
        return $this->belongsTo(Ruang::class, 'tujuan_ruang_id');
    }

    /** @return BelongsTo<Pegawai, $this> */
    public function asalPegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'asal_pegawai_id');
    }

    /** @return BelongsTo<Pegawai, $this> */
    public function tujuanPegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'tujuan_pegawai_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
