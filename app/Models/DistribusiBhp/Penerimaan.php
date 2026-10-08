<?php

namespace App\Models\DistribusiBhp;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Penerimaan extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'bhp_penerimaan';

    protected $fillable = [
        'sekolah_id',
        'no_nota_invoice',
        'no_bast',
        'tanggal_bast',
        'no_kwitansi',
        'penyedia_id',
        'tanggal_titimangsa',
        'is_saldo_awal',
        'periode_tahun',
        'sumber_dana',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_bast' => 'date',
            'tanggal_titimangsa' => 'date',
            'is_saldo_awal' => 'boolean',
            'periode_tahun' => 'integer',
        ];
    }

    public function penyedia(): BelongsTo
    {
        return $this->belongsTo(Penyedia::class, 'penyedia_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(PenerimaanDetail::class, 'bhp_penerimaan_id');
    }
}
