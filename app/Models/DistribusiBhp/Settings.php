<?php

namespace App\Models\DistribusiBhp;

use App\Models\DistribusiBhp\Concerns\HasSumberDanaTable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Settings extends Model
{
    use HasSumberDanaTable, HasUuids;

    protected $fillable = [
        'format_nomor',
    ];

    public static function suffixTabel(): string
    {
        return 'settings';
    }
}
