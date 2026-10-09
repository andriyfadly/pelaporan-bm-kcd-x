<?php

namespace App\Services\DistribusiBhp;

use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\KodeRekening;
use App\Support\DistribusiBhp\SumberDana;
use RuntimeException;

/**
 * Generator NUSP (Nomor Urut Spesifikasi Barang).
 *
 * Format: {kode_barang}.{urut:3} — mis. "1.1.7.01.01.001".
 * `kode_barang` diambil dari kode rekening terkait; urut = nomor terbesar yang
 * sudah ada untuk kode rekening itu + 1 (3 digit, zero-padded).
 *
 * NUSP disimpan sebagai `barang.kode_barang` sekaligus
 * `barang.spesifikasi_barang_nusp`.
 */
class NuspService
{
    public function generate(?KodeRekening $kodeRekening): string
    {
        if (! $kodeRekening) {
            throw new RuntimeException('Kode rekening tidak ditemukan.');
        }

        $kodeBarang = trim((string) $kodeRekening->kode_barang);

        if ($kodeBarang === '') {
            throw new RuntimeException(
                "Kode rekening \"{$kodeRekening->nama}\" (kode: {$kodeRekening->kode}) belum memiliki kode barang yang terdaftar."
            );
        }

        $prefix = $kodeBarang.'.';
        $panjangPrefix = strlen($prefix);

        // NUSP barang yang sudah ada untuk kode rekening ini (dalam sumber dana aktif).
        $nuspTersimpan = Barang::where('kode_rekening_id', $kodeRekening->id)
            ->whereNotNull('spesifikasi_barang_nusp')
            ->pluck('spesifikasi_barang_nusp');

        $urutTerbesar = 0;
        foreach ($nuspTersimpan as $nusp) {
            $nusp = (string) $nusp;
            if (! str_starts_with($nusp, $prefix)) {
                continue;
            }

            $sisa = substr($nusp, $panjangPrefix);
            if (preg_match('/^\d{3}$/', $sisa) === 1) {
                $urutTerbesar = max($urutTerbesar, (int) $sisa);
            }
        }

        return $prefix.str_pad((string) ($urutTerbesar + 1), 3, '0', STR_PAD_LEFT);
    }

    public function generateUntukSumberDana(?KodeRekening $kodeRekening, string $sumberDana): string
    {
        return SumberDana::dengan($sumberDana, fn (): string => $this->generate($kodeRekening));
    }
}
