<?php

namespace App\Models\DistribusiBhp;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Settings extends Model
{
    use HasUuids;

    protected $table = 'bhp_settings';

    protected $fillable = [
        'format_nomor',
        'paper_size',
        'orientation',
    ];
}
