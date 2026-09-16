<?php

namespace App\Models\PelaporanBm;

use App\Models\Master\Sekolah;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

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

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class, 'sekolah_id');
    }

    public function pengunci(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dikunci_oleh');
    }
}
