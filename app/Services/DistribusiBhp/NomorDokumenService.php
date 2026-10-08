<?php

namespace App\Services\DistribusiBhp;

use App\Models\DistribusiBhp\NomorSequence;
use Illuminate\Support\Facades\DB;

/**
 * Generator nomor dokumen BHP.
 *
 * Nomor bersifat atomic dan berurutan per (jenis, sumber dana, tahun, bulan),
 * memakai row lock agar aman terhadap request bersamaan.
 *
 * Format: "{urut:3}/{kode}/BHP/{sumber_dana}/{bulan_romawi}/{tahun}"
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

    private const ROMAWI = [
        1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
        7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
    ];

    public function generate(string $jenis, string $sumberDana, int $tahun, int $bulan): string
    {
        $kode = self::KODE[$jenis] ?? strtoupper($jenis);
        $romawi = self::ROMAWI[$bulan] ?? (string) $bulan;

        $sequence = DB::transaction(function () use ($jenis, $sumberDana, $tahun, $bulan) {
            $row = NomorSequence::where('jenis', $jenis)
                ->where('sumber_dana', $sumberDana)
                ->where('tahun', $tahun)
                ->where('bulan', $bulan)
                ->lockForUpdate()
                ->first();

            if (! $row) {
                $row = NomorSequence::create([
                    'jenis' => $jenis,
                    'sumber_dana' => $sumberDana,
                    'tahun' => $tahun,
                    'bulan' => $bulan,
                    'counter' => 0,
                ]);
                $row = NomorSequence::whereKey($row->id)->lockForUpdate()->firstOrFail();
            }

            $row->increment('counter');

            return $row->fresh()->counter;
        });

        return sprintf('%03d/BHP/%s/%s/%s/%d', $sequence, $kode, $sumberDana, $romawi, $tahun);
    }
}
