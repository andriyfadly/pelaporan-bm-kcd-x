<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class RealisasiBarangSekolah extends Model
{
    use HasPublicId;

    protected $table = 'realisasi_barang_sekolah';

    public $timestamps = false;

    protected $fillable = [
        'id_master_barang', 'id_uraian', 'no_sp2d', 'sumber_perolehan',
        'kodering_belanja', 'bulan_realisasi', 'no_spk', 'ba_no', 'ba_tgl',
        'kode_barang', 'nama_barang', 'jenis_aset', 'merk_tipe',
        'no_sertifikat', 'ukuran_bangunan', 'satuan', 'volume',
        'harga_satuan', 'nilai_perolehan', 'is_realisasi',
    ];

    protected function casts(): array
    {
        return [
            'ba_tgl' => 'date',
            'harga_satuan' => 'decimal:2',
            'nilai_perolehan' => 'decimal:2',
            'is_realisasi' => 'boolean',
        ];
    }
}
