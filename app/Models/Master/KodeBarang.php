<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property string $id
 * @property string $kode_barang
 * @property string $uraian
 * @property string|null $kodering_aset
 * @property string|null $jenis_aset
 * @property int $umur_ekonomis
 * @property string|null $satuan
 * @property float $harga_standar
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
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
