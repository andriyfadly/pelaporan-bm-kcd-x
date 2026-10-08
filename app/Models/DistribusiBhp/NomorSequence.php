<?php

namespace App\Models\DistribusiBhp;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NomorSequence extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'bhp_nomor_sequence';

    protected $fillable = [
        'jenis',
        'sumber_dana',
        'tahun',
        'bulan',
        'counter',
    ];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'bulan' => 'integer',
            'counter' => 'integer',
        ];
    }
}
