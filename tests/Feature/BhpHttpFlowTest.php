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

class BhpHttpFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);
    }

    private function sekolah(string $nama = 'SMKN 1 HTTP'): Sekolah
    {
        return Sekolah::create(['nama_sekolah' => $nama, 'kota_kab' => 'Kota Bandung']);
    }

    private function user(Sekolah $sekolah, string $role, string $username): User
    {
        $user = User::create([
            'name' => ucfirst($role),
            'username' => $username,
            'password' => bcrypt('password'),
            'sekolah_id' => $sekolah->id,
            'password_changed_at' => now(),
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function barang(Sekolah $sekolah, string $sumberDana = 'BOS', int $stok = 100): Barang
    {
        $barang = Barang::create([
            'kode_barang' => '1.1.7.01.'.$sumberDana,
            'nama_barang' => 'Semen Portland',
            'satuan' => 'Sak',
            'sumber_dana' => $sumberDana,
        ]);

        $penerimaan = Penerimaan::create(['sekolah_id' => $sekolah->id, 'sumber_dana' => $sumberDana, 'is_saldo_awal' => true, 'periode_tahun' => 2026]);
        PenerimaanDetail::create([
            'bhp_penerimaan_id' => $penerimaan->id,
            'barang_id' => $barang->id,
            'jumlah' => $stok,
            'harga_satuan' => 50000,
            'nilai_total' => $stok * 50000,
        ]);

        return $barang;
    }

    public function test_operator_dapat_membuat_dan_submit_pengajuan(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->user($sekolah, 'operator_sekolah', 'op.bhp');
        $barang = $this->barang($sekolah, 'BOS', 100);

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.pengajuan.store'), [
                'sumber_dana' => 'BOS',
                'tanggal' => '2026-06-17',
                'items' => [
                    ['barang_id' => $barang->id, 'jumlah_diminta' => 30],
                ],
            ])
            ->assertRedirect(route('distribusi-bhp.pengajuan.index', ['sumber_dana' => 'BOS']));

        $pengajuan = Pengajuan::firstOrFail();
        $this->assertSame('draft', $pengajuan->status);

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.pengajuan.submit', $pengajuan))
            ->assertRedirect();

        $this->assertSame('submitted', $pengajuan->fresh()->status);
    }

    public function test_membuat_pengajuan_dengan_sumber_dana_tidak_sesuai_ditolak(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->user($sekolah, 'operator_sekolah', 'op.bhp2');
        $barang = $this->barang($sekolah, 'BOPD', 50);

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.pengajuan.store'), [
                'sumber_dana' => 'BOS',
                'tanggal' => '2026-06-17',
                'items' => [['barang_id' => $barang->id, 'jumlah_diminta' => 5]],
            ])
            ->assertSessionHasErrors();

        $this->assertSame(0, Pengajuan::count());
    }

    public function test_operator_tidak_dapat_approve_pengajuan_sendiri(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->user($sekolah, 'operator_sekolah', 'op.bhp3');
        $barang = $this->barang($sekolah, 'BOS', 100);

        $pengajuan = Pengajuan::create([
            'sekolah_id' => $sekolah->id,
            'user_id' => $operator->id,
            'sumber_dana' => 'BOS',
            'status' => 'submitted',
            'tanggal' => '2026-06-17',
        ]);
        $detail = $pengajuan->details()->create([
            'barang_id' => $barang->id,
            'jumlah_diminta' => 30,
            'status_reservasi' => 'direservasi',
            'jumlah_direservasi' => 30,
        ]);

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.review-pengajuan.approve', $pengajuan), [
                'jumlah_disetujui' => [$detail->id => 30],
            ])
            ->assertForbidden();

        $this->assertSame('submitted', $pengajuan->fresh()->status);
    }

    public function test_operator_tidak_dapat_mengakses_pengajuan_sekolah_lain(): void
    {
        $sekolahA = $this->sekolah('SMKN A');
        $sekolahB = $this->sekolah('SMKN B');
        $operatorA = $this->user($sekolahA, 'operator_sekolah', 'op.a');
        $operatorB = $this->user($sekolahB, 'operator_sekolah', 'op.b');

        $pengajuan = Pengajuan::create([
            'sekolah_id' => $sekolahB->id,
            'user_id' => $operatorB->id,
            'sumber_dana' => 'BOS',
            'status' => 'draft',
            'tanggal' => '2026-06-17',
        ]);

        $this->actingAs($operatorA)
            ->post(route('distribusi-bhp.pengajuan.submit', $pengajuan))
            ->assertForbidden();
    }

    public function test_operator_dapat_mengubah_dan_menghapus_draft_sendiri(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->user($sekolah, 'operator_sekolah', 'op.edit');
        $barang = $this->barang($sekolah, 'BOS', 100);

        $this->actingAs($operator)->post(route('distribusi-bhp.pengajuan.store'), [
            'sumber_dana' => 'BOS',
            'tanggal' => '2026-06-17',
            'items' => [['barang_id' => $barang->id, 'jumlah_diminta' => 5]],
        ])->assertRedirect();

        $pengajuan = Pengajuan::firstOrFail();

        $this->actingAs($operator)->put(route('distribusi-bhp.pengajuan.update', $pengajuan), [
            'sumber_dana' => 'BOS',
            'tanggal' => '2026-06-20',
            'items' => [['barang_id' => $barang->id, 'jumlah_diminta' => 8]],
        ])->assertRedirect();

        $this->assertSame('2026-06-20', $pengajuan->fresh()->tanggal->toDateString());
        $this->assertSame(8, $pengajuan->fresh()->details()->sum('jumlah_diminta'));

        $this->actingAs($operator)->delete(route('distribusi-bhp.pengajuan.destroy', $pengajuan))
            ->assertRedirect();

        $this->assertSame(0, Pengajuan::count());
    }

    public function test_pengajuan_yang_sudah_submit_tidak_dapat_diubah_atau_dihapus(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->user($sekolah, 'operator_sekolah', 'op.locked');
        $barang = $this->barang($sekolah, 'BOS', 100);

        $pengajuan = Pengajuan::create([
            'sekolah_id' => $sekolah->id,
            'user_id' => $operator->id,
            'sumber_dana' => 'BOS',
            'status' => 'submitted',
            'tanggal' => '2026-06-17',
        ]);
        $pengajuan->details()->create(['barang_id' => $barang->id, 'jumlah_diminta' => 5]);

        $this->actingAs($operator)->put(route('distribusi-bhp.pengajuan.update', $pengajuan), [
            'sumber_dana' => 'BOS',
            'tanggal' => '2026-06-20',
            'items' => [['barang_id' => $barang->id, 'jumlah_diminta' => 8]],
        ])->assertSessionHas('error');

        $this->actingAs($operator)->delete(route('distribusi-bhp.pengajuan.destroy', $pengajuan))
            ->assertSessionHas('error');

        $this->assertSame('submitted', $pengajuan->fresh()->status);
        $this->assertSame(5, $pengajuan->fresh()->details()->sum('jumlah_diminta'));
    }

    public function test_update_draft_sekolah_lain_ditolak(): void
    {
        $sekolahA = $this->sekolah('SMKN Upd A');
        $sekolahB = $this->sekolah('SMKN Upd B');
        $operatorA = $this->user($sekolahA, 'operator_sekolah', 'op.up.a');
        $operatorB = $this->user($sekolahB, 'operator_sekolah', 'op.up.b');
        $barang = $this->barang($sekolahB, 'BOS', 100);

        $pengajuanB = Pengajuan::create([
            'sekolah_id' => $sekolahB->id,
            'user_id' => $operatorB->id,
            'sumber_dana' => 'BOS',
            'status' => 'draft',
            'tanggal' => '2026-06-17',
        ]);
        $pengajuanB->details()->create(['barang_id' => $barang->id, 'jumlah_diminta' => 5]);

        $this->actingAs($operatorA)->put(route('distribusi-bhp.pengajuan.update', $pengajuanB), [
            'sumber_dana' => 'BOS',
            'tanggal' => '2026-06-20',
            'items' => [['barang_id' => $barang->id, 'jumlah_diminta' => 8]],
        ])->assertForbidden();

        $this->assertSame(5, $pengajuanB->fresh()->details()->sum('jumlah_diminta'));
    }

    public function test_user_dengan_divisi_sekolah_lain_ditolak_saat_membuat_pengajuan(): void
    {
        $sekolahA = $this->sekolah('SMKN Div A');
        $sekolahB = $this->sekolah('SMKN Div B');
        $barang = $this->barang($sekolahA, 'BOS', 100);

        $divisiB = Divisi::create(['sekolah_id' => $sekolahB->id, 'nama' => 'TKJ B']);

        // User terikat sekolah A tetapi divisi_id menunjuk divisi sekolah B.
        $user = User::create([
            'name' => 'Operator Nyasar',
            'username' => 'op.nyasar',
            'password' => bcrypt('password'),
            'sekolah_id' => $sekolahA->id,
            'divisi_id' => $divisiB->id,
            'password_changed_at' => now(),
        ]);
        $user->assignRole('operator_sekolah');

        $this->actingAs($user)->post(route('distribusi-bhp.pengajuan.store'), [
            'sumber_dana' => 'BOS',
            'tanggal' => '2026-06-17',
            'items' => [['barang_id' => $barang->id, 'jumlah_diminta' => 5]],
        ])->assertSessionHasErrors('divisi_id');

        $this->assertSame(0, Pengajuan::count());
    }

    public function test_review_approve_sekolah_lain_ditolak(): void
    {
        $sekolahA = $this->sekolah('SMKN Rev A');
        $sekolahB = $this->sekolah('SMKN Rev B');
        $adminA = $this->user($sekolahA, 'operator_sekolah', 'op.rev.a');
        $operatorB = $this->user($sekolahB, 'operator_sekolah', 'op.rev.b');
        $barang = $this->barang($sekolahB, 'BOS', 100);

        $pengajuanB = Pengajuan::create([
            'sekolah_id' => $sekolahB->id,
            'user_id' => $operatorB->id,
            'sumber_dana' => 'BOS',
            'status' => 'submitted',
            'tanggal' => '2026-06-17',
        ]);
        $detail = $pengajuanB->details()->create([
            'barang_id' => $barang->id,
            'jumlah_diminta' => 10,
            'status_reservasi' => 'direservasi',
            'jumlah_direservasi' => 10,
        ]);

        $this->actingAs($adminA)
            ->post(route('distribusi-bhp.review-pengajuan.approve', $pengajuanB), [
                'jumlah_disetujui' => [$detail->id => 10],
            ])
            ->assertForbidden();

        $this->assertSame('submitted', $pengajuanB->fresh()->status);
    }

    public function test_review_reject_sekolah_lain_ditolak(): void
    {
        $sekolahA = $this->sekolah('SMKN Rej A');
        $sekolahB = $this->sekolah('SMKN Rej B');
        $adminA = $this->user($sekolahA, 'operator_sekolah', 'op.rej.a');
        $operatorB = $this->user($sekolahB, 'operator_sekolah', 'op.rej.b');
        $barang = $this->barang($sekolahB, 'BOS', 100);

        $pengajuanB = Pengajuan::create([
            'sekolah_id' => $sekolahB->id,
            'user_id' => $operatorB->id,
            'sumber_dana' => 'BOS',
            'status' => 'submitted',
            'tanggal' => '2026-06-17',
        ]);
        $pengajuanB->details()->create([
            'barang_id' => $barang->id,
            'jumlah_diminta' => 10,
            'status_reservasi' => 'direservasi',
            'jumlah_direservasi' => 10,
        ]);

        $this->actingAs($adminA)
            ->post(route('distribusi-bhp.review-pengajuan.reject', $pengajuanB), [
                'rejection_reason' => 'Bukan wewenang saya',
            ])
            ->assertForbidden();

        $this->assertSame('submitted', $pengajuanB->fresh()->status);
    }

    public function test_submit_mengabaikan_sekolah_id_palsu_dari_request(): void
    {
        $sekolahA = $this->sekolah('SMKN Fake A');
        $sekolahB = $this->sekolah('SMKN Fake B');
        $operatorA = $this->user($sekolahA, 'operator_sekolah', 'op.fake');
        $barang = $this->barang($sekolahA, 'BOS', 100);

        // User A menyisipkan sekolah_id sekolah B; harus diabaikan total.
        $this->actingAs($operatorA)->post(route('distribusi-bhp.pengajuan.store'), [
            'sekolah_id' => $sekolahB->id,
            'sumber_dana' => 'BOS',
            'tanggal' => '2026-06-17',
            'items' => [['barang_id' => $barang->id, 'jumlah_diminta' => 5]],
        ])->assertRedirect();

        $pengajuan = Pengajuan::firstOrFail();
        $this->assertSame($sekolahA->id, $pengajuan->sekolah_id);
    }

    public function test_operator_dapat_melihat_dokumen_pengajuan_sendiri(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->user($sekolah, 'operator_sekolah', 'op.doc');
        $barang = $this->barang($sekolah, 'BOS', 100);

        $pengajuan = Pengajuan::create([
            'sekolah_id' => $sekolah->id,
            'user_id' => $operator->id,
            'sumber_dana' => 'BOS',
            'status' => 'approved',
            'tanggal' => '2026-06-17',
        ]);
        $pengajuan->details()->create([
            'barang_id' => $barang->id,
            'jumlah_diminta' => 5,
            'jumlah_disetujui' => 5,
        ]);

        $this->actingAs($operator)
            ->get(route('distribusi-bhp.dokumen.show', $pengajuan))
            ->assertOk();
    }

    public function test_operator_tidak_dapat_melihat_dokumen_sekolah_lain(): void
    {
        $sekolahA = $this->sekolah('SMKN Doc A');
        $sekolahB = $this->sekolah('SMKN Doc B');
        $operatorA = $this->user($sekolahA, 'operator_sekolah', 'op.doc.a');
        $operatorB = $this->user($sekolahB, 'operator_sekolah', 'op.doc.b');
        $barang = $this->barang($sekolahB, 'BOS', 100);

        $pengajuanB = Pengajuan::create([
            'sekolah_id' => $sekolahB->id,
            'user_id' => $operatorB->id,
            'sumber_dana' => 'BOS',
            'status' => 'approved',
            'tanggal' => '2026-06-17',
        ]);
        $pengajuanB->details()->create([
            'barang_id' => $barang->id,
            'jumlah_diminta' => 5,
            'jumlah_disetujui' => 5,
        ]);

        $this->actingAs($operatorA)
            ->get(route('distribusi-bhp.dokumen.show', $pengajuanB))
            ->assertForbidden();
    }

    public function test_user_tanpa_permission_bhp_ditolak(): void
    {
        $sekolah = $this->sekolah();
        $user = User::create([
            'name' => 'Tanpa Role',
            'username' => 'norole',
            'password' => bcrypt('password'),
            'sekolah_id' => $sekolah->id,
            'password_changed_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('distribusi-bhp.dashboard'))
            ->assertForbidden();
    }
}
