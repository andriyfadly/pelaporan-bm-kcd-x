<?php

namespace Tests\Unit;

use App\Models\Master\Sekolah;
use App\Models\PelaporanBm\Acuan;
use App\Models\PelaporanBm\KunciLaporan;
use App\Models\PelaporanBm\Realisasi;
use App\Models\PelaporanBm\Spj;
use App\Models\User;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelRelationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);
    }

    public function test_sekolah_has_all_relations(): void
    {
        $sekolah = Sekolah::create(['nama_sekolah' => 'SMA 1', 'kota_kab' => 'Bandung']);
        $user = User::create(['name' => 'Op', 'username' => 'op_rel', 'password' => bcrypt('x'), 'sekolah_id' => $sekolah->id]);
        $acuan = Acuan::create(['sekolah_id' => $sekolah->id, 'uraian' => 'A']);
        $spj = Spj::create(['sekolah_id' => $sekolah->id, 'acuan_id' => $acuan->id, 'no_spk' => 'SPK/1', 'kode_barang' => '1.1', 'nama_barang' => 'B', 'jenis_aset' => 'J', 'volume' => 1, 'harga_satuan' => 100, 'nilai_perolehan' => 100]);
        $realisasi = Realisasi::create(['sekolah_id' => $sekolah->id, 'acuan_id' => $acuan->id, 'spj_id' => $spj->id, 'kode_barang' => '1.1', 'volume' => 1, 'harga_satuan' => 100, 'nilai_perolehan' => 100]);
        $kunci = KunciLaporan::create(['sekolah_id' => $sekolah->id, 'bulan' => '01', 'tahun' => 2026, 'status_kunci' => '1', 'dikunci_oleh' => $user->id]);

        $sekolah = $sekolah->fresh();
        $this->assertTrue($sekolah->users->contains($user));
        $this->assertTrue($sekolah->acuan->contains($acuan));
        $this->assertTrue($sekolah->spj->contains($spj));
        $this->assertTrue($sekolah->realisasi->contains($realisasi));
        $this->assertTrue($sekolah->kunciLaporan->contains($kunci));

        $this->assertTrue($acuan->sekolah->is($sekolah));
        $this->assertTrue($acuan->spj->contains($spj));
        $this->assertTrue($acuan->realisasi->contains($realisasi));

        $this->assertTrue($spj->sekolah->is($sekolah));
        $this->assertTrue($spj->acuan->is($acuan));
        $this->assertTrue($spj->realisasi->contains($realisasi));

        $this->assertTrue($realisasi->spj->is($spj));
        $this->assertTrue($realisasi->sekolah->is($sekolah));
        $this->assertTrue($realisasi->acuan->is($acuan));

        $kunci = $kunci->fresh();
        $this->assertTrue($kunci->sekolah->is($sekolah));
        $this->assertTrue($kunci->pengunci->is($user));
        $this->assertTrue($kunci->status_kunci);
        $this->assertInstanceOf(\DateTimeInterface::class, $kunci->dikunci_pada ?? now());
    }
}
