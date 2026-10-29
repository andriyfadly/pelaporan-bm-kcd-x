<?php

namespace App\Models\DistribusiBhp;

use App\Models\DistribusiBhp\Concerns\HasSumberDanaTable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $bhp_penerimaan_id
 * @property string|null $barang_id
 * @property string|null $harga_barang_id
 * @property int $jumlah
 * @property int|null $harga_satuan
 * @property int|null $nilai_total
 * @property string|null $merek_type
 * @property string|null $keterangan
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Penerimaan|null $penerimaan
 * @property-read Barang|null $barang
 */
class PenerimaanDetail extends Model
{
    use HasFactory, HasSumberDanaTable, HasUuids;

    protected $fillable = [
        'bhp_penerimaan_id',
        'barang_id',
        'harga_barang_id',
        'jumlah',
        'harga_satuan',
        'nilai_total',
        'merek_type',
        'keterangan',
    ];

    public static function suffixTabel(): string
    {
        return 'penerimaan_detail';
    }

    protected function casts(): array
    {
        return [
            'jumlah' => 'integer',
            'harga_satuan' => 'integer',
            'nilai_total' => 'integer',
        ];
    }

    /** @return BelongsTo<Penerimaan, $this> */
    public function penerimaan(): BelongsTo
    {
        return $this->belongsTo(Penerimaan::class, 'bhp_penerimaan_id');
    }

    /** @return BelongsTo<Barang, $this> */
    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }
}
