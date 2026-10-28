<?php

namespace App\Support\DistribusiBhp;

/**
 * Kode barang (klasifikasi aset) untuk kode rekening BHP.
 *
 * Lima nilai ini mencerminkan kelompok Barang Pakai Habis pada sistem
 * manajemen aset (bukan FK ke tabel manapun). Dipakai tiga tempat sekaligus —
 * validasi `store`/`batch-assign`, dropdown kode rekening, dan seeder — supaya
 * daftarnya tidak pernah berbeda.
 */
final class KodeBarang
{
    /** @var list<array{kode:string,uraian:string}> */
    public const OPSI = [
        ['kode' => '1.1.7.01.01', 'uraian' => 'Bahan'],
        ['kode' => '1.1.7.01.02', 'uraian' => 'Suku Cadang'],
        ['kode' => '1.1.7.01.03', 'uraian' => 'Alat/Bahan Untuk Kegiatan Kantor'],
        ['kode' => '1.1.7.01.04', 'uraian' => 'Obat-Obatan'],
        ['kode' => '1.1.7.01.07', 'uraian' => 'Naturan dan Pakan'],
    ];

    /** @return list<string> */
    public static function kode(): array
    {
        return array_column(self::OPSI, 'kode');
    }

    public static function uraian(string $kode): ?string
    {
        foreach (self::OPSI as $opsi) {
            if ($opsi['kode'] === $kode) {
                return $opsi['uraian'];
            }
        }

        return null;
    }

    /**
     * Tebak kode barang dari nama kode rekening.
     *
     * Dipakai seeder untuk mengisi kode rekening bawaan; empat kelompok khusus
     * dikenali dari kata kuncinya, sisanya jatuh ke "Bahan" karena itulah
     * kelompok BHP yang paling umum.
     *
     * @return array{kode:string,uraian:string}
     */
    public static function untukNamaRekening(string $nama): array
    {
        $kode = match (true) {
            str_contains($nama, 'Suku Cadang'), str_contains($nama, 'Komponen') => '1.1.7.01.02',
            str_contains($nama, 'Obat') => '1.1.7.01.04',
            str_contains($nama, 'Natura'), str_contains($nama, 'Pakan') => '1.1.7.01.07',
            // Beberapa baris sumber menulis "Kanto" tanpa 'r'.
            (bool) preg_match('/Kegiatan Kanto/i', $nama) => '1.1.7.01.03',
            default => '1.1.7.01.01',
        };

        return ['kode' => $kode, 'uraian' => (string) self::uraian($kode)];
    }
}
