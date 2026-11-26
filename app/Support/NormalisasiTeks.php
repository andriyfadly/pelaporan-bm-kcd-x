<?php

namespace App\Support;

/**
 * Normalisasi teks master yang di aplikasi acuan dilakukan saat menyimpan:
 * nama ruang/pegawai dirapikan kapitalisasinya, gelar dibetulkan ejaannya, dan
 * NIP berupa tanda hubung dianggap kosong.
 */
final class NormalisasiTeks
{
    /**
     * Pasangan gelar acuan (`data_pegawai.php:formatNamaGelar`), sudah terurut
     * dari yang terpanjang supaya `S.pd` tidak dipotong oleh `S.p`.
     *
     * @var array<string, string>
     */
    private const GELAR = [
        'S.pd' => 'S.Pd',
        'M.pd' => 'M.Pd',
        'S.kom' => 'S.Kom',
        'S.st' => 'S.ST',
        'M.si' => 'M.Si',
        'S.si' => 'S.Si',
        'S.sos' => 'S.Sos',
        'S.ag' => 'S.Ag',
        'M.ag' => 'M.Ag',
        'Ph.d' => 'Ph.D',
        'Hj.' => 'Hj.',
        'H.' => 'H.',
        'S.t' => 'S.T',
        'M.t' => 'M.T',
        'S.h' => 'S.H',
        'M.h' => 'M.H',
        'S.e' => 'S.E',
        'M.m' => 'M.M',
    ];

    public static function namaRuang(string $nama): string
    {
        return self::judul($nama);
    }

    public static function namaPegawai(string $nama): string
    {
        return str_ireplace(array_keys(self::GELAR), array_values(self::GELAR), self::judul($nama));
    }

    public static function nip(?string $nip): ?string
    {
        $bersih = trim((string) $nip);

        return in_array($bersih, ['', '-', '–', '—'], true) ? null : $bersih;
    }

    private static function judul(string $teks): string
    {
        return mb_convert_case(mb_strtolower(trim($teks)), MB_CASE_TITLE);
    }
}
