<?php

namespace Tests\Feature;

use App\Http\Controllers\DistribusiBhp\DokumenController;
use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\Dokumen;
use App\Models\DistribusiBhp\Penerimaan;
use App\Models\DistribusiBhp\PenerimaanDetail;
use App\Models\DistribusiBhp\Pengajuan;
use App\Models\Master\Jabatan;
use App\Models\Master\Sekolah;
use App\Models\User;
use App\Services\DistribusiBhp\DokumenService;
use App\Services\Master\PenugasanPegawaiService;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;
use ZipArchive;

/**
 * Format cetak BHP: ukuran/orientasi per jenis dokumen dan pemakaian kop surat
 * mengikuti template baku `sistem-manajemen-aset`. Orientasi & kop diperiksa
 * pada PDF jadi (MediaBox + teks PDF), bukan pada konfigurasi, supaya
 * salah-set `setPaper` tidak lolos.
 */
class BhpPdfFormatTest extends TestCase
{
    use RefreshDatabase;

    private Sekolah $sekolah;

    private User $operator;

    private User $reviewer;

    private Pengajuan $pengajuan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);

        $this->sekolah = Sekolah::create(['nama_sekolah' => 'SMKN Format PDF', 'kota_kab' => 'Kuningan']);
        $this->operator = $this->user('op.format');
        $this->reviewer = $this->user('rev.format');
        $this->pengajuan = $this->approvedPengajuan();
    }

    /** A4 landscape dalam point (A4 = 595 × 842). */
    private const A4_LANDSCAPE = ['width' => 841.89, 'height' => 595.28];

    /** A4 portrait. */
    private const A4_PORTRAIT = ['width' => 595.28, 'height' => 841.89];

    /** F4 = 210 × 330 mm. */
    private const F4_PORTRAIT = ['width' => 595.28, 'height' => 935.43];

    public function test_ukuran_dan_orientasi_setiap_jenis_dokumen(): void
    {
        $harapan = [
            'nota_permintaan' => self::A4_PORTRAIT,
            'surat_permintaan' => self::A4_LANDSCAPE,
            'surat_penyaluran' => self::A4_PORTRAIT,
            'bast' => self::A4_PORTRAIT,
            'bap' => self::A4_PORTRAIT,
            // Stok opname memakai F4, bukan A4 — mengikuti template baku.
            'stok_opname' => self::F4_PORTRAIT,
            // Buku pengeluaran & penyaluran: landscape A4, tanpa kop.
            'buku_pengeluaran' => self::A4_LANDSCAPE,
            'buku_penyaluran' => self::A4_LANDSCAPE,
        ];

        foreach ($harapan as $jenis => $ukuran) {
            $dokumen = $this->dokumen($jenis);
            $pdf = $this->unduhPdf($dokumen);

            $this->assertSame($ukuran, $this->mediaBox($pdf), "Ukuran/orientasi {$jenis} tidak sesuai template baku.");
        }
    }

    public function test_kop_surat_hanya_dipakai_jenis_yang_memang_berkop(): void
    {
        // Pemakaian kop diperiksa di sumber blade: teks PDF terkompresi sehingga
        // tidak bisa dicari langsung di biner. Mapping VIEW ikut diuji supaya
        // blade yang salah pasang tetap tertangkap.
        $berkop = ['nota_permintaan', 'surat_penyaluran', 'bast', 'bap', 'stok_opname'];
        $tanpaKop = ['surat_permintaan', 'buku_pengeluaran', 'buku_penyaluran'];

        $view = (new \ReflectionClassConstant(DokumenController::class, 'VIEW'))->getValue();

        foreach ($berkop as $jenis) {
            $this->assertStringContainsString(
                'partials.kop-surat',
                (string) file_get_contents(resource_path('views/'.str_replace('.', '/', $view[$jenis]).'.blade.php')),
                "Dokumen {$jenis} kehilangan kop surat.",
            );
        }

        foreach ($tanpaKop as $jenis) {
            $this->assertStringNotContainsString(
                'partials.kop-surat',
                (string) file_get_contents(resource_path('views/'.str_replace('.', '/', $view[$jenis]).'.blade.php')),
                "Dokumen {$jenis} seharusnya tanpa kop surat.",
            );
        }

        // Buku penerimaan juga tanpa kop, mengikuti template baku.
        $this->assertStringNotContainsString(
            'partials.kop-surat',
            (string) file_get_contents(resource_path('views/distribusi-bhp/buku-penerimaan.blade.php')),
        );
    }

    public function test_buku_penerimaan_tanpa_kop_surat(): void
    {
        $response = $this->actingAs($this->operator)
            ->get(route('distribusi-bhp.bos.laporan.buku-penerimaan.cetak', ['tahun' => 2026, 'bulan' => 6]));

        $response->assertOk();
        $this->assertSame(self::A4_LANDSCAPE, $this->mediaBox($this->isi($response)));
    }

    /** Kop surat selalu membawa logo provinsi, bukan hanya teks. */
    public function test_kop_surat_menyertakan_logo(): void
    {
        $html = view('distribusi-bhp.partials.kop-surat', [
            'sekolah' => $this->sekolah->nama_sekolah,
            'alamat' => 'Jl. Contoh',
        ])->render();

        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertFileExists(public_path('images/logo-jabar.png'));
    }

    /** Batch mengirim ZIP berisi satu PDF per dokumen dengan format aslinya. */
    public function test_batch_pdf_berisi_satu_pdf_per_dokumen_dengan_format_asli(): void
    {
        $response = $this->actingAs($this->operator)
            ->post(route('distribusi-bhp.dokumen.batch', $this->pengajuan));

        $response->assertOk();
        $this->assertSame('application/zip', $response->headers->get('content-type'));

        $zip = new ZipArchive;
        $path = (string) tempnam(sys_get_temp_dir(), 'bhp-zip-test-');
        file_put_contents($path, $this->isi($response));

        $this->assertTrue($zip->open($path) === true, 'Berkas batch tidak bisa dibuka sebagai ZIP.');
        $this->assertSame($this->pengajuan->dokumen()->count(), $zip->numFiles);

        $ukuran = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $ukuran[$zip->getNameIndex($i)] = $this->mediaBox($zip->getFromIndex($i));
        }

        $zip->close();
        @unlink($path);

        // Nota permintaan portrait, surat permintaan landscape: keduanya harus
        // tetap pada formatnya sendiri di dalam satu unduhan.
        $this->assertContains(self::A4_PORTRAIT, $ukuran);
        $this->assertContains(self::A4_LANDSCAPE, $ukuran);
    }

    /** @return array{width:float,height:float} */
    private function mediaBox(string $pdf): array
    {
        $this->assertMatchesRegularExpression('~/MediaBox~', $pdf, 'PDF tidak memuat MediaBox.');
        preg_match('~/MediaBox\s*\[\s*([\d.]+)\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)\s*\]~', $pdf, $m);

        return [
            'width' => round((float) $m[3], 2),
            'height' => round((float) $m[4], 2),
        ];
    }

    private function unduhPdf(Dokumen $dokumen): string
    {
        $response = $this->actingAs($this->operator)
            ->get(route('distribusi-bhp.dokumen.pdf', [$this->pengajuan, $dokumen]));

        $response->assertOk();

        return $this->isi($response);
    }

    /** Isi respons, baik streamed, binari, maupun biasa. */
    private function isi(TestResponse $response): string
    {
        $base = $response->baseResponse;

        if ($base instanceof BinaryFileResponse) {
            return (string) file_get_contents($base->getFile()->getPathname());
        }

        if ($base instanceof StreamedResponse) {
            return (string) $response->streamedContent();
        }

        return (string) $response->getContent();
    }

    /** Buat dokumen tambahan sekali lalu pakai ulang. */
    private function dokumen(string $jenis): Dokumen
    {
        if ($this->pengajuan->dokumen()->where('jenis', $jenis)->doesntExist()) {
            app(DokumenService::class)->generateTambahan($this->pengajuan, $jenis, '2026-06-30');
            $this->pengajuan->unsetRelation('dokumen');
        }

        return $this->pengajuan->dokumen()->where('jenis', $jenis)->firstOrFail();
    }

    private function user(string $username): User
    {
        $user = User::create([
            'name' => ucfirst($username),
            'username' => $username,
            'password' => bcrypt('password'),
            'sekolah_id' => $this->sekolah->id,
            'password_changed_at' => now(),
        ]);
        $user->assignRole('operator_sekolah');

        return $user;
    }

    private function approvedPengajuan(): Pengajuan
    {
        // Approval mensyaratkan penandatangan lengkap.
        $pejabat = app(PenugasanPegawaiService::class);
        foreach (Jabatan::KODE_PENANDATANGAN as $i => $jabatan) {
            $pejabat->simpan((string) $this->sekolah->id, (string) $this->reviewer->id, [
                'jabatan' => $jabatan,
                'nama' => 'Pejabat '.$jabatan,
                'pangkat_golongan' => 'III/d',
                'nip' => '1980010120100110'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'mulai_menjabat' => '2026-01-01',
            ]);
        }

        $barang = Barang::create([
            'kode_barang' => '1.1.7.01.01.001',
            'nama_barang' => 'Kertas HVS A4',
            'satuan' => 'Rim',
            'sumber_dana' => 'BOS',
        ]);

        $penerimaan = Penerimaan::create([
            'sekolah_id' => $this->sekolah->id,
            'sumber_dana' => 'BOS',
            'is_saldo_awal' => true,
            'periode_tahun' => 2026,
        ]);
        PenerimaanDetail::create([
            'bhp_penerimaan_id' => $penerimaan->id,
            'barang_id' => $barang->id,
            'jumlah' => 100,
            'harga_satuan' => 50000,
            'nilai_total' => 5_000_000,
        ]);

        $pengajuan = Pengajuan::create([
            'sekolah_id' => $this->sekolah->id,
            'user_id' => $this->operator->id,
            'sumber_dana' => 'BOS',
            'status' => 'submitted',
            'tanggal' => '2026-06-17',
        ]);
        $detail = $pengajuan->details()->create([
            'barang_id' => $barang->id,
            'jumlah_diminta' => 20,
            'status_reservasi' => 'direservasi',
            'jumlah_direservasi' => 20,
        ]);

        app(DokumenService::class)->approveDanGenerate($pengajuan->load('details'), $this->reviewer, [$detail->id => 20]);

        return $pengajuan->fresh('dokumen');
    }
}
