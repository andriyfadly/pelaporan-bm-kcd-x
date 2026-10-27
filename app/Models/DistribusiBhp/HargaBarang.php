<?php

namespace App\Models\DistribusiBhp;

use App\Models\DistribusiBhp\Concerns\HasSumberDanaTable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HargaBarang extends Model
{
    use HasFactory, HasSumberDanaTable, HasUuids;

    protected $fillable = [
        'barang_id',
        'harga_satuan',
        'created_by',
    ];

    public static function suffixTabel(): string
    {
        return 'harga_barang';
    }

    protected function casts(): array
    {
        return [
            'harga_satuan' => 'integer',
        ];
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }
}
