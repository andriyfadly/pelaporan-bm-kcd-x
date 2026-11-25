<?php

namespace App\Support;

/**
 * Nama orang pada dokumen resmi: nama utama huruf besar, gelar tetap
 * Title Case ("muhammad andriy, s.kom" → "MUHAMMAD ANDRIY, S.Kom").
 */
final class NamaGelar
{
    public static function format(?string $nama): string
    {
        $nama = trim((string) $nama);

        if ($nama === '' || $nama === '-') {
            return '-';
        }

        $bagian = explode(',', $nama);
        $utama = mb_strtoupper(trim(array_shift($bagian)));

        $gelar = array_values(array_filter(array_map(
            static fn (string $g): string => ucwords(mb_strtolower(trim($g)), ' .'),
            $bagian,
        ), static fn (string $g): bool => $g !== ''));

        return $gelar === [] ? $utama : $utama.', '.implode(', ', $gelar);
    }
}
