<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BmReferenceSeeder extends Seeder
{
    public function run(): void
    {
        foreach (range(1, 100) as $number) {
            $district = $number <= 50 ? 'Kuningan' : 'Cirebon';

            DB::table('kode_sekolah')->updateOrInsert(
                ['id_sekolah' => $number],
                [
                    'no_urut' => $number,
                    'nama_sekolah' => sprintf('SMAN %d %s', $number, $district),
                    'kota_kab' => $district,
                    'kode_sub_pengguna' => sprintf('KCDX-%03d', $number),
                    'kode_wilayah' => $number <= 50 ? '32.08' : '32.09',
                    'public_id' => sprintf('01900000-0000-7000-8000-%012d', $number),
                ],
            );

            foreach (range(1, 3) as $line) {
                DB::table('data_barang_acuan')->updateOrInsert(
                    ['id_sekolah' => (string) $number, 'bku' => sprintf('BM-%03d-%02d', $number, $line)],
                    [
                        'satuan_pendidikan' => sprintf('SMAN %d %s', $number, $district),
                        'npsn' => '202'.str_pad((string) $number, 5, '0', STR_PAD_LEFT),
                        'tanggal' => '2026-01-01',
                        'kodering' => '5.2.02.01.01',
                        'uraian' => sprintf('Acuan belanja modal sekolah %d komponen %d', $number, $line),
                        'nominal' => 500000,
                        'bulan' => 'Januari',
                        'public_id' => sprintf('01900000-0000-7000-8001-%012d', ($number * 10) + $line),
                    ],
                );
            }
        }
    }
}
