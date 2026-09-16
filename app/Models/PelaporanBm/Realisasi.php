<?php

namespace App\Models\PelaporanBm;

use App\Models\Master\Sekolah;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('sistem')
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function spj(): BelongsTo
    {
        return $this->belongsTo(Spj::class, 'spj_id');
    }

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class, 'sekolah_id');
    }

    public function acuan(): BelongsTo
    {
        return $this->belongsTo(Acuan::class, 'acuan_id');
    }
}
