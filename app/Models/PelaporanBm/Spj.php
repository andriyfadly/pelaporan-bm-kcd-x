<?php

namespace App\Models\PelaporanBm;

use App\Models\Master\Sekolah;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property string $id
 * @property string|null $sekolah_id
 * @property string|null $acuan_id
 * @property string|null $kategori
 * @property string|null $no_sp2d
 * @property string|null $sumber_perolehan
 * @property int|null $bulan_realisasi
 * @property int $tahun
 * @property string $no_spk
 * @property string|null $ba_no
 * @property string|null $ba_tgl
 * @property string $kode_barang
 * @property string $nama_barang
 * @property string $jenis_aset
 * @property string|null $merk_tipe
 * @property string|null $no_sertifikat
 * @property string|null $ukuran_bangunan
 * @property string|null $satuan
 * @property float $volume
 * @property float $harga_satuan
 * @property float $nilai_perolehan
 * @property int|null $id_spj_lama
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Sekolah|null $sekolah
 * @property-read Acuan|null $acuan
 * @property-read Collection<int, Realisasi> $realisasi
 * @property-read bool $is_realisasi
 */
class Spj extends Model
{
    use HasFactory, HasUuids, LogsActivity;

    protected $table = 'pelaporan_bm_spj';

    protected $fillable = [
        'sekolah_id',
        'acuan_id',
        'kategori',
        'no_sp2d',
        'sumber_perolehan',
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
        'id_spj_lama',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $spj): void {
            $spj->tahun ??= $spj->ba_tgl ? (int) date('Y', strtotime((string) $spj->ba_tgl)) : (int) now()->year;
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

    protected function casts(): array
    {
        return [
            'volume' => 'float',
            'harga_satuan' => 'float',
            'nilai_perolehan' => 'float',
        ];
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

    /** @return HasMany<Realisasi, $this> */
    public function realisasi(): HasMany
    {
        return $this->hasMany(Realisasi::class, 'spj_id');
    }

    public function getIsRealisasiAttribute(): bool
    {
        return $this->relationLoaded('realisasi')
            ? $this->realisasi->isNotEmpty()
            : $this->realisasi()->exists();
    }
}
