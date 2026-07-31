<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterSchoolProjection extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'source_updated_at' => 'datetime',
        ];
    }
}
