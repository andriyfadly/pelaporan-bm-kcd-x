<?php

namespace App\Services\PencatatanBm;

use App\Models\PencatatanBm\InventarisUnit;
use App\Models\PencatatanBm\Mutasi;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Penempatan/mutasi/pengembalian unit. Semua perubahan state didahului satu
 * baris histori; `sekolah_id` dan `realisasi_id` tidak pernah berubah.
 */
class MutasiInventarisService
{
    /**
     * @param  array{ruang_id?: ?string, pegawai_id?: ?string, kondisi?: ?string, tanggal: string, keterangan?: ?string}  $data
     */
    public function tempatkan(InventarisUnit $unit, array $data, ?string $userId): Mutasi
    {
        return DB::transaction(function () use ($unit, $data, $userId): Mutasi {
            $unit = InventarisUnit::whereKey($unit->id)->lockForUpdate()->firstOrFail();

            if ($unit->status === InventarisUnit::STATUS_DIHAPUS_KOREKSI) {
                throw ValidationException::withMessages([
                    'unit' => 'Unit sudah dihapus karena koreksi sumber dan tidak bisa ditempatkan.',
                ]);
            }

            $pertama = $unit->ruang_id === null && $unit->pegawai_id === null;
            $kondisiBaru = $data['kondisi'] ?? $unit->kondisi;

            // Kunci yang tidak dikirim berarti "tidak berubah"; null eksplisit
            // berarti "kosongkan penempatan".
            $tujuanRuang = array_key_exists('ruang_id', $data) ? $data['ruang_id'] : $unit->ruang_id;
            $tujuanPegawai = array_key_exists('pegawai_id', $data) ? $data['pegawai_id'] : $unit->pegawai_id;

            $mutasi = $this->catat($unit, [
                'jenis' => $pertama ? 'penempatan' : 'mutasi',
                'tujuan_ruang_id' => $tujuanRuang,
                'tujuan_pegawai_id' => $tujuanPegawai,
                'tujuan_kondisi' => $kondisiBaru,
                'tanggal' => $data['tanggal'],
                'keterangan' => $data['keterangan'] ?? null,
                'user_id' => $userId,
            ]);

            $unit->update([
                'ruang_id' => $mutasi->tujuan_ruang_id,
                'pegawai_id' => $mutasi->tujuan_pegawai_id,
                'kondisi' => $kondisiBaru,
                'status' => InventarisUnit::STATUS_AKTIF,
            ]);

            return $mutasi;
        });
    }

    /**
     * @param  array{kondisi: string, tanggal: string, keterangan?: ?string}  $data
     */
    public function kembalikan(InventarisUnit $unit, array $data, ?string $userId): Mutasi
    {
        return DB::transaction(function () use ($unit, $data, $userId): Mutasi {
            $unit = InventarisUnit::whereKey($unit->id)->lockForUpdate()->firstOrFail();

            $aktif = $unit->status === InventarisUnit::STATUS_AKTIF
                && ($unit->ruang_id !== null || $unit->pegawai_id !== null);

            if (! $aktif) {
                throw ValidationException::withMessages([
                    'unit' => 'Unit ini tidak sedang ditempatkan, jadi tidak bisa dikembalikan.',
                ]);
            }

            $mutasi = $this->catat($unit, [
                'jenis' => 'pengembalian',
                'tujuan_ruang_id' => null,
                'tujuan_pegawai_id' => null,
                'tujuan_kondisi' => $data['kondisi'],
                'tanggal' => $data['tanggal'],
                'keterangan' => $data['keterangan'] ?? null,
                'user_id' => $userId,
            ]);

            $unit->update([
                'ruang_id' => null,
                'pegawai_id' => null,
                'kondisi' => $data['kondisi'],
                'status' => InventarisUnit::STATUS_DIKEMBALIKAN,
            ]);

            return $mutasi;
        });
    }

    /**
     * @param  array<string, mixed>  $atribut
     */
    private function catat(InventarisUnit $unit, array $atribut): Mutasi
    {
        return Mutasi::create($atribut + [
            'sekolah_id' => $unit->sekolah_id,
            'inventaris_unit_id' => $unit->id,
            'asal_ruang_id' => $unit->ruang_id,
            'asal_pegawai_id' => $unit->pegawai_id,
            'asal_kondisi' => $unit->kondisi,
        ]);
    }
}
