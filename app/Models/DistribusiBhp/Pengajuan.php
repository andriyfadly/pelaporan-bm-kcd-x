<?php

namespace App\Models\DistribusiBhp;

use App\Models\DistribusiBhp\Concerns\HasSumberDanaTable;
use App\Models\Master\Sekolah;
use App\Models\User;
use App\Support\DistribusiBhp\Periode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $sekolah_id
 * @property string|null $divisi_id
 * @property string|null $user_id
 * @property string|null $nomor_pengajuan
 * @property CarbonImmutable|null $tanggal
 * @property string $status
 * @property string|null $review_notes
 * @property string|null $rejection_reason
 * @property string|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, PengajuanDetail> $details
 * @property-read Collection<int, Dokumen> $dokumen
 * @property-read User|null $pemohon
 * @property-read Sekolah|null $sekolah
 * @property-read Divisi|null $divisi
 * @property-read User|null $reviewer
 */
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

    /**
     * Pengajuan yang tanggal transaksinya paling lambat $tanggal. Tanggal
     * pengajuan adalah tanggal bisnis (bisa mundur), created_at hanya cadangan
     * untuk data lama tanpa tanggal.
     *
     * @param  Builder<Pengajuan>  $query
     * @return Builder<Pengajuan>
     */
    public function scopeEfektifSampai(Builder $query, string $tanggal): Builder
    {
        return $query->where(function (Builder $q) use ($tanggal) {
            $q->whereDate('tanggal', '<=', $tanggal)
                ->orWhere(fn (Builder $tanpaTanggal) => $tanpaTanggal
                    ->whereNull('tanggal')
                    ->whereDate('created_at', '<=', $tanggal));
        });
    }

    /**
     * Pengajuan yang tanggal transaksinya jatuh di dalam periode. Tanggal
     * pengajuan adalah tanggal bisnis (bisa mundur); created_at hanya cadangan
     * untuk data lama tanpa tanggal.
     *
     * @param  Builder<Pengajuan>  $query
     * @return Builder<Pengajuan>
     */
    public function scopeDalamPeriode(Builder $query, Periode $periode): Builder
    {
        $mulai = $periode->mulai->toDateString();
        $sampai = $periode->sampai();

        return $query->where(function (Builder $q) use ($mulai, $sampai) {
            $q->whereDate('tanggal', '>=', $mulai)
                ->whereDate('tanggal', '<=', $sampai)
                ->orWhere(fn (Builder $tanpaTanggal) => $tanpaTanggal
                    ->whereNull('tanggal')
                    ->whereDate('created_at', '>=', $mulai)
                    ->whereDate('created_at', '<=', $sampai));
        });
    }

    /** @return HasMany<PengajuanDetail, $this> */
    public function details(): HasMany
    {
        return $this->hasMany(PengajuanDetail::class, 'bhp_pengajuan_id');
    }

    /** @return HasMany<Dokumen, $this> */
    public function dokumen(): HasMany
    {
        return $this->hasMany(Dokumen::class, 'bhp_pengajuan_id');
    }

    /** @return BelongsTo<User, $this> */
    public function pemohon(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<Sekolah, $this> */
    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class, 'sekolah_id');
    }

    /** @return BelongsTo<Divisi, $this> */
    public function divisi(): BelongsTo
    {
        return $this->belongsTo(Divisi::class, 'divisi_id');
    }

    /** @return BelongsTo<User, $this> */
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

    /** Tanggal bisnis pengajuan; created_at hanya cadangan data lama. */
    public function tanggalEfektif(): ?CarbonImmutable
    {
        if ($this->tanggal) {
            return CarbonImmutable::parse($this->tanggal)->startOfDay();
        }

        return $this->created_at ? CarbonImmutable::parse($this->created_at)->startOfDay() : null;
    }
}
