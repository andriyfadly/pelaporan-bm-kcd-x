<?php

namespace App\Models\DistribusiBhp;

use App\Models\DistribusiBhp\Concerns\HasSumberDanaTable;
use App\Models\Master\Sekolah;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pengajuan extends Model
{
    use HasFactory, HasSumberDanaTable, HasUuids;

    protected $fillable = [
        'sekolah_id',
        'divisi_id',
        'user_id',
        'nomor_pengajuan',
        'tanggal',
        'status',
        'review_notes',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    public static function suffixTabel(): string
    {
        return 'pengajuan';
    }

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function details(): HasMany
    {
        return $this->hasMany(PengajuanDetail::class, 'bhp_pengajuan_id');
    }

    public function dokumen(): HasMany
    {
        return $this->hasMany(Dokumen::class, 'bhp_pengajuan_id');
    }

    public function pemohon(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class, 'sekolah_id');
    }

    public function divisi(): BelongsTo
    {
        return $this->belongsTo(Divisi::class, 'divisi_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
