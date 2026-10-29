<?php

namespace App\Models\PencatatanBm;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Penomoran register atomic per (sekolah_id, kode_barang). Nomor yang sudah
 * terbit tidak pernah dipakai ulang.
 *
 * @property string $id
 * @property string $sekolah_id
 * @property string $kode_barang
 * @property int $tahun
 * @property int $next_number
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class InventarisRegisterSequence extends Model
{
    use HasUuids;

    protected $table = 'inventaris_register_sequences';

    protected $fillable = ['sekolah_id', 'kode_barang', 'tahun', 'next_number'];

    protected function casts(): array
    {
        return ['tahun' => 'integer', 'next_number' => 'integer'];
    }

    /**
     * Ambil nomor register berikutnya. Nomor diulang dari 001 setiap tahun
     * perolehan, seperti aplikasi acuan.
     *
     * Harus dipanggil di dalam transaksi pemanggil: baris sequence dikunci agar
     * dua pendaftaran bersamaan tidak mendapat nomor yang sama.
     */
    public static function alokasi(string $sekolahId, string $kodeBarang, ?int $tahun = null): string
    {
        $tahun = (int) ($tahun ?: now()->year);

        static::query()->insertOrIgnore([
            'id' => (string) str()->uuid(),
            'sekolah_id' => $sekolahId,
            'kode_barang' => $kodeBarang,
            'tahun' => $tahun,
            'next_number' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sequence = static::where('sekolah_id', $sekolahId)
            ->where('kode_barang', $kodeBarang)
            ->where('tahun', $tahun)
            ->lockForUpdate()
            ->firstOrFail();

        $nomor = str_pad((string) $sequence->next_number, 3, '0', STR_PAD_LEFT);

        $sequence->update(['next_number' => $sequence->next_number + 1]);

        return $nomor;
    }
}
