<?php

namespace App\Models\PelaporanBm;

use App\Models\Master\Sekolah;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property string $id
 * @property string|null $sekolah_id
 * @property string|null $tanggal
 * @property string|null $kodering
 * @property string|null $bku
 * @property string|null $uraian
 * @property float|null $nominal
 * @property int|null $bulan
 * @property int $tahun
 * @property int|null $id_acuan_lama
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Sekolah|null $sekolah
 * @property-read Collection<int, Spj> $spj
 * @property-read Collection<int, Realisasi> $realisasi
 */
class Acuan extends Model
{
    use HasFactory, HasUuids, LogsActivity;

    protected $table = 'pelaporan_bm_acuan';

    protected $fillable = [
        'sekolah_id',
        'tanggal',
        'kodering',
        'bku',
        'uraian',
        'nominal',
        'bulan',
        'tahun',
        'id_acuan_lama',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $acuan): void {
            $acuan->tahun ??= $acuan->tanggal ? (int) date('Y', strtotime((string) $acuan->tanggal)) : (int) now()->year;
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('sistem')
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /** @return BelongsTo<Sekolah, $this> */
    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class, 'sekolah_id');
    }

    /** @return HasMany<Spj, $this> */
    public function spj(): HasMany
    {
        return $this->hasMany(Spj::class, 'acuan_id');
    }

    /** @return HasMany<Realisasi, $this> */
    public function realisasi(): HasMany
    {
        return $this->hasMany(Realisasi::class, 'acuan_id');
    }
}
