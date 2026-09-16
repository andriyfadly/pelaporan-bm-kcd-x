<?php

namespace Tests\Feature;

use App\Models\Master\Sekolah;
use App\Models\PelaporanBm\Acuan;
use App\Models\PelaporanBm\Spj;
use App\Models\User;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);
    }

    private function buatSuperAdmin(): User
    {
        $admin = activity()->withoutLogging(fn () => User::create([
            'name' => 'Super Admin',
            'username' => 'super_audit',
            'password' => bcrypt('password'),
        ]));
        $admin->assignRole('super_admin');

        return $admin;
    }

    public function test_spj_create_update_destroy_tercatat(): void
    {
        $admin = $this->buatSuperAdmin();
        $sekolah = Sekolah::create(['nama_sekolah' => 'SMKN 1 Audit', 'kota_kab' => 'Bandung']);
        Activity::query()->delete();

        $this->actingAs($admin)->post(route('pelaporan-bm.spj.store'), [
            'no_spk' => 'SPK-AUDIT',
            'bulan_realisasi' => 5,
            'kode_barang' => '1.3.2.05',
            'nama_barang' => 'Laptop',
            'jenis_aset' => 'Peralatan dan Mesin',
            'volume' => 2,
            'harga_satuan' => 10000000,
        ])->assertRedirect();

        $spj = Spj::where('no_spk', 'SPK-AUDIT')->first();
        $this->assertNotNull($spj);
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'sistem',
            'event' => 'created',
            'subject_type' => Spj::class,
            'subject_id' => $spj->id,
        ]);

        $this->actingAs($admin)->put(route('pelaporan-bm.spj.update', $spj), [
            'no_spk' => 'SPK-AUDIT',
            'bulan_realisasi' => 5,
            'kode_barang' => '1.3.2.05',
            'nama_barang' => 'Laptop Pro',
            'jenis_aset' => 'Peralatan dan Mesin',
            'volume' => 2,
            'harga_satuan' => 10000000,
        ])->assertRedirect();

        $updated = Activity::inLog('sistem')->forEvent('updated')
            ->where('subject_type', Spj::class)->latest('id')->first();
        $this->assertNotNull($updated);
        $this->assertArrayHasKey('old', $updated->attribute_changes ?? []);
        $this->assertEquals('Laptop', $updated->attribute_changes['old']['nama_barang']);

        $this->actingAs($admin)->delete(route('pelaporan-bm.spj.destroy', $spj))
            ->assertRedirect();
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'sistem',
            'event' => 'deleted',
            'subject_type' => Spj::class,
        ]);
    }

    public function test_login_dan_kunci_laporan_tercatat(): void
    {
        $admin = $this->buatSuperAdmin();
        $sekolah = Sekolah::create(['nama_sekolah' => 'SMKN 2 Audit', 'kota_kab' => 'Cimahi']);
        Activity::query()->delete();

        $this->post('/login', ['username' => 'super_audit', 'password' => 'password'])
            ->assertRedirect();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'sistem',
            'event' => 'login',
        ]);

        $this->actingAs($admin)->post(route('pelaporan-bm.kunci-laporan.toggle'), [
            'sekolah_id' => $sekolah->id,
            'bulan' => 5,
        ])->assertRedirect();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'sistem',
            'event' => 'kunci-laporan',
        ]);
    }

    public function test_password_tidak_bocor_di_log(): void
    {
        $this->buatSuperAdmin();
        Activity::query()->delete();

        $user = activity()->withoutLogging(fn () => User::create([
            'name' => 'Operator X',
            'username' => 'op_x',
            'password' => bcrypt('password'),
        ]));

        $logs = Activity::inLog('sistem')->get();
        foreach ($logs as $log) {
            $json = json_encode([$log->attribute_changes, $log->properties]);
            $this->assertStringNotContainsStringIgnoringCase('password', $json);
        }

        $user->forceFill(['name' => 'Operator Y'])->save();
        $updated = Activity::inLog('sistem')->forEvent('updated')
            ->where('subject_type', User::class)->latest('id')->first();
        $this->assertNotNull($updated);
        $changes = $updated->attribute_changes['attributes'] ?? [];
        $this->assertArrayNotHasKey('password', $changes);
    }

    public function test_viewer_hanya_super_admin(): void
    {
        $super = $this->buatSuperAdmin();

        $admin = activity()->withoutLogging(fn () => User::create([
            'name' => 'Admin KCD',
            'username' => 'adm_kcd_audit',
            'password' => bcrypt('password'),
        ]));
        $admin->assignRole('admin_kcd');

        $sekolah = Sekolah::create(['nama_sekolah' => 'SMKN 3 Audit', 'kota_kab' => 'Bandung']);
        $operator = activity()->withoutLogging(fn () => User::create([
            'name' => 'Operator',
            'username' => 'op_audit',
            'password' => bcrypt('password'),
            'password_changed_at' => now(),
            'sekolah_id' => $sekolah->id,
        ]));
        $operator->assignRole('operator_sekolah');

        Acuan::create([
            'sekolah_id' => $sekolah->id,
            'bulan' => 5,
            'uraian' => 'Acuan audit',
            'nominal' => 1000,
        ]);

        $this->actingAs($super)->get(route('admin.log-aktivitas.index'))->assertOk();
        $this->actingAs($super)->get(route('admin.log-aktivitas.index', ['event' => 'created']))->assertOk();

        $this->actingAs($admin)->get(route('admin.log-aktivitas.index'))->assertForbidden();
        $this->actingAs($operator)->get(route('admin.log-aktivitas.index'))->assertForbidden();
    }
}
