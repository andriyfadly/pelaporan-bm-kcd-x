<?php

namespace App\Support\DistribusiBhp;

use Carbon\CarbonImmutable;

/**
 * Periode laporan BHP: satu bulan penuh.
 *
 * Batas tanggal dipakai bersama oleh laporan stok, buku pengeluaran, kartu
 * persediaan, export Excel, dan snapshot dokumen. Satu sumber batas berarti
 * angka di layar, di Excel, dan di PDF tidak mungkin berbeda.
 */
final class Periode
{
    private function __construct(
        public readonly int $tahun,
        public readonly int $bulan,
        public readonly CarbonImmutable $mulai,
        public readonly CarbonImmutable $akhir,
    ) {}

    public static function bulan(int $tahun, int $bulan): self
    {
        $mulai = CarbonImmutable::create($tahun, $bulan, 1)->startOfDay();

        return new self($tahun, $bulan, $mulai, $mulai->endOfMonth()->startOfDay());
    }

    /** Default bulan berjalan bila tahun/bulan tidak dikirim. */
    public static function dari(?int $tahun, ?int $bulan): self
    {
        $sekarang = CarbonImmutable::now();

        return self::bulan($tahun ?? (int) $sekarang->year, $bulan ?? (int) $sekarang->month);
    }

    /** Batas atas sebagai string tanggal — dipakai query `whereDate(<=)`. */
    public function sampai(): string
    {
        return $this->akhir->toDateString();
    }

    /** Kode periode `Y-m`, dipakai nama berkas export. */
    public function kode(): string
    {
        return $this->mulai->format('Y-m');
    }

    public function label(): string
    {
        $bulan = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ][$this->bulan];

        return $bulan.' '.$this->tahun;
    }
}
