<?php

namespace Tests\Unit;

use App\Support\RentangRegister;
use App\Support\Terbilang;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TerbilangTest extends TestCase
{
    public function test_angka_dasar(): void
    {
        $this->assertSame('Nol', Terbilang::angka(0));
        $this->assertSame('Satu', Terbilang::angka(1));
        $this->assertSame('Sebelas', Terbilang::angka(11));
        $this->assertSame('Dua Puluh Lima', Terbilang::angka(25));
        $this->assertSame('Seratus', Terbilang::angka(100));
        $this->assertSame('Dua Ribu Dua Puluh Enam', Terbilang::angka(2026));
    }

    public function test_tanggal_menyebut_hari_bulan_dan_tahun(): void
    {
        Carbon::setLocale('id');

        $this->assertSame(
            'Rabu tanggal Dua Puluh Lima bulan Februari tahun Dua Ribu Dua Puluh Enam',
            Terbilang::tanggal(Carbon::parse('2026-02-25')),
        );
    }

    public function test_rentang_register_menggabung_nomor_berurutan(): void
    {
        $this->assertSame('001-003, 007', RentangRegister::dari(['001', '002', '003', '007']));
        $this->assertSame('003', RentangRegister::dari(['003']));
        $this->assertSame('', RentangRegister::dari([]));
        $this->assertSame('001-002, 005-006', RentangRegister::dari(['005', '006', '002', '001']));
    }
}
