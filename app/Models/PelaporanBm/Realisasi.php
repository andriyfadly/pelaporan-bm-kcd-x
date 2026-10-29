<?php

namespace App\Models\PelaporanBm;

use App\Models\Master\Sekolah;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property string $id
 * @property string|null $spj_id
 * @property string|null $sekolah_id
 * @property string|null $acuan_id
 * @property string|null $no_sp2d
 * @property string|null $sumber_perolehan
 * @property string|null $kodering_belanja
 * @property int|null $bulan_realisasi
 * @property int $tahun
 * @property string|null $no_spk
 * @property string|null $ba_no
 * @property string|null $ba_tgl
 * @property string $kode_barang
 * @property string|null $nama_barang
 * @property string|null $jenis_aset
 * @property string|null $merk_tipe
 * @property string|null $no_sertifikat
 * @property string|null $ukuran_bangunan
 * @property string|null $satuan
 * @property float $volume
 * @property float $harga_satuan
 * @property float $nilai_perolehan
 * @property int|null $id_realisasi_lama
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Spj|null $spj
 * @property-read Sekolah|null $sekolah
 * @property-read Acuan|null $acuan
 */
class Realisasi extends Model
{
    use HasFactory, HasUuids, LogsActivity;

    protected $table = 'pelaporan_bm_realisasi';

    protected $fillable = [
        'spj_id',
        'sekolah_id',
        'acuan_id',
        'no_sp2d',
        'sumber_perolehan',
        'kodering_belanja',
        'bulan_realisasi',
        'tahun',
        'no_spk',
        'ba_no',
        'ba_tgl',
        'kode_barang',
        'nama_barang',
        'jenis_aset',
        'merk_tipe',
        'no_sertifikat',
        'ukuran_bangunan',
        'satuan',
        'volume',
        'harga_satuan',
        'nilai_perolehan',
        'id_realisasi_lama',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $realisasi): void {
            $realisasi->tahun ??= $realisasi->ba_tgl ? (int) date('Y', strtotime((string) $realisasi->ba_tgl)) : (int) now()->year;
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('sistem')
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /** @return BelongsTo<Spj, $this> */
    public function spj(): BelongsTo
    {
        return $this->belongsTo(Spj::class, 'spj_id');
    }

    /** @return BelongsTo<Sekolah, $this> */
    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class, 'sekolah_id');
    }

    /** @return BelongsTo<Acuan, $this> */
    public function acuan(): BelongsTo
    {
        return $this->belongsTo(Acuan::class, 'acuan_id');
    }
}
