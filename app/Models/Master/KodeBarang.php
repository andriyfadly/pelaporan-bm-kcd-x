<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class KodeBarang extends Model
{
    use HasFactory, HasUuids, LogsActivity;

    protected $table = 'master_data_kode_barang';

    protected $fillable = [
        'kode_barang',
        'uraian',
        'kodering_aset',
        'jenis_aset',
        'umur_ekonomis',
        'satuan',
        'harga_standar',
    ];

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
            'umur_ekonomis' => 'integer',
            'harga_standar' => 'float',
        ];
    }
}
