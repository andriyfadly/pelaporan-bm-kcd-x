<?php

namespace App\Services\PencatatanBm;

use App\Models\Master\KodeBarang;
use App\Models\PencatatanBm\InventarisRegisterSequence;
use App\Models\PencatatanBm\InventarisUnit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pendaftaran unit inventaris di luar sinkronisasi realisasi (input manual dan
 * impor Excel), mengikuti alur aplikasi acuan: satu baris barang dengan volume N
 * melahirkan N unit, masing-masing dengan nomor register sendiri.
 *
 * Katalog kode barang hanya dipakai sebagai nilai default; kode yang belum ada
 * di katalog tetap boleh didaftarkan seperti pada aplikasi acuan.
 */
class PendaftaranInventarisService
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<int, InventarisUnit>
     */
    public function daftarkan(string $sekolahId, array $data): array
    {
        $volume = max(1, (int) ($data['volume'] ?? 1));
        $kode = trim((string) $data['kode_barang']);

        $katalog = KodeBarang::where('kode_barang', $kode)->first();

        $tanggal = ! empty($data['tanggal_perolehan'])
            ? Carbon::parse($data['tanggal_perolehan'])
            : now();

        $harga = (float) ($data['harga_satuan'] ?? 0);
        $namaBarang = trim((string) ($data['nama_barang'] ?? ''));
        $satuan = trim((string) ($data['satuan'] ?? ''));
        $tahun = (int) $tanggal->year;

        return DB::transaction(function () use ($sekolahId, $data, $volume, $kode, $katalog, $tanggal, $tahun, $harga, $namaBarang, $satuan): array {
            $unit = [];

            for ($i = 0; $i < $volume; $i++) {
                $unit[] = InventarisUnit::create([
                    'sekolah_id' => $sekolahId,
                    'realisasi_id' => null,
                    'unit_index' => null,
                    'kode_barang' => $kode,
                    'nomor_register' => InventarisRegisterSequence::alokasi($sekolahId, $kode, $tahun),
                    'nama_barang' => $namaBarang !== '' ? $namaBarang : $katalog?->uraian,
                    'jenis_aset' => $data['jenis_aset'] ?? $katalog?->jenis_aset,
                    'merk_tipe' => $data['merk_tipe'] ?? null,
                    'no_sertifikat' => $data['no_sertifikat'] ?? null,
                    'ukuran_bangunan' => $data['ukuran_bangunan'] ?? null,
                    'satuan' => $satuan !== '' ? $satuan : ($katalog?->satuan ?: 'Unit'),
                    'tanggal_perolehan' => $tanggal,
                    'bulan_perolehan' => $tanggal->month,
                    'tahun_perolehan' => $tanggal->year,
                    'harga_satuan' => $harga,
                    'nilai_perolehan_unit' => $harga,
                    'asal_perolehan' => $data['asal_perolehan'] ?? null,
                    'kondisi' => $data['kondisi'] ?? 'B',
                    'keterangan' => $data['keterangan'] ?? null,
                ]);
            }

            return $unit;
        });
    }
}
