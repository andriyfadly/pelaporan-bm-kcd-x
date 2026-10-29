<?php

namespace App\Models\PencatatanBm;

use App\Models\Master\Pegawai;
use App\Models\Master\Ruang;
use App\Models\Master\Sekolah;
use App\Models\PelaporanBm\Realisasi;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Satu unit inventaris individual. Dibentuk dari satu baris realisasi yang
 * periodenya sudah disetujui; `sekolah_id` dan `realisasi_id` tidak berubah.
 *
 * @property string $id
 * @property string $sekolah_id
 * @property string|null $realisasi_id
 * @property int|null $unit_index
 * @property string $kode_barang
 * @property string $nomor_register
 * @property string|null $nama_barang
 * @property string|null $jenis_aset
 * @property string|null $merk_tipe
 * @property string|null $no_sertifikat
 * @property string|null $ukuran_bangunan
 * @property string|null $satuan
 * @property Carbon|null $tanggal_perolehan
 * @property int|null $bulan_perolehan
 * @property int $tahun_perolehan
 * @property float $harga_satuan
 * @property float $nilai_perolehan_unit
 * @property string|null $asal_perolehan
 * @property string $kondisi
 * @property string $status
 * @property string|null $ruang_id
 * @property string|null $pegawai_id
 * @property string|null $foto_path
 * @property string|null $keterangan
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Sekolah|null $sekolah
 * @property-read Realisasi|null $realisasi
 * @property-read Ruang|null $ruang
 * @property-read Pegawai|null $pegawai
 * @property-read Collection<int, Mutasi> $mutasi
 * @property-read Collection<int, Foto> $foto
 * @property-read Collection<int, Dokumen> $dokumen
 */
class InventarisUnit extends Model
{
    use HasUuids;

    public const KONDISI = ['B' => 'Baik', 'RR' => 'Rusak Ringan', 'RB' => 'Rusak Berat'];

    public const STATUS_AKTIF = 'aktif';

    public const STATUS_DIKEMBALIKAN = 'dikembalikan';

    public const STATUS_DIHAPUS_KOREKSI = 'dihapus_koreksi';

    protected $table = 'inventaris_unit';

    protected $attributes = [
        'kondisi' => 'B',
        'status' => self::STATUS_AKTIF,
    ];

    protected $fillable = [
        'sekolah_id',
        'realisasi_id',
        'unit_index',
        'kode_barang',
        'nomor_register',
        'nama_barang',
        'jenis_aset',
        'merk_tipe',
        'no_sertifikat',
        'ukuran_bangunan',
        'satuan',
        'tanggal_perolehan',
        'bulan_perolehan',
        'tahun_perolehan',
        'harga_satuan',
        'nilai_perolehan_unit',
        'asal_perolehan',
        'kondisi',
        'status',
        'ruang_id',
        'pegawai_id',
        'foto_path',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'unit_index' => 'integer',
            'bulan_perolehan' => 'integer',
            'tahun_perolehan' => 'integer',
            'tanggal_perolehan' => 'date',
            'harga_satuan' => 'decimal:2',
            'nilai_perolehan_unit' => 'decimal:2',
        ];
    }

    /**
     * Tahun perolehan ikut menentukan bucket nomor register, jadi tidak boleh
     * kosong. Diisi dari tanggal perolehan bila pemanggil lupa.
     */
    protected static function booted(): void
    {
        static::saving(function (self $unit): void {
            $unit->tahun_perolehan ??= $unit->tanggal_perolehan->year ?? now()->year;
        });
    }

    /** @return BelongsTo<Sekolah, $this> */
    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class, 'sekolah_id');
    }

    /** @return BelongsTo<Realisasi, $this> */
    public function realisasi(): BelongsTo
    {
        return $this->belongsTo(Realisasi::class, 'realisasi_id');
    }

    /** @return BelongsTo<Ruang, $this> */
    public function ruang(): BelongsTo
    {
        return $this->belongsTo(Ruang::class, 'ruang_id');
    }

    /** @return BelongsTo<Pegawai, $this> */
    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    /** @return HasMany<Mutasi, $this> */
    public function mutasi(): HasMany
    {
        return $this->hasMany(Mutasi::class, 'inventaris_unit_id')->latest('tanggal')->latest('created_at');
    }

    /** @return HasMany<Foto, $this> */
    public function foto(): HasMany
    {
        return $this->hasMany(Foto::class, 'inventaris_unit_id');
    }

    /** @return HasMany<Dokumen, $this> */
    public function dokumen(): HasMany
    {
        return $this->hasMany(Dokumen::class, 'inventaris_unit_id');
    }
}
