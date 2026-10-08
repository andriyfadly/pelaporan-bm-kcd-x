<?php

namespace Tests\Feature;

use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\Divisi;
use App\Models\DistribusiBhp\Penerimaan;
use App\Models\DistribusiBhp\PenerimaanDetail;
use App\Models\DistribusiBhp\Pengajuan;
use App\Models\Master\Sekolah;
use App\Models\User;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BhpDivisiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);
    }

    private function sekolah(string $nama, string $npsn): Sekolah
    {
        return Sekolah::create(['nama_sekolah' => $nama, 'npsn' => $npsn, 'kota_kab' => 'Kota Bandung']);
    }

    private function user(Sekolah $sekolah, string $username, string $role, ?Divisi $divisi = null): User
    {
        $user = User::create([
            'name' => ucfirst($role),
            'username' => $username,
            'password' => bcrypt('Password123!'),
            'sekolah_id' => $sekolah->id,
            'divisi_id' => $divisi?->id,
            'password_changed_at' => now(),
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function buatDivisi(Sekolah $sekolah, string $nama = 'Sarpras'): Divisi
    {
        return Divisi::create(['sekolah_id' => $sekolah->id, 'nama' => $nama]);
    }

    public function test_operator_sekolah_membuat_divisi_dan_akun_dengan_username_npsn_suffix(): void
    {
        $sekolah = $this->sekolah('SMKN Divisi Buat', '20221111');
        $operatorSekolah = $this->user($sekolah, '20221111-admin', 'operator_sekolah');

        $this->actingAs($operatorSekolah)
            ->post(route('distribusi-bhp.divisi.store'), [
                'nama_divisi' => 'Sarpras',
                'nama_operator' => 'Budi Sarpras',
                'username_suffix' => 'sarpras',
                'password' => 'Password123!',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $divisi = Divisi::where('sekolah_id', $sekolah->id)->where('nama', 'Sarpras')->firstOrFail();
        $akun = User::where('username', '20221111-sarpras')->firstOrFail();

        $this->assertSame($sekolah->id, $akun->sekolah_id);
        $this->assertSame($divisi->id, $akun->divisi_id);
        $this->assertNull($akun->password_changed_at);
        $this->assertTrue($akun->hasRole('operator_divisi'));
    }

    public function test_prefix_sekolah_lain_ditolak_karena_suffix_saja_yang_diisi(): void
    {
        $sekolahA = $this->sekolah('SMKN Divisi A', '20222222');
        $sekolahB = $this->sekolah('SMKN Divisi B', '20223333');
        $operatorA = $this->user($sekolahA, '20222222-admin', 'operator_sekolah');
        $this->user($sekolahB, '20223333-admin', 'operator_sekolah');

        // Coba menempel username sekolah lain sebagai suffix: tetap membentuk
        // {npsn-sendiri}-{suffix}, bukan username sekolah lain. Karena sudah ada
        // user dengan username persis "20223333-admin" di sekolah B, form untuk
        // akun baru punya nama operator berbeda; yang dilarang adalah prefix dari
        // client — di sini server membentuk "20222222-20223333-admin".
        $this->actingAs($operatorA)
            ->post(route('distribusi-bhp.divisi.store'), [
                'nama_divisi' => 'Kurikulum',
                'nama_operator' => 'Ani',
                'username_suffix' => '20223333-admin',
                'password' => 'Password123!',
            ])
            ->assertSessionHasNoErrors();

        $akun = User::where('username', '20222222-20223333-admin')->firstOrFail();
        $this->assertSame($sekolahA->id, $akun->sekolah_id);
    }

    public function test_suffix_tidak_valid_ditolak(): void
    {
        $sekolah = $this->sekolah('SMKN Divisi Suffix', '20224444');
        $operator = $this->user($sekolah, '20224444-admin', 'operator_sekolah');

        foreach (['Ada Spasi', '-awalan', 'akhiran-', 'DOBEL--DASH', 'hurufBESAR'] as $suffix) {
            $this->actingAs($operator)
                ->post(route('distribusi-bhp.divisi.store'), [
                    'nama_divisi' => 'Divisi X',
                    'nama_operator' => 'Operator X',
                    'username_suffix' => $suffix,
                    'password' => 'Password123!',
                ])
                ->assertSessionHasErrors('username_suffix');
        }

        $this->assertSame(0, Divisi::where('sekolah_id', $sekolah->id)->count());
    }

    public function test_username_duplikat_ditolak(): void
    {
        $sekolah = $this->sekolah('SMKN Divisi Duplikat', '20225555');
        $operator = $this->user($sekolah, '20225555-admin', 'operator_sekolah');

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.divisi.store'), [
                'nama_divisi' => 'Sarpras',
                'nama_operator' => 'Pertama',
                'username_suffix' => 'sarpras',
                'password' => 'Password123!',
            ])
            ->assertRedirect();

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.divisi.store'), [
                'nama_divisi' => 'Tata Usaha',
                'nama_operator' => 'Kedua',
                'username_suffix' => 'sarpras',
                'password' => 'Password123!',
            ])
            ->assertSessionHasErrors('username_suffix');

        $this->assertSame(1, User::where('username', '20225555-sarpras')->count());
    }

    public function test_role_selain_operator_sekolah_ditolak(): void
    {
        $sekolah = $this->sekolah('SMKN Divisi Guard', '20226666');
        $bendahara = $this->user($sekolah, '20226666-bend', 'bendahara_sekolah');

        $this->actingAs($bendahara)
            ->post(route('distribusi-bhp.divisi.store'), [
                'nama_divisi' => 'Sarpras',
                'nama_operator' => 'Budi',
                'username_suffix' => 'sarpras',
                'password' => 'Password123!',
            ])
            ->assertForbidden();

        $this->assertSame(0, Divisi::count());
    }

    public function test_sekolah_tanpa_npsn_ditolak(): void
    {
        $sekolah = Sekolah::create(['nama_sekolah' => 'SMKN Tanpa NPSN', 'kota_kab' => 'Kota Bandung']);
        $operator = $this->user($sekolah, 'operator-tanpa-npsn', 'operator_sekolah');

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.divisi.store'), [
                'nama_divisi' => 'Sarpras',
                'nama_operator' => 'Budi',
                'username_suffix' => 'sarpras',
                'password' => 'Password123!',
            ])
            ->assertForbidden();

        $this->assertSame(0, Divisi::count());
    }

    public function test_index_divisi_scoped_ke_sekolah_sendiri(): void
    {
        $sekolahA = $this->sekolah('SMKN Divisi Idx A', '20227777');
        $sekolahB = $this->sekolah('SMKN Divisi Idx B', '20228888');
        $operatorA = $this->user($sekolahA, '20227777-admin', 'operator_sekolah');
        $this->buatDivisi($sekolahA, 'Sarpras A');
        $this->buatDivisi($sekolahB, 'Sarpras B');

        $this->actingAs($operatorA)
            ->get(route('distribusi-bhp.divisi.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('DistribusiBhp/Divisi/Index')
                ->has('divisis', 1)
                ->where('divisis.0.nama', 'Sarpras A')
                ->where('npsn', '20227777'));
    }

    public function test_operator_divisi_membuat_pengajuan_terikat_divisinya(): void
    {
        $sekolah = $this->sekolah('SMKN Divisi Pengajuan', '20229901');
        $divisi = $this->buatDivisi($sekolah, 'Sarpras');
        $opDivisi = $this->user($sekolah, '20229901-sarpras', 'operator_divisi', $divisi);

        $barang = Barang::create(['kode_barang' => '1.1.7.50.ISO', 'nama_barang' => 'Semen', 'satuan' => 'Sak', 'sumber_dana' => 'BOS']);
        $penerimaan = Penerimaan::create(['sekolah_id' => $sekolah->id, 'sumber_dana' => 'BOS', 'is_saldo_awal' => true, 'periode_tahun' => 2026]);
        PenerimaanDetail::create(['bhp_penerimaan_id' => $penerimaan->id, 'barang_id' => $barang->id, 'jumlah' => 100, 'harga_satuan' => 1, 'nilai_total' => 100]);

        $this->actingAs($opDivisi)
            ->post(route('distribusi-bhp.pengajuan.store'), [
                'sumber_dana' => 'BOS',
                'tanggal' => '2026-06-17',
                'items' => [['barang_id' => $barang->id, 'jumlah_diminta' => 10]],
            ])
            ->assertRedirect();

        $pengajuan = Pengajuan::firstOrFail();
        $this->assertSame($divisi->id, $pengajuan->divisi_id);
        $this->assertSame($sekolah->id, $pengajuan->sekolah_id);
    }

    public function test_operator_divisi_hanya_melihat_pengajuan_divisinya(): void
    {
        $sekolah = $this->sekolah('SMKN Divisi Isolasi', '20229902');
        $divisiA = $this->buatDivisi($sekolah, 'Sarpras');
        $divisiB = $this->buatDivisi($sekolah, 'Tata Usaha');
        $opA = $this->user($sekolah, '20229902-sarpras', 'operator_divisi', $divisiA);

        $opSekolah = $this->user($sekolah, '20229902-admin', 'operator_sekolah');
        foreach ([$divisiA, $divisiB] as $divisi) {
            Pengajuan::create([
                'sekolah_id' => $sekolah->id,
                'divisi_id' => $divisi->id,
                'user_id' => $opSekolah->id,
                'sumber_dana' => 'BOS',
                'status' => 'draft',
                'tanggal' => '2026-06-17',
            ]);
        }

        $this->actingAs($opA)
            ->get(route('distribusi-bhp.pengajuan.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('items.data', 1)
                ->where('items.data.0.divisi_id', $divisiA->id));
    }

    public function test_operator_divisi_tidak_bisa_submit_pengajuan_divisi_lain(): void
    {
        $sekolah = $this->sekolah('SMKN Divisi Cross', '20229903');
        $divisiA = $this->buatDivisi($sekolah, 'Sarpras');
        $divisiB = $this->buatDivisi($sekolah, 'Tata Usaha');
        $opA = $this->user($sekolah, '20229903-sarpras', 'operator_divisi', $divisiA);
        $opSekolah = $this->user($sekolah, '20229903-admin', 'operator_sekolah');

        $pengajuan = Pengajuan::create([
            'sekolah_id' => $sekolah->id,
            'divisi_id' => $divisiB->id,
            'user_id' => $opSekolah->id,
            'sumber_dana' => 'BOS',
            'status' => 'draft',
            'tanggal' => '2026-06-17',
        ]);
        $pengajuan->details()->create(['barang_id' => Barang::create(['kode_barang' => 'X1', 'nama_barang' => 'X', 'satuan' => 'Unit', 'sumber_dana' => 'BOS'])->id, 'jumlah_diminta' => 1]);

        $this->actingAs($opA)
            ->post(route('distribusi-bhp.pengajuan.submit', $pengajuan))
            ->assertForbidden();

        $this->assertSame('draft', $pengajuan->fresh()->status);
    }

    public function test_reset_password_dan_toggle_hanya_untuk_operator_divisi_sekolah_sendiri(): void
    {
        $sekolahA = $this->sekolah('SMKN Divisi Reset A', '20229904');
        $sekolahB = $this->sekolah('SMKN Divisi Reset B', '20229905');
        $divisiA = $this->buatDivisi($sekolahA, 'Sarpras');
        $divisiB = $this->buatDivisi($sekolahB, 'Sarpras');
        $opSekolahA = $this->user($sekolahA, '20229904-admin', 'operator_sekolah');
        $targetB = $this->user($sekolahB, '20229905-sarpras', 'operator_divisi', $divisiB);

        $pwSebelum = $targetB->password_changed_at;

        // Operator sekolah lain tidak boleh reset.
        $this->actingAs($opSekolahA)
            ->put(route('distribusi-bhp.divisi.operator.password', $targetB), ['password' => 'NewPassword123!'])
            ->assertForbidden();

        // Target bukan operator divisi (tanpa divisi_id) tidak bisa diproses.
        $this->actingAs($opSekolahA)
            ->put(route('distribusi-bhp.divisi.operator.password', $opSekolahA), ['password' => 'NewPassword123!'])
            ->assertForbidden();

        // Password target tidak tersentuh (password_changed_at tetap nilai awal).
        $this->assertSame($pwSebelum?->toDateTimeString(), $targetB->fresh()->password_changed_at?->toDateTimeString());
    }

    public function test_reset_password_oleh_operator_sekolah_sendiri(): void
    {
        $sekolah = $this->sekolah('SMKN Divisi Reset Ok', '20229906');
        $divisi = $this->buatDivisi($sekolah, 'Sarpras');
        $opSekolah = $this->user($sekolah, '20229906-admin', 'operator_sekolah');
        $target = $this->user($sekolah, '20229906-sarpras', 'operator_divisi', $divisi);
        $target->update(['password_changed_at' => now()]);

        $this->actingAs($opSekolah)
            ->put(route('distribusi-bhp.divisi.operator.password', $target), ['password' => 'NewPassword123!'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNull($target->fresh()->password_changed_at);
    }

    public function test_operator_sekolah_melihat_semua_divisi_di_index_dan_tidak_difilter_divisi(): void
    {
        $sekolah = $this->sekolah('SMKN Divisi Op Sekolah', '20229907');
        $divisi = $this->buatDivisi($sekolah, 'Sarpras');
        $opSekolah = $this->user($sekolah, '20229907-admin', 'operator_sekolah');
        $opDivisi = $this->user($sekolah, '20229907-sarpras', 'operator_divisi', $divisi);

        Pengajuan::create([
            'sekolah_id' => $sekolah->id,
            'divisi_id' => $divisi->id,
            'user_id' => $opDivisi->id,
            'sumber_dana' => 'BOS',
            'status' => 'draft',
            'tanggal' => '2026-06-17',
        ]);

        // Operator sekolah (tanpa divisi_id) melihat pengajuan semua divisi.
        $this->actingAs($opSekolah)
            ->get(route('distribusi-bhp.pengajuan.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('items.data', 1));
    }

    public function test_operator_divisi_tidak_dapat_mengakses_dokumen_divisi_lain(): void
    {
        $sekolah = $this->sekolah('SMKN Divisi Dokumen', '20229908');
        $divisiA = $this->buatDivisi($sekolah, 'Sarpras');
        $divisiB = $this->buatDivisi($sekolah, 'Tata Usaha');
        $opA = $this->user($sekolah, '20229908-sarpras', 'operator_divisi', $divisiA);
        $opSekolah = $this->user($sekolah, '20229908-admin', 'operator_sekolah');

        $pengajuan = Pengajuan::create([
            'sekolah_id' => $sekolah->id,
            'divisi_id' => $divisiB->id,
            'user_id' => $opSekolah->id,
            'sumber_dana' => 'BOS',
            'status' => 'approved',
            'tanggal' => '2026-06-17',
        ]);
        $pengajuan->details()->create(['barang_id' => Barang::create(['kode_barang' => 'X2', 'nama_barang' => 'Y', 'satuan' => 'Unit', 'sumber_dana' => 'BOS'])->id, 'jumlah_diminta' => 1]);
        $dokumen = $pengajuan->dokumen()->create([
            'jenis' => 'nota_permintaan',
            'nomor' => '001/BHP/NPB/BOS/VI/2026',
            'tanggal_titimangsa' => '2026-06-17',
        ]);

        // Show, PDF, batch, dan tambahan semuanya ditolak untuk operator divisi lain.
        $this->actingAs($opA)->get(route('distribusi-bhp.dokumen.show', $pengajuan))->assertForbidden();
        $this->actingAs($opA)->get(route('distribusi-bhp.dokumen.pdf', [$pengajuan, $dokumen]))->assertForbidden();
        $this->actingAs($opA)->post(route('distribusi-bhp.dokumen.batch', $pengajuan))->assertForbidden();
        $this->actingAs($opA)->post(route('distribusi-bhp.dokumen.tambahan', $pengajuan), ['jenis' => 'bast'])->assertForbidden();
    }

    public function test_operator_divisi_dapat_mengakses_dokumen_divisinya(): void
    {
        $sekolah = $this->sekolah('SMKN Divisi Dokumen OK', '20229909');
        $divisi = $this->buatDivisi($sekolah, 'Sarpras');
        $opDivisi = $this->user($sekolah, '20229909-sarpras', 'operator_divisi', $divisi);

        $pengajuan = Pengajuan::create([
            'sekolah_id' => $sekolah->id,
            'divisi_id' => $divisi->id,
            'user_id' => $opDivisi->id,
            'sumber_dana' => 'BOS',
            'status' => 'approved',
            'tanggal' => '2026-06-17',
        ]);
        $pengajuan->details()->create(['barang_id' => Barang::create(['kode_barang' => 'X3', 'nama_barang' => 'Z', 'satuan' => 'Unit', 'sumber_dana' => 'BOS'])->id, 'jumlah_diminta' => 1]);

        $this->actingAs($opDivisi)
            ->get(route('distribusi-bhp.dokumen.show', $pengajuan))
            ->assertOk();
    }

    public function test_dashboard_operator_divisi_hanya_menghitung_divisinya(): void
    {
        $sekolah = $this->sekolah('SMKN Divisi Dashboard', '20229910');
        $divisiA = $this->buatDivisi($sekolah, 'Sarpras');
        $divisiB = $this->buatDivisi($sekolah, 'Tata Usaha');
        $opA = $this->user($sekolah, '20229910-sarpras', 'operator_divisi', $divisiA);
        $opSekolah = $this->user($sekolah, '20229910-admin', 'operator_sekolah');

        foreach ([
            [$divisiA, 'draft'],
            [$divisiA, 'approved'],
            [$divisiB, 'draft'],
            [$divisiB, 'submitted'],
        ] as [$divisi, $status]) {
            Pengajuan::create([
                'sekolah_id' => $sekolah->id,
                'divisi_id' => $divisi->id,
                'user_id' => $opSekolah->id,
                'sumber_dana' => 'BOS',
                'status' => $status,
                'tanggal' => '2026-06-17',
            ]);
        }

        // Operator divisi A hanya melihat 1 draft + 1 approved milik divisinya.
        $this->actingAs($opA)
            ->get(route('distribusi-bhp.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.pengajuanDraft', 1)
                ->where('stats.pengajuanSubmitted', 0)
                ->where('stats.pengajuanApproved', 1));

        // Operator sekolah melihat semua divisi: 2 draft + 1 submitted + 1 approved.
        $this->actingAs($opSekolah)
            ->get(route('distribusi-bhp.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.pengajuanDraft', 2)
                ->where('stats.pengajuanSubmitted', 1)
                ->where('stats.pengajuanApproved', 1));
    }
}
