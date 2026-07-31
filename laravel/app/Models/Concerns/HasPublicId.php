<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasPublicId
{
    public static function bootHasPublicId(): void
    {
        static::creating(fn (Model $model) => $model->assignPublicId());
    }

    public function assignPublicId(): void
    {
        $this->public_id ??= (string) Str::uuid7();
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
