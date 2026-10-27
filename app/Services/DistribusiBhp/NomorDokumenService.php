<?php

namespace App\Services\DistribusiBhp;

use App\Models\DistribusiBhp\NomorSequence;
use App\Support\DistribusiBhp\SumberDana;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Generator nomor dokumen BHP.
 *
 * Nomor bersifat atomic dan berurutan per (sumber dana, jenis, tahun, bulan).
 * Tabel sequence sudah terpisah per sumber dana (`bhp_bos_nomor_sequence` /
 * `bhp_bopd_nomor_sequence`), memakai row lock agar aman terhadap request
 * bersamaan.
 *
 * Format diambil dari settings BHP per jenis dokumen, fallback ke
 * format_nomor umum (default: "{urut:3}/BHP/{kode}/{bulan_romawi}/{tahun}").
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

            NomorSequence::query()->insertOrIgnore([
                'id' => (string) Str::uuid(),
                'jenis' => $jenis,
                'tahun' => $tahun,
                'bulan' => $bulan,
                'counter' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $row = $cari()->lockForUpdate()->firstOrFail();

            $row->increment('counter');

            return $row->fresh()->counter;
        }, 3));

        return $this->settings->formatNomor($jenis, $kode, $sumberDana, $tahun, $bulan, $sequence);
    }
}
