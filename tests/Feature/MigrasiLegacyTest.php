<?php

namespace Tests\Feature;

use App\Models\Master\Sekolah;
use App\Models\PelaporanBm\Acuan;
use App\Models\PelaporanBm\KunciLaporan;
use App\Models\PelaporanBm\Realisasi;
use App\Models\PelaporanBm\Spj;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MigrasiLegacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrasi_data_lama_counts(): void
    {
        $this->artisan('app:migrasi-data-lama')->assertSuccessful();

        $this->assertSame(77, Sekolah::count());
        $this->assertSame(717, Acuan::count());
        $this->assertSame(490, Spj::count());
        $this->assertSame(42, Realisasi::count());
        $this->assertSame(13, KunciLaporan::count());
        $this->assertSame(78, User::count());

        // status laporan terbawa
        $this->assertSame(13, KunciLaporan::where('status_kirim', 'disetujui')->count());
        $this->assertSame(13, KunciLaporan::whereNotNull('dikirim_pada')->count());

        // relasi spj->sekolah & realisasi->spj utuh
        $this->assertSame(0, Spj::whereNull('sekolah_id')->count());
        $this->assertSame(0, Realisasi::whereNull('spj_id')->count());
        $this->assertSame(0, Realisasi::whereNull('sekolah_id')->count());

        // role user legacy
        $this->assertSame(1, User::role('admin_kcd')->count());
        $this->assertSame(77, User::role('operator_sekolah')->count());
        $this->assertSame(78, User::whereNull('password_changed_at')->count());
    }
}
