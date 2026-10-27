<?php

namespace Tests\Feature;

use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\Dokumen;
use App\Models\DistribusiBhp\Penerimaan;
use App\Models\DistribusiBhp\PenerimaanDetail;
use App\Models\DistribusiBhp\Pengajuan;
use App\Models\Master\Jabatan;
use App\Models\Master\Sekolah;
use App\Models\User;
use App\Services\DistribusiBhp\DokumenService;
use App\Services\DistribusiBhp\StokService;
use App\Services\Master\PenugasanPegawaiService;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Halaman daftar dokumen per jenis pada modul Laporan BHP: semua jenis harus
 * terbuka, menampilkan nomor dokumen, dan memakai nomor Nota Permintaan sebagai
 * identitas pengajuan (bukan UUID kosong).
 */
class BhpLaporanDokumenListTest extends TestCase
{
    use RefreshDatabase;

    private Sekolah $sekolah;

    private User $operator;

    private Pengajuan $pengajuan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);

        $this->sekolah = Sekolah::create(['nama_sekolah' => 'SMKN Daftar', 'kota_kab' => 'Kota Bandung']);

        $this->operator = User::create([
            'name' => 'Operator',
            'username' => 'op.daftar',
            'password' => bcrypt('password'),
            'sekolah_id' => $this->sekolah->id,
            'password_changed_at' => now(),
        ]);
        $this->operator->assignRole('operator_sekolah');

        $barang = Barang::create([
            'kode_barang' => '1.1.7.30.500',
            'nama_barang' => 'Amplop',
            'satuan' => 'Pak',
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
            'jumlah' => 30,
            'harga_satuan' => 12_000,
            'nilai_total' => 360_000,
        ]);

        $pejabat = app(PenugasanPegawaiService::class);
        foreach (Jabatan::KODE_PENANDATANGAN as $kode) {
            $pejabat->simpan($this->sekolah->id, $this->operator->id, [
                'jabatan' => $kode,
                'nama' => 'Pejabat '.$kode,
                'mulai_menjabat' => '2026-01-01',
            ]);
        }

        $this->pengajuan = Pengajuan::create([
            'sekolah_id' => $this->sekolah->id,
            'user_id' => $this->operator->id,
            'sumber_dana' => 'BOS',
            'status' => 'draft',
            'tanggal' => '2026-07-10',
        ]);
        $this->pengajuan->details()->create([
            'barang_id' => $barang->id,
            'jumlah_diminta' => 4,
        ]);

        app(StokService::class)->submit($this->pengajuan->fresh());
        app(DokumenService::class)->approveDanGenerate($this->pengajuan->fresh(), $this->operator);
        $this->pengajuan = $this->pengajuan->fresh();

        foreach (DokumenService::JENIS_TAMBAHAN as $jenis) {
            app(DokumenService::class)->generateTambahan($this->pengajuan, $jenis, '2026-07-31');
        }
    }

    /** @return list<array{string,string}> */
    public static function halaman(): array
    {
        return [
            ['distribusi-bhp.bos.laporan.bast-internal', 'bast'],
            ['distribusi-bhp.bos.laporan.bap', 'bap'],
            ['distribusi-bhp.bos.laporan.buku-penyaluran', 'buku_penyaluran'],
            ['distribusi-bhp.bos.laporan.stok-opname', 'stok_opname'],
            ['distribusi-bhp.bos.laporan.surat-permintaan', 'surat_permintaan'],
            ['distribusi-bhp.bos.laporan.surat-penyaluran', 'surat_penyaluran'],
            ['distribusi-bhp.bos.laporan.bast-serah-terima', 'bast'],
        ];
    }

    #[DataProvider('halaman')]
    public function test_halaman_daftar_dokumen_terbuka_dan_menampilkan_nomor(string $route, string $jenis): void
    {
        $dokumen = $this->pengajuan->dokumen()->where('jenis', $jenis)->firstOrFail();
        $nota = $this->pengajuan->dokumen()->where('jenis', 'nota_permintaan')->firstOrFail();

        $this->actingAs($this->operator)
            ->get(route($route))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('DistribusiBhp/Laporan/DokumenList')
                ->where('jenis', $jenis)
                ->has('items.data', 1)
                ->where('items.data.0.nomor', $dokumen->nomor)
                // Nomor pengajuan tidak pernah diisi; nomor Nota Permintaan
                // dipakai sebagai identitas yang bisa dibaca.
                ->where('items.data.0.pengajuan.nomor', $nota->nomor));
    }

    public function test_halaman_daftar_dokumen_kosong_tetap_terbuka(): void
    {
        Dokumen::sumberDana('BOS')->where('jenis', 'bap')->delete();

        $this->actingAs($this->operator)
            ->get(route('distribusi-bhp.bos.laporan.bap'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('items.data', 0));
    }

    public function test_export_pengeluaran_memakai_periode_dan_nomor_nota(): void
    {
        $response = $this->actingAs($this->operator)->get(route('distribusi-bhp.bos.laporan.export', [
            'jenis' => 'pengeluaran',
            'tahun' => 2026,
            'bulan' => 7,
        ]));

        $response->assertOk();
        $this->assertStringContainsString('buku_pengeluaran_bhp_BOS_2026-07.xlsx', $response->headers->get('content-disposition') ?? '');
    }
}
