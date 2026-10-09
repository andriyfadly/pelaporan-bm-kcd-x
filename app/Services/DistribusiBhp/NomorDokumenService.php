<?php

namespace App\Services\DistribusiBhp;

use App\Models\DistribusiBhp\NomorSequence;
use App\Support\DistribusiBhp\SumberDana;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Generator nomor dokumen BHP.
 *
 * Nomor bersifat atomic dan berurutan per (sumber dana, jenis, tahun, bulan).
 * Tabel sequence sudah terpisah per sumber dana (`bhp_bos_nomor_sequence` /
 * `bhp_bopd_nomor_sequence`), memakai row lock agar aman terhadap request
 * bersamaan.
 *
 * Format diambil dari settings BHP per sumber dana, default:
 * "{urut:3}/{kode}/BHP/{bulan_romawi}/{tahun}"
 */
class NomorDokumenService
{
    /** @var array<string,string> */
    private const KODE = [
        'nota_permintaan' => 'NPB',
        'surat_permintaan' => 'SPB',
        'surat_penyaluran' => 'SPPB',
        'bast' => 'BAST',
        'bap' => 'BAP',
        'stok_opname' => 'SO',
        'buku_pengeluaran' => 'BPB',
        'buku_penyaluran' => 'BP',
    ];

    public function __construct(private readonly SettingsService $settings) {}

    public function generate(string $jenis, string $sumberDana, int $tahun, int $bulan): string
    {
        $sumberDana = SumberDana::normalisasi($sumberDana);
        $kode = self::KODE[$jenis] ?? strtoupper($jenis);

        $sequence = SumberDana::dengan($sumberDana, fn () => DB::transaction(function () use ($jenis, $tahun, $bulan) {
            $cari = fn () => NomorSequence::where('jenis', $jenis)
                ->where('tahun', $tahun)
                ->where('bulan', $bulan);

            $row = $cari()->lockForUpdate()->first();

            if (! $row) {
                // Dua request bersamaan bisa sama-sama melihat "belum ada row".
                // unique constraint (jenis, tahun, bulan) menolak duplikat;
                // pemenang race adalah yang insert berhasil, yang kalah retry
                // dan lock row milik pemenang.
                try {
                    $row = NomorSequence::create([
                        'jenis' => $jenis,
                        'tahun' => $tahun,
                        'bulan' => $bulan,
                        'counter' => 0,
                    ]);
                } catch (UniqueConstraintViolationException) {
                    $row = $cari()->lockForUpdate()->firstOrFail();
                }

                $row = NomorSequence::whereKey($row->id)->lockForUpdate()->firstOrFail();
            }

            $row->increment('counter');

            return $row->fresh()->counter;
        }));

        return $this->settings->formatNomor($jenis, $kode, $sumberDana, $tahun, $bulan, $sequence);
    }
}
