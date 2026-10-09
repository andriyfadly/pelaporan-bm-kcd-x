<?php

namespace App\Services\DistribusiBhp;

use App\Models\DistribusiBhp\Settings;
use App\Support\DistribusiBhp\SumberDana;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Settings BHP: format nomor dokumen + preferensi PDF.
 *
 * Satu baris settings per sumber dana.
 */
class SettingsService
{
    /** @deprecated Gunakan Settings::DEFAULT_FORMAT */
    public const DEFAULT_FORMAT_NOMOR = '{urut:3}/BHP/{kode}/{bulan_romawi}/{tahun}';

    private const ROMAWI = [
        1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
        7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
    ];

    public function get(?string $sumberDana = null): Settings
    {
        $dana = $sumberDana ?? SumberDana::aktif() ?? SumberDana::BOS;

        return SumberDana::dengan($dana, function (): Settings {
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
        });
    }

    public function update(array $data, ?string $sumberDana = null): Settings
    {
        $dana = $sumberDana ?? SumberDana::aktif() ?? SumberDana::BOS;

        return SumberDana::dengan($dana, function () use ($data, $dana): Settings {
            $settings = $this->get($dana);
            $settings->update($data);

            return $settings->refresh();
        });
    }

    /** Format nomor dokumen per jenis (membaca kolom format_nomor_{jenis}). */
    public function getFormat(string $jenis, ?string $sumberDana = null): string
    {
        return SumberDana::dengan($sumberDana ?? SumberDana::BOS, function () use ($jenis): string {
            return $this->get()->getFormat($jenis);
        });
    }

    /**
     * Format nomor dokumen. Placeholder:
     * {urut:3} / {urut:N} counter padded, {kode} kode jenis, {sumber_dana},
     * {bulan_romawi}, {bulan}, {tahun}.
     */
    public function formatNomor(string $jenis, string $kodeJenis, string $sumberDana, int $tahun, int $bulan, int $urut): string
    {
        $format = $this->getFormat($jenis, $sumberDana);

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

    /** Preferensi PDF hardcode — ukuran/orientasi tetap per jenis dokumen. */
    public function pdf(?string $sumberDana = null): array
    {
        return [
            'paper' => 'a4',
            'orientation' => 'portrait',
        ];
    }
}
