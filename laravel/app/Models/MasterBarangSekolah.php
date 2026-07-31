<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MasterBarangSekolah extends Model
{
    use HasPublicId;

    protected $table = 'master_barang_sekolah';

    public $timestamps = false;

    protected $fillable = [
        'kategori', 'id_uraian', 'no_sp2d', 'sumber_perolehan',
        'bulan_realisasi', 'no_spk', 'ba_no', 'ba_tgl', 'kode_barang',
        'nama_barang', 'jenis_aset', 'merk_tipe', 'no_sertifikat',
        'ukuran_bangunan', 'satuan', 'volume', 'harga_satuan',
        'nilai_perolehan',
    ];

    public function scopeForSchool(Builder $query, int|string $schoolId): Builder
    {
        return $query->where('id_sekolah', (string) $schoolId);
    }

    protected function casts(): array
    {
        return [
            'ba_tgl' => 'date',
            'volume' => 'decimal:2',
            'harga_satuan' => 'decimal:2',
            'nilai_perolehan' => 'decimal:2',
            'is_realisasi' => 'boolean',
        ];
    }
}
