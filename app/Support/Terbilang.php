<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Bilangan menjadi kata Bahasa Indonesia, dipakai narasi Berita Acara
 * ("tanggal Dua Puluh Lima bulan Februari tahun Dua Ribu Dua Puluh Enam").
 * Algoritme mengikuti acuan (bast_pdf.php), termasuk perilaku `0` → "Nol".
 */
final class Terbilang
{
    /** @var array<int, string> */
    private const HURUF = [
        '', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas',
    ];

    public static function angka(int $nilai): string
    {
        if ($nilai === 0) {
            return 'Nol';
        }

        return preg_replace('/\s+/', ' ', trim(self::penyebut(abs($nilai)))) ?? 'Nol';
    }

    /**
     * "Rabu tanggal Dua Puluh Lima bulan Februari tahun Dua Ribu Dua Puluh Enam".
     * Nama hari/bulan bergantung locale aplikasi (`id`).
     */
    public static function tanggal(CarbonInterface $tanggal): string
    {
        return sprintf(
            '%s tanggal %s bulan %s tahun %s',
            $tanggal->translatedFormat('l'),
            self::angka($tanggal->day),
            $tanggal->translatedFormat('F'),
            self::angka($tanggal->year),
        );
    }

    private static function penyebut(int $nilai): string
    {
        $temp = '';

        if ($nilai < 12) {
            $temp = ' '.self::HURUF[$nilai];
        } elseif ($nilai < 20) {
            $temp = self::penyebut($nilai - 10).' Belas';
        } elseif ($nilai < 100) {
            $temp = self::penyebut(intdiv($nilai, 10)).' Puluh'.self::penyebut($nilai % 10);
        } elseif ($nilai < 200) {
            $temp = ' Seratus'.self::penyebut($nilai - 100);
        } elseif ($nilai < 1000) {
            $temp = self::penyebut(intdiv($nilai, 100)).' Ratus'.self::penyebut($nilai % 100);
        } elseif ($nilai < 2000) {
            $temp = ' Seribu'.self::penyebut($nilai - 1000);
        } elseif ($nilai < 1000000) {
            $temp = self::penyebut(intdiv($nilai, 1000)).' Ribu'.self::penyebut($nilai % 1000);
        } elseif ($nilai < 1000000000) {
            $temp = self::penyebut(intdiv($nilai, 1000000)).' Juta'.self::penyebut($nilai % 1000000);
        } else {
            $temp = self::penyebut(intdiv($nilai, 1000000000)).' Miliar'.self::penyebut($nilai % 1000000000);
        }

        return $temp;
    }
}
