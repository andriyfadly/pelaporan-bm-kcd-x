<?php

namespace Tests\Feature;

use App\Models\Master\Sekolah;
use App\Models\PelaporanBm\KunciLaporan;
use App\Models\PelaporanBm\Realisasi;
use App\Models\PencatatanBm\InventarisRegisterSequence;
use App\Models\PencatatanBm\InventarisUnit;
use App\Services\PencatatanBm\SinkronisasiInventarisService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penjaga Review Focus #1: dua proses sinkronisasi tidak boleh mendapat
 * register yang sama. Dijaga oleh unique(sekolah_id, kode_barang,
 * nomor_register) + unique sequence + lockForUpdate pada baris sequence.
 */
class InventarisRegisterConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private function sekolah(string $nama = 'SMA Negeri 1'): Sekolah
    {
        return activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => $nama,
            'kota_kab' => 'Kota Bandung',
            'npsn' => '20246369',
        ]));
    }

    private function realisasi(Sekolah $sekolah, float $volume, string $kode = '5.2.02.01.004'): Realisasi
    {
        return activity()->withoutLogging(fn () => Realisasi::create([
            'sekolah_id' => $sekolah->id,
            'bulan_realisasi' => 3,
            'tahun' => 2026,
            'kode_barang' => $kode,
            'nama_barang' => 'Laptop',
            'volume' => $volume,
            'harga_satuan' => 9000000,
            'nilai_perolehan' => $volume * 9000000,
        ]));
    }

    private function approve(Sekolah $sekolah): void
    {
        KunciLaporan::create([
            'sekolah_id' => $sekolah->id,
            'bulan' => 3,
            'tahun' => 2026,
            'status_kirim' => 'disetujui',
        ]);
    }

    public function test_dua_realisasi_kode_sama_tidak_pernah_berbagi_register(): void
    {
        $sekolah = $this->sekolah();
        $this->approve($sekolah);

        $service = app(SinkronisasiInventarisService::class);
        $service->syncRealisasi($this->realisasi($sekolah, 2));
        $service->syncRealisasi($this->realisasi($sekolah, 2));

        $registers = InventarisUnit::orderBy('nomor_register')->pluck('nomor_register')->all();

        $this->assertSame(['001', '002', '003', '004'], $registers);
        $this->assertSame(4, count(array_unique($registers)), 'Register tidak boleh dipakai dua kali.');
    }

    public function test_register_ganda_ditolak_constraint_database(): void
    {
        $sekolah = $this->sekolah();
        $this->approve($sekolah);

        app(SinkronisasiInventarisService::class)->syncRealisasi($this->realisasi($sekolah, 1));

        $this->expectException(UniqueConstraintViolationException::class);

        InventarisUnit::create([
            'sekolah_id' => $sekolah->id,
            'realisasi_id' => $this->realisasi($sekolah, 1)->id,
            'unit_index' => 1,
            'kode_barang' => '5.2.02.01.004',
            'nomor_register' => '001',
            'harga_satuan' => 0,
            'nilai_perolehan_unit' => 0,
        ]);
    }

    public function test_sequence_unik_per_sekolah_dan_kode_barang(): void
    {
        $sekolah = $this->sekolah();

        InventarisRegisterSequence::create([
            'sekolah_id' => $sekolah->id,
            'kode_barang' => '5.2.02.01.004',
            'next_number' => 5,
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        InventarisRegisterSequence::create([
            'sekolah_id' => $sekolah->id,
            'kode_barang' => '5.2.02.01.004',
            'next_number' => 1,
        ]);
    }

    public function test_sequence_selalu_maju_dan_tidak_mundur(): void
    {
        $sekolah = $this->sekolah();
        $this->approve($sekolah);

        $service = app(SinkronisasiInventarisService::class);
        $service->syncRealisasi($this->realisasi($sekolah, 2));
        $service->syncRealisasi($this->realisasi($sekolah, 1));
        $service->syncRealisasi($this->realisasi($sekolah, 2));
        $service->syncRealisasi($this->realisasi($sekolah, 1));

        $sequence = InventarisRegisterSequence::where('sekolah_id', $sekolah->id)->firstOrFail();

        $this->assertSame(7, $sequence->next_number, 'Nomor berikutnya = jumlah unit terbit + 1.');
        $this->assertSame(6, InventarisUnit::where('sekolah_id', $sekolah->id)->count());
        $this->assertSame(
            ['001', '002', '003', '004', '005', '006'],
            InventarisUnit::orderBy('nomor_register')->pluck('nomor_register')->all()
        );
    }
}
