<?php

namespace App\Models\DistribusiBhp;

use App\Models\DistribusiBhp\Concerns\HasSumberDanaTable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Master barang BHP (`bhp_bos_barang` / `bhp_bopd_barang`).
 *
 * @property string $id
 * @property string|null $kode_barang
 * @property string $nama_barang
 * @property string|null $spesifikasi_barang_nusp
 * @property string|null $satuan
 * @property string|null $kode_rekening_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read KodeRekening|null $kodeRekening
 * @property-read Collection<int, HargaBarang> $harga
 */
class Barang extends Model
{
    use HasFactory, HasSumberDanaTable, HasUuids;

    protected $fillable = [
        'kode_barang',
        'nama_barang',
        'spesifikasi_barang_nusp',
        'satuan',
        'kode_rekening_id',
    ];

    public static function suffixTabel(): string
    {
        return 'barang';
    }

    /** @return BelongsTo<KodeRekening, $this> */
    public function kodeRekening(): BelongsTo
    {
        return $this->belongsTo(KodeRekening::class, 'kode_rekening_id');
    }

    /** @return HasMany<HargaBarang, $this> */
    public function harga(): HasMany
    {
        return $this->hasMany(HargaBarang::class, 'barang_id');
    }
}
