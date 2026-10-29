<?php

namespace Tests\Feature;

use App\Models\DistribusiBhp\Dokumen;
use App\Models\DistribusiBhp\Pengajuan;
use App\Models\Master\Jabatan;
use App\Models\Master\Pegawai;
use App\Models\Master\Sekolah;
use App\Models\User;
use App\Services\Master\PenugasanPegawaiService;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sumber tunggal penandatangan PDF: `master_data_pegawai` +
 * `master_data_jabatan` + `master_data_penugasan_pegawai`. Test ini menjaga
 * dua hal: label jabatan dibaca dari database, dan tidak ada template PDF yang
 * memakai nama jabatan hard-coded sebagai fallback.
 */
class PdfPenandatanganSourceTest extends TestCase
{
    use RefreshDatabase;

    private Sekolah $sekolah;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);

        $this->sekolah = activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => 'SMA Sumber Tunggal',
            'kota_kab' => 'Kota Bandung',
        ]));
    }

    public function test_label_jabatan_dibaca_dari_master_data_jabatan(): void
    {
        // Nama jabatan diubah di master: PDF harus ikut nama itu, bukan teks
        // bawaan model.
        Jabatan::create([
            'sekolah_id' => $this->sekolah->id,
            'kode' => 'wakasek_sarpras',
            'nama' => 'Wakil Kepala Bidang Sarana',
        ]);

        $label = app(PenugasanPegawaiService::class)->labelPenandatangan($this->sekolah->id);

        $this->assertSame('Wakil Kepala Bidang Sarana', $label['wakasek_sarpras']);
        // Kode yang belum ada di master tetap punya label bawaan, bukan kosong.
        $this->assertSame('Kepala Sekolah', $label['kepala_sekolah']);
        $this->assertSame('Pembantu Pengurus Barang', $label['pembantu_pengurus_barang']);
    }

    public function test_label_jabatan_terisolasi_per_sekolah(): void
    {
        $lain = activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => 'SMA Lain',
            'kota_kab' => 'Kota Bandung',
        ]));

        Jabatan::create(['sekolah_id' => $this->sekolah->id, 'kode' => 'kepala_sekolah', 'nama' => 'Kepsek A']);
        Jabatan::create(['sekolah_id' => $lain->id, 'kode' => 'kepala_sekolah', 'nama' => 'Kepsek B']);

        $service = app(PenugasanPegawaiService::class);

        $this->assertSame('Kepsek A', $service->labelPenandatangan($this->sekolah->id)['kepala_sekolah']);
        $this->assertSame('Kepsek B', $service->labelPenandatangan($lain->id)['kepala_sekolah']);
    }

    public function test_snapshot_jabatan_diambil_dari_master_data_jabatan(): void
    {
        Jabatan::create([
            'sekolah_id' => $this->sekolah->id,
            'kode' => 'kepala_sekolah',
            'nama' => 'Kepala Sekolah Negeri',
        ]);

        app(PenugasanPegawaiService::class)->simpan($this->sekolah->id, (string) $this->operator()->id, [
            'jabatan' => 'kepala_sekolah',
            'nama' => 'Budi Santoso',
            'nip' => '198001012005011001',
            'mulai_menjabat' => '2026-01-01',
        ]);

        $snapshot = app(PenugasanPegawaiService::class)->pejabatPada($this->sekolah->id, '2026-06-01');

        $this->assertSame('Kepala Sekolah Negeri', $snapshot['kepala_sekolah']['jabatan']);
        // Pegawai tidak menyimpan jabatan: nama/NIP datang dari master pegawai.
        $this->assertSame('Budi Santoso', $snapshot['kepala_sekolah']['nama']);
        $this->assertSame('198001012005011001', $snapshot['kepala_sekolah']['nip']);
    }

    public function test_template_pdf_tidak_memakai_nama_jabatan_hard_coded(): void
    {
        $terlarang = [
            "?? 'Kepala Sekolah'",
            "?? 'Wakasek Sarpras'",
            "?? 'Wakasek Sarana Prasarana'",
            "?? 'Pembantu Pengurus Barang'",
        ];

        $pelanggaran = [];

        foreach (glob(resource_path('views/{pencatatan-bm,distribusi-bhp}/*.blade.php'), GLOB_BRACE) as $file) {
            $isi = (string) file_get_contents($file);

            foreach ($terlarang as $pola) {
                if (str_contains($isi, $pola)) {
                    $pelanggaran[] = str_replace(resource_path('views').'/', '', $file)." → {$pola}";
                }
            }
        }

        $this->assertSame([], $pelanggaran, 'Blade PDF masih memakai nama jabatan hard-coded sebagai fallback: '.implode('; ', $pelanggaran));
    }

    public function test_template_pdf_yang_menampilkan_pejabat_membaca_sumber_database(): void
    {
        $wajibBaca = [
            'pencatatan-bm/dokumen.blade.php',
            'pencatatan-bm/kir.blade.php',
            'pencatatan-bm/buku-inventaris.blade.php',
            'pencatatan-bm/sensus.blade.php',
            'distribusi-bhp/bast.blade.php',
            'distribusi-bhp/bap.blade.php',
            'distribusi-bhp/stok-opname.blade.php',
            'distribusi-bhp/buku-pengeluaran.blade.php',
            'distribusi-bhp/buku-penyaluran.blade.php',
        ];

        foreach ($wajibBaca as $relatif) {
            $isi = (string) file_get_contents(resource_path('views/'.$relatif));

            $this->assertMatchesRegularExpression(
                '/\$pejabat|\$penandatangan|\$penandatanganHalaman|\$labelJabatan/',
                $isi,
                "Template {$relatif} tidak membaca snapshot/label penandatangan dari database.",
            );
        }
    }

    public function test_pdf_bhp_memakai_label_jabatan_dari_snapshot_bukan_default(): void
    {
        Jabatan::create([
            'sekolah_id' => $this->sekolah->id,
            'kode' => 'pembantu_pengurus_barang',
            'nama' => 'Pengurus Barang Sekolah',
        ]);

        $service = app(PenugasanPegawaiService::class);

        foreach (Jabatan::KODE_PENANDATANGAN as $i => $kode) {
            $service->simpan($this->sekolah->id, (string) $this->operator()->id, [
                'jabatan' => $kode,
                'nama' => 'Pejabat '.$kode,
                'nip' => '19900202201001200'.$i,
                'mulai_menjabat' => '2026-01-01',
            ]);
        }

        $snapshot = $service->snapshotPenandatangan($this->sekolah->id, '2026-06-01');

        $this->assertSame('Pengurus Barang Sekolah', $snapshot['pembantu_pengurus_barang']['jabatan']);

        // HTML PDF benar-benar memakai label itu.
        $html = view('distribusi-bhp.bap', [
            'judul' => 'Berita Acara Pemeriksaan',
            'dokumen' => $this->dokumenBap($snapshot),
            'pengajuan' => $this->pengajuan(),
            'sekolah' => $this->sekolah->nama_sekolah,
            'penandatangan' => $snapshot,
            'labelJabatan' => $service->labelPenandatangan($this->sekolah->id),
        ])->render();

        $this->assertStringContainsString('Pengurus Barang Sekolah', $html);
        $this->assertStringNotContainsString('Pembantu Pengurus Barang', $html);
    }

    private function operator(): User
    {
        $user = User::create([
            'name' => 'Operator',
            'username' => 'op.sumber.'.uniqid(),
            'password' => bcrypt('password'),
            'sekolah_id' => $this->sekolah->id,
            'password_changed_at' => now(),
        ]);
        $user->assignRole('operator_sekolah');

        return $user;
    }

    /** @param array<string, array<string, mixed>> $snapshot */
    private function dokumenBap(array $snapshot): Dokumen
    {
        return new Dokumen([
            'jenis' => 'bap',
            'nomor' => 'BAP-001',
            'tanggal_titimangsa' => '2026-06-01',
            'payload' => ['penandatangan' => $snapshot],
        ]);
    }

    private function pengajuan(): Pengajuan
    {
        return new Pengajuan([
            'sekolah_id' => $this->sekolah->id,
            'sumber_dana' => 'BOS',
        ]);
    }
}
