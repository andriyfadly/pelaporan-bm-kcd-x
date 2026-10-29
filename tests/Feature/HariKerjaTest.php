<?php

namespace Tests\Feature;

use App\Support\HariKerja;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Kalender hari kerja: Sabtu/Minggu libur, libur nasional libur, cuti bersama
 * tetap hari kerja. API dipalsukan supaya test tidak bergantung jaringan.
 */
class HariKerjaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Nonaktif secara global agar test lain tidak memanggil jaringan;
        // test ini menyalakannya kembali dan memalsukan respons API.
        config(['services.tanggal_merah.enabled' => true]);
        Cache::flush();
    }

    /** @param list<array{0: string, 1: string, 2?: string}> $items */
    private function fakeApi(array $items): void
    {
        Http::fake([
            'tanggalmerah.upset.dev/*' => Http::response([
                'success' => true,
                'data' => array_map(fn (array $item): array => [
                    'date' => $item[0],
                    'name' => $item[1],
                    'type' => $item[2] ?? 'holiday',
                ], $items),
                'meta' => ['year' => 2026, 'count' => count($items)],
            ]),
        ]);
    }

    private function tgl(string $tanggal): Carbon
    {
        return Carbon::parse($tanggal);
    }

    public function test_akhir_pekan_dihitung_libur_tanpa_memanggil_api(): void
    {
        Http::preventStrayRequests();
        Cache::flush();

        $this->assertTrue(HariKerja::libur($this->tgl('2026-01-31')), 'Sabtu harus libur.');
        $this->assertTrue(HariKerja::libur($this->tgl('2026-05-31')), 'Minggu harus libur.');
        $this->assertFalse(HariKerja::libur($this->tgl('2026-06-30')), 'Selasa harus hari kerja.');
    }

    public function test_libur_nasional_dari_api_dihitung_libur(): void
    {
        Cache::flush();
        $this->fakeApi([['2026-08-17', 'Hari Kemerdekaan Republik Indonesia']]);

        $this->assertTrue(HariKerja::libur($this->tgl('2026-08-17')));
        $this->assertSame(
            'Hari Kemerdekaan Republik Indonesia',
            HariKerja::namaLibur($this->tgl('2026-08-17')),
        );
    }

    public function test_cuti_bersama_tidak_dihitung_libur(): void
    {
        Cache::flush();
        $this->fakeApi([
            ['2026-03-19', 'Hari Suci Nyepi', 'holiday'],
            ['2026-03-18', 'Cuti Bersama Nyepi', 'leave'],
        ]);

        $this->assertTrue(HariKerja::libur($this->tgl('2026-03-19')), 'Libur nasional tetap libur.');
        $this->assertFalse(HariKerja::libur($this->tgl('2026-03-18')), 'Cuti bersama tetap hari kerja.');
    }

    public function test_api_hanya_diminta_untuk_type_holiday(): void
    {
        Cache::flush();
        $this->fakeApi([]);

        HariKerja::liburNasional(2026);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'year=2026')
            && str_contains($request->url(), 'type=holiday'));
    }

    public function test_libur_tetap_berlaku_saat_api_mati_dan_belum_ada_cache(): void
    {
        Cache::flush();
        Http::fake(['tanggalmerah.upset.dev/*' => Http::failedConnection()]);

        $this->assertTrue(HariKerja::libur($this->tgl('2026-01-01')), 'Tahun baru tetap libur.');
        $this->assertTrue(HariKerja::libur($this->tgl('2026-08-17')), 'Kemerdekaan tetap libur.');
        $this->assertTrue(HariKerja::libur($this->tgl('2026-01-31')), 'Akhir pekan tetap libur.');
        $this->assertFalse(HariKerja::libur($this->tgl('2026-01-16')), 'Libur bergerak tidak diketahui tanpa API.');
    }

    public function test_hasil_api_di_cache_sehari(): void
    {
        Cache::flush();
        $this->fakeApi([['2026-05-14', 'Kenaikan Yesus Kristus']]);

        HariKerja::liburNasional(2026);
        HariKerja::liburNasional(2026);

        Http::assertSentCount(1);
    }

    public function test_kerja_terakhir_bulan_semester_satu_2026(): void
    {
        Cache::flush();
        $this->fakeApi([
            ['2026-01-01', 'Tahun Baru'],
            ['2026-05-01', 'Hari Buruh'],
        ]);

        $harapan = [
            1 => '2026-01-30',
            2 => '2026-02-27',
            3 => '2026-03-31',
            4 => '2026-04-30',
            5 => '2026-05-29',
            6 => '2026-06-30',
        ];

        foreach ($harapan as $bulan => $tanggal) {
            $this->assertSame(
                $tanggal,
                HariKerja::kerjaTerakhir(2026, $bulan)->toDateString(),
                "Hari kerja terakhir bulan {$bulan} salah.",
            );
        }
    }

    public function test_kerja_terakhir_mundur_melewati_libur_nasional(): void
    {
        // 31 Agustus 2026 Senin; bila tanggal itu libur nasional, hari kerja
        // terakhir mundur melewati Minggu dan Sabtu ke Jumat 28 Agustus.
        Cache::flush();
        $this->fakeApi([['2026-08-31', 'Libur Nasional Uji']]);

        $this->assertSame('2026-08-28', HariKerja::kerjaTerakhir(2026, 8)->toDateString());
    }

    public function test_api_gagal_tidak_melempar_error(): void
    {
        Cache::flush();
        Http::fake(['tanggalmerah.upset.dev/*' => Http::response('Payment required', 402)]);

        $this->assertSame([], HariKerja::liburNasional(2026));
        $this->assertFalse(HariKerja::libur($this->tgl('2026-06-30')));
    }
}
