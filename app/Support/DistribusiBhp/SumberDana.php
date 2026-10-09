<?php

namespace App\Support\DistribusiBhp;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Sumber kebenaran tunggal untuk pemisahan tabel Distribusi BHP per sumber dana.
 *
 * Sejak audit lanjutan, tabel transaksi & master barang dipisah fisik per sumber
 * dana (`bhp_bos_*` / `bhp_bopd_*`) sehingga sumber dana implisit dari nama
 * tabel — tidak ada lagi kolom `sumber_dana`. Hanya `bhp_penyedia` dan
 * `bhp_divisi` yang tetap satu tabel bersama lintas sumber dana.
 *
 * Helper ini memetakan suffix logis (mis. `penerimaan`) menjadi nama tabel fisik
 * sesuai sumber dana aktif, dan dipakai oleh model (via trait
 * {@see HasSumberDanaTable}) maupun query builder mentah.
 */
final class SumberDana
{
    public const BOS = 'BOS';

    public const BOPD = 'BOPD';

    /** @var list<string> */
    public const SEMUA = [self::BOS, self::BOPD];

    /** Tabel yang TIDAK dipisah per sumber dana. */
    private const TABEL_BERSAMA = [
        'penyedia',
        'divisi',
    ];

    /**
     * Sumber dana aktif secara default pada request ini. Dipakai model saat
     * tabel di-resolve tanpa sumber dana eksplisit.
     */
    private static ?string $aktif = null;

    public static function normalisasi(?string $sumberDana): string
    {
        $dana = strtoupper(trim((string) $sumberDana));

        return $dana === self::BOPD ? self::BOPD : self::BOS;
    }

    public static function valid(?string $sumberDana): bool
    {
        return in_array(strtoupper(trim((string) $sumberDana)), self::SEMUA, true);
    }

    public static function aktif(): ?string
    {
        return self::$aktif;
    }

    public static function setAktif(?string $sumberDana): void
    {
        self::$aktif = $sumberDana === null ? null : self::normalisasi($sumberDana);
    }

    /**
     * Jalankan callback dengan sumber dana aktif tertentu, lalu kembalikan
     * nilai sebelumnya (aman untuk nesting).
     */
    public static function dengan(string $sumberDana, \Closure $callback): mixed
    {
        $sebelumnya = self::$aktif;
        self::setAktif($sumberDana);

        try {
            return $callback();
        } finally {
            self::$aktif = $sebelumnya;
        }
    }

    public static function bersamakah(string $suffix): bool
    {
        return in_array($suffix, self::TABEL_BERSAMA, true);
    }

    /**
     * Nama tabel fisik untuk suffix logis pada sumber dana tertentu.
     * Contoh: tabel('penerimaan', 'BOS') => 'bhp_bos_penerimaan'.
     */
    public static function tabel(string $suffix, ?string $sumberDana = null): string
    {
        if (self::bersamakah($suffix)) {
            return 'bhp_'.$suffix;
        }

        return 'bhp_'.strtolower(self::normalisasi($sumberDana ?? self::$aktif)).'_'.$suffix;
    }

    /**
     * Daftar semua nama tabel fisik untuk suffix logis (satu per sumber dana).
     *
     * @return list<string>
     */
    public static function semuaTabel(string $suffix): array
    {
        if (self::bersamakah($suffix)) {
            return ['bhp_'.$suffix];
        }

        return [
            self::tabel($suffix, self::BOS),
            self::tabel($suffix, self::BOPD),
        ];
    }

    /**
     * Aturan validasi `exists` yang menunjuk tabel fisik sesuai sumber dana,
     * sehingga `barang_id`/`kode_rekening_id` asing dari dana lain ikut ditolak.
     */
    public static function existsRule(string $suffix, ?string $sumberDana = null): Exists
    {
        return Rule::exists(self::tabel($suffix, $sumberDana), 'id');
    }

    /** Suffix tabel yang dipisah per sumber dana (kecuali penyedia & divisi). */
    public static function suffixDipisah(): array
    {
        return [
            'kode_rekening',
            'barang',
            'harga_barang',
            'penerimaan',
            'penerimaan_detail',
            'pengajuan',
            'pengajuan_detail',
            'nomor_sequence',
            'dokumen',
            'batch_dokumen',
            'settings',
        ];
    }
}
