<?php

namespace Tests\Feature;

use App\Models\Master\Pegawai;
use App\Models\Master\Sekolah;
use App\Models\PencatatanBm\Dokumen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Narasi dokumen bernarasi harus sama dengan aplikasi acuan
 * (bast_pdf.php, fakta_pdf.php, cetak_pengembalian.php, surat_permohonan_kembali.php).
 */
class InventarisDokumenNarasiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $sekolah = Sekolah::create([
            'nama_sekolah' => 'SMA Narasi',
            'kota_kab' => 'Kuningan',
            'alamat' => 'Jl. Narasi 1',
            'email' => 'narasi@sekolah.sch.id',
            'kode_pos' => '45511',
        ]);

        $kepala = Pegawai::create(['sekolah_id' => $sekolah->id, 'nama_lengkap' => 'Ipan Supandi', 'nip' => '197501012000031001']);
        $wakasek = Pegawai::create(['sekolah_id' => $sekolah->id, 'nama_lengkap' => 'Ani Suryani', 'nip' => '198001012005012002']);
        $penerima = Pegawai::create([
            'sekolah_id' => $sekolah->id,
            'nama_lengkap' => 'budi santoso, s.kom',
            'nip' => '199001012015011003',
            'pangkat_golongan' => 'III/a',
        ]);

        return [
            'dokumen' => new Dokumen([
                'jenis' => 'bast',
                'nomor' => '001/AR.03.05-CADISDIK.WIL-X',
                'tanggal' => '2026-03-12',
                'payload' => [
                    'sekolah' => [
                        'nama_sekolah' => $sekolah->nama_sekolah,
                        'alamat' => $sekolah->alamat,
                        'email' => $sekolah->email,
                        'kota_kab' => $sekolah->kota_kab,
                        'kode_pos' => $sekolah->kode_pos,
                    ],
                    'units' => [[
                        'nama_barang' => 'Laptop',
                        'merk_tipe' => 'Lenovo ThinkPad',
                        'tahun_perolehan' => 2026,
                        'kode_barang' => '1.3.2.10.01.02.002',
                        'nomor_register' => '001',
                        'kondisi' => 'RR',
                        'keterangan' => 'layar pecah',
                    ]],
                    'penerima' => [
                        'nama' => $penerima->nama_lengkap,
                        'nip' => $penerima->nip,
                        'pangkat_golongan' => $penerima->pangkat_golongan,
                        'jabatan' => 'staf kepegawaian',
                    ],
                    'pejabat' => [
                        'kepala_sekolah' => ['nama' => $kepala->nama_lengkap, 'nip' => $kepala->nip, 'pangkat_golongan' => 'IV/a'],
                        'wakasek_sarpras' => ['nama' => $wakasek->nama_lengkap, 'nip' => $wakasek->nip, 'pangkat_golongan' => 'IV/a'],
                    ],
                    'nomor_bast' => '001/AR.03.05-CADISDIK.WIL-X',
                ],
            ]),
        ];
    }

    public function test_bast_memuat_narasi_acuan(): void
    {
        $data = $this->payload();

        $html = view('pencatatan-bm.bast', [
            'dokumen' => $data['dokumen'],
            'judul' => 'Berita Acara Penyaluran',
            'sekolah' => null,
            'pejabat' => [],
            'labelJabatan' => [],
        ])->render();

        foreach ([
            'PEMERINTAH DAERAH PROVINSI JAWA BARAT',
            'CABANG DINAS PENDIDIKAN WILAYAH X',
            'BERITA ACARA PENYALURAN',
            'BARANG MILIK DAERAH (BMD)',
            'Pada hari ini',
            'Pengelola Sarpras',
            'PIHAK KESATU',
            'PIHAK KEDUA',
            'BUDI SANTOSO, S.Kom',
            'Rusak Ringan',
            'rangkap 2 (dua)',
        ] as $teks) {
            $this->assertStringContainsString($teks, $html);
        }
    }

    public function test_fakta_integritas_tanpa_baris_nomor(): void
    {
        $data = $this->payload();
        $data['dokumen']->jenis = 'fakta_integritas';

        $html = view('pencatatan-bm.fakta-integritas', [
            'dokumen' => $data['dokumen'],
            'judul' => 'Pakta Integritas',
            'sekolah' => null,
            'pejabat' => [],
            'labelJabatan' => [],
        ])->render();

        foreach ([
            'PAKTA INTEGRITAS',
            'PEMANFAATAN INVENTARIS BARANG MILIK DAERAH (BMD)',
            'Akan bertanggungjawab atas Inventaris Barang Milik Daerah (BMD)',
            'Akan memanfaatkan Inventaris BMD sesuai Tugas, Pokok dan Fungsi Jabatan',
            'Berita Acara Pengembalian',
            'Penanggungjawab Inventaris',
            'rangkap 2 (dua)',
        ] as $teks) {
            $this->assertStringContainsString($teks, $html);
        }

        $this->assertStringNotContainsString('Nomor:', $html);
    }

    public function test_berita_acara_pengembalian_memuat_nomor_bast_lama(): void
    {
        $data = $this->payload();
        $data['dokumen']->jenis = 'pengembalian';

        $html = view('pencatatan-bm.berita-acara-pengembalian', [
            'dokumen' => $data['dokumen'],
            'judul' => 'Berita Acara Pengembalian',
            'sekolah' => null,
            'pejabat' => [],
            'labelJabatan' => [],
        ])->render();

        foreach ([
            'BERITA ACARA PENGEMBALIAN',
            'PIHAK KESATU',
            'PIHAK KEDUA',
            '001/AR.03.05-CADISDIK.WIL-X',
            'rangkap 2 (dua)',
            'layar pecah',
        ] as $teks) {
            $this->assertStringContainsString($teks, $html);
        }
    }

    public function test_surat_permohonan_memuat_meta_dan_batas_waktu(): void
    {
        $data = $this->payload();
        $data['dokumen']->jenis = 'surat_pengembalian';

        $html = view('pencatatan-bm.surat-pengembalian', [
            'dokumen' => $data['dokumen'],
            'judul' => 'Surat Permohonan Pengembalian',
            'sekolah' => null,
            'pejabat' => [],
            'labelJabatan' => [],
        ])->render();

        foreach ([
            'Sifat',
            'Penting',
            'Lampiran',
            'Permohonan Pengembalian Barang Milik Daerah (BMD)',
            'Bpk./Ibu BUDI SANTOSO, S.Kom',
            'paling lambat 7 (tujuh) hari kerja',
            'KEPALA SEKOLAH',
            'Demikian surat ini disampaikan',
        ] as $teks) {
            $this->assertStringContainsString($teks, $html);
        }
    }
}
