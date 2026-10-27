<?php

namespace App\Models\PencatatanBm;

use App\Models\Master\Sekolah;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class, 'sekolah_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(InventarisUnit::class, 'inventaris_unit_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
