<?php

namespace App\Models\PelaporanBm;

use App\Models\Master\Sekolah;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property string $id
 * @property string|null $sekolah_id
 * @property int|null $bulan
 * @property int $tahun
 * @property bool $status_kunci
 * @property Carbon|null $dikunci_pada
 * @property string|null $dikunci_oleh
 * @property string $status_kirim
 * @property Carbon|null $dikirim_pada
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Sekolah|null $sekolah
 * @property-read User|null $pengunci
 */
class KunciLaporan extends Model
{
    use HasFactory, HasUuids, LogsActivity;

    protected $table = 'pelaporan_bm_kunci_laporan';

    protected $fillable = [
        'sekolah_id',
        'bulan',
        'tahun',
        'status_kunci',
        'dikunci_pada',
        'dikunci_oleh',
        'status_kirim',
        'dikirim_pada',
    ];

    protected function casts(): array
    {
        return [
            'status_kunci' => 'boolean',
            'dikunci_pada' => 'datetime',
            'dikirim_pada' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('sistem')
            ->logOnly(['sekolah_id', 'bulan', 'tahun', 'status_kunci', 'status_kirim', 'dikunci_oleh'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /** @return BelongsTo<Sekolah, $this> */
    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class, 'sekolah_id');
    }

    /** @return BelongsTo<User, $this> */
    public function pengunci(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dikunci_oleh');
    }
}
