<?php

namespace Tests\Feature;

use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\HargaBarang;
use App\Models\DistribusiBhp\Penerimaan;
use App\Models\DistribusiBhp\PenerimaanDetail;
use App\Models\DistribusiBhp\Pengajuan;
use App\Models\Master\Jabatan;
use App\Models\Master\Sekolah;
use App\Models\User;
use App\Services\DistribusiBhp\DokumenService;
use App\Services\DistribusiBhp\LaporanService;
use App\Services\DistribusiBhp\StokService;
use App\Services\Master\PenugasanPegawaiService;
use App\Support\DistribusiBhp\Periode;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Snapshot isi dokumen BHP wajib mengikuti TANGGAL DOKUMEN, bukan saat dokumen
 * dibuat: stok opname bertanggal lampau tidak boleh berubah walau transaksi
 * baru masuk setelahnya.
 */
class BhpDokumenSnapshotPeriodeTest extends TestCase
{
    use RefreshDatabase;

    private Sekolah $sekolah;

    private User $operator;

    private User $reviewer;

    private Barang $barang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);

        $this->sekolah = Sekolah::create(['nama_sekolah' => 'SMKN Snapshot', 'kota_kab' => 'Kota Bandung']);

        $this->operator = User::create([
            'name' => 'Operator',
            'username' => 'op.snapshot',
            'password' => bcrypt('password'),
            'sekolah_id' => $this->sekolah->id,
            'password_changed_at' => now(),
        ]);
        $this->operator->assignRole('operator_sekolah');

        $this->reviewer = User::create([
            'name' => 'Reviewer',
            'username' => 'rev.snapshot',
            'password' => bcrypt('password'),
            'sekolah_id' => $this->sekolah->id,
            'password_changed_at' => now(),
        ]);
        $this->reviewer->assignRole('operator_sekolah');

        $this->barang = Barang::create([
            'kode_barang' => '1.1.7.30.700',
            'nama_barang' => 'Map Plastik',
            'satuan' => 'Buah',
            'sumber_dana' => 'BOS',
        ]);

        $pejabat = app(PenugasanPegawaiService::class);
        foreach (Jabatan::KODE_PENANDATANGAN as $kode) {
            $pejabat->simpan($this->sekolah->id, $this->reviewer->id, [
                'jabatan' => $kode,
                'nama' => 'Pejabat '.$kode,
                'mulai_menjabat' => '2026-01-01',
            ]);
        }
    }

    private function terima(int $jumlah, string $tanggal): void
    {
        $penerimaan = Penerimaan::create([
            'sekolah_id' => $this->sekolah->id,
            'sumber_dana' => 'BOS',
            'is_saldo_awal' => false,
            'tanggal_bast' => $tanggal,
        ]);

        PenerimaanDetail::create([
            'bhp_penerimaan_id' => $penerimaan->id,
            'barang_id' => $this->barang->id,
            'jumlah' => $jumlah,
            'harga_satuan' => 5_000,
            'nilai_total' => $jumlah * 5_000,
        ]);
    }

    private function pengajuanDisetujui(int $jumlah, string $tanggal): Pengajuan
    {
        $pengajuan = Pengajuan::create([
            'sekolah_id' => $this->sekolah->id,
            'user_id' => $this->operator->id,
            'sumber_dana' => 'BOS',
            'status' => 'draft',
            'tanggal' => $tanggal,
        ]);
        $pengajuan->details()->create([
            'barang_id' => $this->barang->id,
            'jumlah_diminta' => $jumlah,
        ]);

        app(StokService::class)->submit($pengajuan->fresh());
        app(DokumenService::class)->approveDanGenerate($pengajuan->fresh(), $this->reviewer);

        return $pengajuan->fresh();
    }

    public function test_buku_pengeluaran_memakai_nomor_dokumen_nota_bila_nomor_pengajuan_kosong(): void
    {
        $this->terima(50, '2026-06-01');
        $pengajuan = $this->pengajuanDisetujui(5, '2026-07-05');

        $nota = $pengajuan->dokumen()->where('jenis', 'nota_permintaan')->firstOrFail();
        $rows = app(LaporanService::class)
            ->bukuPengeluaran('BOS', $this->sekolah->id, Periode::bulan(2026, 7));

        $this->assertNull($pengajuan->fresh()->nomor_pengajuan, 'Kolom nomor pengajuan memang tidak diisi.');
        $this->assertSame($nota->nomor, $rows[0]['nomor_pengajuan']);
        $this->assertSame($nota->nomor, $rows[0]['nomor_dokumen']);
    }

    public function test_stok_opname_memakai_harga_rata_rata_penerimaan_untuk_nilai(): void
    {
        $this->terima(10, '2026-07-01');
        HargaBarang::create([
            'barang_id' => $this->barang->id,
            'harga_satuan' => 7_500,
        ]);

        $pengajuan = $this->pengajuanDisetujui(2, '2026-07-05');
        $dokumen = app(DokumenService::class)->generateTambahan($pengajuan, 'stok_opname', '2026-07-31');

        $item = collect($dokumen->payload['items'])->firstWhere('barang_id', $this->barang->id);

        $this->assertSame(10, $item['fisik'], 'Fisik = total barang masuk.');
        $this->assertSame(8, $item['fisik_nyata'], 'Fisik nyata = 10 masuk - 2 disetujui.');
        // Harga beli nyata pada periode menang atas harga master.
        $this->assertSame(5_000, $item['harga_satuan']);
        $this->assertSame(40_000, $item['nilai_total'], 'Nilai = fisik nyata x harga rata-rata penerimaan.');
    }

    public function test_harga_master_dipakai_bila_penerimaan_tidak_bernilai(): void
    {
        $this->terima(5, '2026-07-01');
        PenerimaanDetail::query()->update(['harga_satuan' => 0, 'nilai_total' => 0]);
        HargaBarang::create([
            'barang_id' => $this->barang->id,
            'harga_satuan' => 9_000,
        ]);

        $pengajuan = $this->pengajuanDisetujui(1, '2026-07-05');
        $dokumen = app(DokumenService::class)->generateTambahan($pengajuan, 'stok_opname', '2026-07-31');

        $item = collect($dokumen->payload['items'])->firstWhere('barang_id', $this->barang->id);

        $this->assertSame(9_000, $item['harga_satuan'], 'Fallback ke harga master terakhir.');
        $this->assertSame(36_000, $item['nilai_total']);
    }

    public function test_stok_opname_bertanggal_lampau_memakai_stok_tanggal_itu(): void
    {
        $this->terima(100, '2026-06-01');
        $pengajuan = $this->pengajuanDisetujui(20, '2026-06-20');

        // Transaksi baru SETELAH dokumen akan dibuat.
        $this->terima(90, '2026-08-01');

        $dokumen = app(DokumenService::class)->generateTambahan($pengajuan, 'stok_opname', '2026-07-31');

        $item = collect($dokumen->payload['items'])->firstWhere('barang_id', $this->barang->id);

        $this->assertNotNull($item);
        $this->assertSame(100, $item['fisik'], 'Fisik per 31 Juli, bukan termasuk penerimaan Agustus.');
        $this->assertSame(20, $item['terpakai']);
        $this->assertSame(80, $item['tersedia']);
    }

    public function test_stok_opname_tidak_berubah_setelah_approval_baru(): void
    {
        $this->terima(100, '2026-06-01');
        $pengajuan = $this->pengajuanDisetujui(10, '2026-06-10');

        $dokumen = app(DokumenService::class)->generateTambahan($pengajuan, 'stok_opname', '2026-07-31');
        $sebelum = $dokumen->payload['items'];

        $this->pengajuanDisetujui(30, '2026-07-20');

        $dokumen->refresh();
        $this->assertSame($sebelum, $dokumen->payload['items'], 'Snapshot tersimpan tidak boleh ikut berubah.');
    }

    public function test_buku_pengeluaran_bertanggal_lampau_hanya_memuat_bulan_dokumen(): void
    {
        $this->terima(100, '2026-06-01');
        $pengajuan = $this->pengajuanDisetujui(15, '2026-07-04');
        $lain = $this->pengajuanDisetujui(25, '2026-08-04');

        $dokumen = app(DokumenService::class)->generateTambahan($pengajuan, 'buku_pengeluaran', '2026-07-31');

        $jumlah = collect($dokumen->payload['items'])->pluck('jumlah')->all();

        $this->assertSame([15], $jumlah, 'Hanya transaksi Juli yang disimpan.');
        $this->assertNotContains(25, $jumlah, 'Transaksi Agustus tidak boleh masuk snapshot Juli.');
        $this->assertSame('2026-08-04', $lain->tanggal->toDateString());
    }

    public function test_buku_penyaluran_bertanggal_lampau_hanya_memuat_bulan_dokumen(): void
    {
        $this->terima(100, '2026-06-01');
        $pengajuan = $this->pengajuanDisetujui(15, '2026-07-04');
        $this->pengajuanDisetujui(25, '2026-08-04');

        $dokumen = app(DokumenService::class)->generateTambahan($pengajuan, 'buku_penyaluran', '2026-07-31');

        $this->assertSame([15], collect($dokumen->payload['items'])->pluck('jumlah')->all());
    }

    public function test_dokumen_wajib_approval_memakai_tanggal_pengajuan_untuk_pejabat(): void
    {
        $this->terima(50, '2026-06-01');

        // Pejabat berganti 1 Juli: dokumen tanggal 20 Juni harus tetap pejabat lama.
        $penugasan = app(PenugasanPegawaiService::class);
        foreach (Jabatan::KODE_PENANDATANGAN as $kode) {
            $penugasan->simpan($this->sekolah->id, $this->reviewer->id, [
                'jabatan' => $kode,
                'nama' => 'Pejabat Baru '.$kode,
                'mulai_menjabat' => '2026-07-01',
            ]);
        }

        $pengajuan = Pengajuan::create([
            'sekolah_id' => $this->sekolah->id,
            'user_id' => $this->operator->id,
            'sumber_dana' => 'BOS',
            'status' => 'draft',
            'tanggal' => '2026-06-20',
        ]);
        $pengajuan->details()->create(['barang_id' => $this->barang->id, 'jumlah_diminta' => 5]);

        app(StokService::class)->submit($pengajuan->fresh());
        app(DokumenService::class)->approveDanGenerate($pengajuan->fresh(), $this->reviewer);

        $dokumen = $pengajuan->fresh()->dokumen()->where('jenis', 'nota_permintaan')->firstOrFail();

        $this->assertSame('Pejabat kepala_sekolah', $dokumen->payload['penandatangan']['kepala_sekolah']['nama']);
        $this->assertSame('2026-06-20', $dokumen->tanggal_titimangsa->toDateString());
    }
}
