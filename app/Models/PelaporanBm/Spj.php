<?php

namespace App\Models\PelaporanBm;

use App\Models\Master\Sekolah;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Spj extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'pelaporan_bm_spj';

    protected $fillable = [
        'sekolah_id',
        'acuan_id',
        'kategori',
        'no_sp2d',
        'sumber_perolehan',
        'bulan_realisasi',
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
        'is_realisasi',
        'id_spj_lama',
    ];

    protected function casts(): array
    {
        return [
            'is_realisasi' => 'boolean',
            'volume' => 'float',
            'harga_satuan' => 'float',
            'nilai_perolehan' => 'float',
        ];
    }

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class, 'sekolah_id');
    }

    public function acuan(): BelongsTo
    {
        return $this->belongsTo(Acuan::class, 'acuan_id');
    }

    public function realisasi(): HasMany
    {
        return $this->hasMany(Realisasi::class, 'spj_id');
    }
}
