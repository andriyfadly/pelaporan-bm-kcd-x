<?php

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

it('assigns an immutable UUIDv7 public id', function (): void {
    $model = new class extends Model
    {
        use HasPublicId;
    };

    $model->assignPublicId();
    $publicId = $model->public_id;
    $model->assignPublicId();

    expect(Str::isUuid($publicId))->toBeTrue()
        ->and($publicId[14])->toBe('7')
        ->and($model->public_id)->toBe($publicId)
        ->and($model->getRouteKeyName())->toBe('public_id');
});
