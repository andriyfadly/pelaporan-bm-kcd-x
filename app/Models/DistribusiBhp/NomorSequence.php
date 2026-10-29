<?php

namespace App\Models\DistribusiBhp;

use App\Models\DistribusiBhp\Concerns\HasSumberDanaTable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $jenis
 * @property int $tahun
 * @property int $bulan
 * @property int $counter
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class NomorSequence extends Model
{
    use HasFactory, HasSumberDanaTable, HasUuids;

    protected $fillable = [
        'jenis',
        'tahun',
        'bulan',
        'counter',
    ];

    public static function suffixTabel(): string
    {
        return 'nomor_sequence';
    }

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'bulan' => 'integer',
            'counter' => 'integer',
        ];
    }
}
