<?php

namespace App\Models\PencatatanBm;

use App\Models\Master\Sekolah;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Salinan dokumen cetak. `payload` menyimpan snapshot agar cetak ulang tetap
 * sama setelah data master berubah.
 *
 * @property string $id
 * @property string $sekolah_id
 * @property string $jenis
 * @property string|null $nomor
 * @property Carbon|null $nomor_dibatalkan_pada
 * @property Carbon|null $tanggal
 * @property string|null $inventaris_unit_id
 * @property string|null $inventaris_pegawai_id
 * @property string|null $inventaris_ruang_id
 * @property array<string, mixed> $payload
 * @property string|null $file_path
 * @property string|null $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Sekolah|null $sekolah
 * @property-read User|null $user
 */
class Dokumen extends Model
{
    use HasUuids;

    protected $table = 'inventaris_dokumen';

    protected $fillable = [
        'sekolah_id',
        'jenis',
        'nomor',
        'nomor_dibatalkan_pada',
        'tanggal',
        'inventaris_unit_id',
        'inventaris_pegawai_id',
        'inventaris_ruang_id',
        'payload',
        'file_path',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'nomor_dibatalkan_pada' => 'datetime',
            'payload' => 'array',
        ];
    }

    /** @return BelongsTo<Sekolah, $this> */
    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class, 'sekolah_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Dokumen BAST yang nomornya sudah terisi dan belum dibatalkan. Hanya
     * baris inilah yang dianggap "BA sudah jadi" saat pengembalian.
     *
     * @return Builder<static>
     */
    public static function bastBernomor(?string $sekolahId = null): Builder
    {
        return static::query()
            ->when($sekolahId, fn (Builder $q) => $q->where('sekolah_id', $sekolahId))
            ->where('jenis', 'bast')
            ->whereNotNull('nomor')
            ->whereNull('nomor_dibatalkan_pada');
    }
}
