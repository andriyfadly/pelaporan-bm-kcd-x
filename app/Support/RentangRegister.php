<?php

namespace App\Support;

use App\Models\PencatatanBm\InventarisUnit;
use Illuminate\Support\Collection;

/**
 * Rentang nomor register untuk ditampilkan: unit-level menyimpan nomor satu per
 * satu, acuan menyimpan satu string rentang ("001-003"). Nomor yang berurutan
 * digabung dengan "-", kelompok dipisah ", ".
 */
final class RentangRegister
{
    /**
     * @param  array<int, string>  $nomor  nomor register 3 digit
     */
    public static function dari(array $nomor): string
    {
        $angka = array_values(array_unique(array_map(
            static fn (string $n): int => (int) ltrim($n, '0'),
            array_filter($nomor, static fn (string $n): bool => trim($n) !== ''),
        )));

        if ($angka === []) {
            return '';
        }

        sort($angka);

        $bagian = [];
        $awal = $angka[0];
        $akhir = $awal;

        foreach (array_slice($angka, 1) as $nilai) {
            if ($nilai === $akhir + 1) {
                $akhir = $nilai;

                continue;
            }

            $bagian[] = self::potong($awal, $akhir);
            $awal = $nilai;
            $akhir = $nilai;
        }

        $bagian[] = self::potong($awal, $akhir);

        return implode(', ', $bagian);
    }

    private static function potong(int $awal, int $akhir): string
    {
        $format = static fn (int $n): string => str_pad((string) $n, 3, '0', STR_PAD_LEFT);

        return $awal === $akhir ? $format($awal) : $format($awal).'-'.$format($akhir);
    }

    /**
     * Potong kelompok unit menjadi run nomor register berurutan. Dipakai Buku
     * Penyaluran dan Pengembalian supaya batas barisnya identik.
     *
     * @param  Collection<int, InventarisUnit>  $kelompok  sudah urut nomor_register
     * @return array<int, Collection<int, InventarisUnit>>
     */
    public static function potongRun(Collection $kelompok): array
    {
        $run = [];
        $sekarang = collect();
        $sebelumnya = null;

        foreach ($kelompok as $unit) {
            $nomor = (int) $unit->nomor_register;

            if ($sebelumnya !== null && $nomor !== $sebelumnya + 1) {
                $run[] = $sekarang;
                $sekarang = collect();
            }

            $sekarang->push($unit);
            $sebelumnya = $nomor;
        }

        if ($sekarang->isNotEmpty()) {
            $run[] = $sekarang;
        }

        return $run;
    }
}
