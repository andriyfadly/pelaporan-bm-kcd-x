<?php

namespace App\Services\DistribusiBhp;

use App\Models\DistribusiBhp\Settings;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Settings BHP: format nomor dokumen + preferensi PDF.
 *
 * Satu baris settings (singleton). Nilai default dari migration dipakai bila
 * baris belum ada.
 */
class SettingsService
{
    public const DEFAULT_FORMAT_NOMOR = '{urut:3}/BHP/{kode}/{sumber_dana}/{bulan_romawi}/{tahun}';

    private const ROMAWI = [
        1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
        7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
    ];

    public function get(): Settings
    {
        // # ponytail: singleton race-safe tanpa transaksi panjang — bila dua
        // request concurrent sama-sama insert, yang kalah menangkap unique
        // violation (partial index bhp_settings_singleton_unique) lalu reload.
        // Query ulang di luar transaction yang aborted oleh pgsql.
        $settings = Settings::query()->first();
        if ($settings !== null) {
            return $settings;
        }

        try {
            Settings::create();

            return Settings::query()->firstOrFail();
        } catch (UniqueConstraintViolationException) {
            return Settings::query()->firstOrFail();
        }
    }

    public function update(array $data): Settings
    {
        $settings = $this->get();
        $settings->update($data);

        return $settings->refresh();
    }

    /**
     * Format nomor dokumen. Placeholder:
     * {urut:3} / {urut:N} counter padded, {kode} kode jenis, {sumber_dana},
     * {bulan_romawi}, {bulan}, {tahun}.
     */
    public function formatNomor(string $jenis, string $kodeJenis, string $sumberDana, int $tahun, int $bulan, int $urut): string
    {
        $format = $this->get()->format_nomor ?: self::DEFAULT_FORMAT_NOMOR;

        return str_replace(
            ['{urut:1}', '{urut:2}', '{urut:3}', '{urut:4}', '{kode}', '{sumber_dana}', '{bulan_romawi}', '{bulan}', '{tahun}'],
            [
                (string) $urut,
                sprintf('%02d', $urut),
                sprintf('%03d', $urut),
                sprintf('%04d', $urut),
                $kodeJenis,
                $sumberDana,
                self::ROMAWI[$bulan] ?? (string) $bulan,
                sprintf('%02d', $bulan),
                (string) $tahun,
            ],
            $format,
        );
    }

    /** Preferensi PDF untuk DomPDF. */
    public function pdf(): array
    {
        $settings = $this->get();

        return [
            'paper' => $settings->paper_size ?: 'a4',
            'orientation' => $settings->orientation ?: 'portrait',
        ];
    }
}
