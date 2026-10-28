<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Kalender hari kerja Indonesia.
 *
 * Hari kerja = bukan Sabtu/Minggu, bukan hari libur nasional, dan bukan hari
 * besar keagamaan. Cuti bersama sengaja TIDAK dihitung libur: sekolah tetap
 * masuk pada hari itu.
 *
 * Sumber libur bergerak diambil dari API tanggal merah (type=holiday sudah
 * mengecualikan cuti bersama). Bila API tidak dapat dihubungi, libur tetap
 * pada config/hari_libur.php dan aturan akhir pekan tetap berlaku — aplikasi
 * tidak boleh gagal hanya karena layanan eksternal mati.
 */
final class HariKerja
{
    private const CACHE_PREFIX = 'hari_libur_nasional:';

    /**
     * Peta `Y-m-d` => nama libur nasional untuk satu tahun.
     *
     * @return array<string,string>
     */
    public static function liburNasional(int $tahun): array
    {
        $kunci = self::CACHE_PREFIX.$tahun;
        $tersimpan = Cache::get($kunci);

        if (is_array($tersimpan)) {
            return $tersimpan;
        }

        if (! config('services.tanggal_merah.enabled', true)) {
            return [];
        }

        try {
            $hasil = self::ambilDariApi($tahun);
            Cache::put($kunci, $hasil, now()->addDay());

            return $hasil;
        } catch (Throwable $e) {
            Log::warning('Gagal mengambil hari libur nasional '.$tahun.': '.$e->getMessage());

            // Simpan kosong sebentar supaya tidak memanggil API di setiap
            // request saat layanan sedang bermasalah.
            Cache::put($kunci, [], now()->addMinutes(10));

            return [];
        }
    }

    /**
     * @return array<string,string>
     */
    private static function ambilDariApi(int $tahun): array
    {
        $response = Http::timeout((int) config('services.tanggal_merah.timeout', 3))
            ->acceptJson()
            ->get(rtrim((string) config('services.tanggal_merah.url'), '/').'/api/holidays', [
                'year' => $tahun,
                'type' => 'holiday',
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('HTTP '.$response->status());
        }

        $body = $response->json();

        if (($body['success'] ?? false) !== true) {
            throw new \RuntimeException('Respons API tidak menyatakan success.');
        }

        $peta = [];

        foreach ($body['data'] ?? [] as $item) {
            if (($item['type'] ?? null) !== 'holiday' || blank($item['date'] ?? null)) {
                continue;
            }

            $peta[(string) $item['date']] = (string) ($item['name'] ?? 'Hari Libur Nasional');
        }

        return $peta;
    }

    /** Libur tetap (tanggal sama setiap tahun) sebagai jaring terakhir. */
    private static function liburTetap(CarbonInterface $tanggal): bool
    {
        return array_key_exists($tanggal->format('m-d'), config('hari_libur.nasional_tetap', []));
    }

    public static function libur(CarbonInterface $tanggal): bool
    {
        if ($tanggal->isWeekend()) {
            return true;
        }

        if (self::liburTetap($tanggal)) {
            return true;
        }

        return array_key_exists($tanggal->toDateString(), self::liburNasional((int) $tanggal->year));
    }

    public static function kerja(CarbonInterface $tanggal): bool
    {
        return ! self::libur($tanggal);
    }

    /**
     * Hari kerja terakhir pada satu bulan: mundur dari tanggal akhir bulan
     * sampai menemukan hari yang bukan akhir pekan dan bukan libur nasional.
     */
    public static function kerjaTerakhir(int $tahun, int $bulan): Carbon
    {
        $tanggal = Carbon::create($tahun, $bulan, 1)->endOfMonth()->startOfDay();

        // Batas 31 hari hanya pengaman; satu bulan tidak mungkin seluruhnya libur.
        for ($mundur = 0; $mundur < 31; $mundur++) {
            if (self::kerja($tanggal)) {
                return $tanggal;
            }

            $tanggal = $tanggal->subDay();
        }

        return Carbon::create($tahun, $bulan, 1);
    }

    /** Alasan tanggal ini tidak bisa dipakai SO; null bila hari kerja. */
    public static function namaLibur(CarbonInterface $tanggal): ?string
    {
        if ($tanggal->isWeekend()) {
            return 'akhir pekan';
        }

        $tetap = config('hari_libur.nasional_tetap.'.$tanggal->format('m-d'));

        return $tetap ?? self::liburNasional((int) $tanggal->year)[$tanggal->toDateString()] ?? null;
    }
}
