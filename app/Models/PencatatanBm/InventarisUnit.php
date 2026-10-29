<?php

namespace App\Models\PencatatanBm;

use App\Models\Master\Pegawai;
use App\Models\Master\Ruang;
use App\Models\Master\Sekolah;
use App\Models\PelaporanBm\Realisasi;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu unit inventaris individual. Dibentuk dari satu baris realisasi yang
 * periodenya sudah disetujui; `sekolah_id` dan `realisasi_id` tidak berubah.
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
            $unit->tahun_perolehan ??= $unit->tanggal_perolehan?->year ?? now()->year;
        });
    }

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class, 'sekolah_id');
    }

    public function realisasi(): BelongsTo
    {
        return $this->belongsTo(Realisasi::class, 'realisasi_id');
    }

    public function ruang(): BelongsTo
    {
        return $this->belongsTo(Ruang::class, 'ruang_id');
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    public function mutasi(): HasMany
    {
        return $this->hasMany(Mutasi::class, 'inventaris_unit_id')->latest('tanggal')->latest('created_at');
    }

    public function foto(): HasMany
    {
        return $this->hasMany(Foto::class, 'inventaris_unit_id');
    }

    public function dokumen(): HasMany
    {
        return $this->hasMany(Dokumen::class, 'inventaris_unit_id');
    }
}
