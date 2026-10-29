<?php

namespace App\Models\PencatatanBm;

use App\Models\Master\Sekolah;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $sekolah_id
 * @property string $inventaris_unit_id
 * @property string $path
 * @property string $mime
 * @property int $size
 * @property string $checksum
 * @property string|null $uploaded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Sekolah|null $sekolah
 * @property-read InventarisUnit|null $unit
 * @property-read User|null $uploader
 */
class Foto extends Model
{
    use HasUuids;

    protected $table = 'inventaris_foto';

    protected $fillable = [
        'sekolah_id',
        'inventaris_unit_id',
        'path',
        'mime',
        'size',
        'checksum',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    /** @return BelongsTo<Sekolah, $this> */
    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class, 'sekolah_id');
    }

    /** @return BelongsTo<InventarisUnit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(InventarisUnit::class, 'inventaris_unit_id');
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
