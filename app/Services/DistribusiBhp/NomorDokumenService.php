<?php

namespace App\Services\DistribusiBhp;

use App\Models\DistribusiBhp\NomorSequence;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Generator nomor dokumen BHP.
 *
 * Nomor bersifat atomic dan berurutan per (jenis, sumber dana, tahun, bulan),
 * memakai row lock agar aman terhadap request bersamaan.
 *
 * Format diambil dari settings BHP (bhp_settings.format_nomor), default:
 * "{urut:3}/{kode}/BHP/{sumber_dana}/{bulan_romawi}/{tahun}"
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
        $kode = self::KODE[$jenis] ?? strtoupper($jenis);

        $sequence = DB::transaction(function () use ($jenis, $sumberDana, $tahun, $bulan) {
            $row = NomorSequence::where('jenis', $jenis)
                ->where('sumber_dana', $sumberDana)
                ->where('tahun', $tahun)
                ->where('bulan', $bulan)
                ->lockForUpdate()
                ->first();

            if (! $row) {
                // Dua request bersamaan bisa sama-sama melihat "belum ada row".
                // unique constraint (jenis, sumber_dana, tahun, bulan) menolak
                // duplikat; pemenang race adalah yang insert berhasil, yang kalah
                // retry dan lock row milik pemenang.
                try {
                    $row = NomorSequence::create([
                        'jenis' => $jenis,
                        'sumber_dana' => $sumberDana,
                        'tahun' => $tahun,
                        'bulan' => $bulan,
                        'counter' => 0,
                    ]);
                } catch (UniqueConstraintViolationException) {
                    $row = NomorSequence::where('jenis', $jenis)
                        ->where('sumber_dana', $sumberDana)
                        ->where('tahun', $tahun)
                        ->where('bulan', $bulan)
                        ->lockForUpdate()
                        ->firstOrFail();
                }

                $row = NomorSequence::whereKey($row->id)->lockForUpdate()->firstOrFail();
            }

            $row->increment('counter');

            return $row->fresh()->counter;
        });

        return $this->settings->formatNomor($jenis, $kode, $sumberDana, $tahun, $bulan, $sequence);
    }
}
