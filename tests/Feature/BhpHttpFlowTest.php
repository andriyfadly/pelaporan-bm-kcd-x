<?php

namespace Tests\Feature;

use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\Penerimaan;
use App\Models\DistribusiBhp\PenerimaanDetail;
use App\Models\DistribusiBhp\Pengajuan;
use App\Models\Master\PejabatSekolah;
use App\Models\Master\Sekolah;
use App\Models\User;
use App\Services\PejabatSekolahService;
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

    private function barang(string $sumberDana = 'BOS', int $stok = 100): Barang
    {
        $barang = Barang::create([
            'kode_barang' => '1.1.7.01.'.$sumberDana,
            'nama_barang' => 'Semen Portland',
            'satuan' => 'Sak',
            'sumber_dana' => $sumberDana,
        ]);

        $penerimaan = Penerimaan::create(['sumber_dana' => $sumberDana, 'is_saldo_awal' => true, 'periode_tahun' => 2026]);
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
        $barang = $this->barang('BOS', 100);

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
        $barang = $this->barang('BOPD', 50);

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.pengajuan.store'), [
                'sumber_dana' => 'BOS',
                'tanggal' => '2026-06-17',
                'items' => [['barang_id' => $barang->id, 'jumlah_diminta' => 5]],
            ])
            ->assertSessionHasErrors();

        $this->assertSame(0, Pengajuan::count());
    }

    public function test_operator_dapat_approve_pengajuan_sekolah_sendiri(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->user($sekolah, 'operator_sekolah', 'op.bhp3');
        $barang = $this->barang('BOS', 100);

        // Approval mensyaratkan pejabat penandatangan lengkap.
        $pejabat = app(PejabatSekolahService::class);
        foreach (PejabatSekolah::JABATAN as $i => $jabatan) {
            $pejabat->simpan((string) $sekolah->id, (string) $operator->id, [
                'jabatan' => $jabatan,
                'nama' => 'Pejabat '.$jabatan,
                'mulai_menjabat' => '2026-01-01',
            ]);
        }

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
            ->assertRedirect();

        $this->assertSame('approved', $pengajuan->fresh()->status);
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
