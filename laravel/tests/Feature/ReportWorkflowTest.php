<?php

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    foreach (['role_has_permissions', 'model_has_roles', 'model_has_permissions', 'roles', 'permissions', 'kode_sekolah', 'laporan_realisasi', 'realisasi_barang_sekolah', 'users'] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('username')->unique();
        $table->string('password');
        $table->enum('role', ['admin', 'user']);
        $table->unsignedInteger('id_sekolah')->nullable();
    });
    Schema::create('laporan_realisasi', function (Blueprint $table): void {
        $table->id();
        $table->uuid('public_id')->unique();
        $table->string('id_sekolah');
        $table->unsignedTinyInteger('bulan');
        $table->enum('status', ['Menunggu Approval', 'Disetujui', 'Ditolak']);
        $table->timestamp('tanggal_kirim')->nullable();
        $table->unique(['id_sekolah', 'bulan']);
    });
    Schema::create('realisasi_barang_sekolah', function (Blueprint $table): void {
        $table->id();
        $table->uuid('public_id')->unique();
        $table->string('id_sekolah');
        $table->unsignedTinyInteger('bulan_realisasi');
        $table->string('nama_barang');
        $table->decimal('volume', 10, 2);
        $table->decimal('harga_satuan', 15, 2);
        $table->decimal('nilai_perolehan', 15, 2);
        $table->date('ba_tgl')->nullable();
        $table->string('no_spk')->nullable();
    });
    Schema::create('kode_sekolah', function (Blueprint $table): void {
        $table->id();
        $table->unsignedInteger('id_sekolah')->nullable();
        $table->string('nama_sekolah');
    });

    $migration = require database_path('migrations/2026_07_30_162859_create_permission_tables.php');
    $migration->up();
});

it('creates realization for authenticated school and calculates money server side', function (): void {
    $this->actingAs(reportUser('operator', 'user', 1))
        ->postJson('/realisasi', ['bulan_realisasi' => 6, 'nama_barang' => 'Meja', 'volume' => '2.5', 'harga_satuan' => '100000.20', 'id_sekolah' => 2])
        ->assertCreated()
        ->assertJsonPath('data.id_sekolah', '1');

    expect(DB::table('realisasi_barang_sekolah')->value('nilai_perolehan'))->toBe('250000.50');
});

it('submits then admin approves report through valid transitions', function (): void {
    $user = reportUser('operator', 'user', 1);
    $admin = reportUser('admin', 'admin', null);

    $this->actingAs($user)->postJson('/laporan/6/submit')->assertCreated()->assertJsonPath('data.status', 'Menunggu Approval');
    $this->actingAs($admin)->postJson('/admin/laporan/1/6/approve')->assertOk()->assertJsonPath('data.status', 'Disetujui');
    $this->actingAs($admin)->postJson('/admin/laporan/1/6/reject')->assertStatus(409);
});

it('lets a rejected report reopen school edits and be resubmitted', function (): void {
    $user = reportUser('operator', 'user', 1);
    $admin = reportUser('admin', 'admin', null);

    $this->actingAs($user)->postJson('/laporan/6/submit')->assertCreated();
    $this->actingAs($admin)->postJson('/admin/laporan/1/6/reject')->assertOk()->assertJsonPath('data.status', 'Ditolak');
    $this->actingAs($user)
        ->postJson('/realisasi', ['bulan_realisasi' => 6, 'nama_barang' => 'Meja', 'volume' => 1, 'harga_satuan' => 1])
        ->assertCreated();
    $this->actingAs($user)->postJson('/laporan/6/submit')->assertCreated()->assertJsonPath('data.status', 'Menunggu Approval');
});

it('denies realization mutations after report submission', function (): void {
    DB::table('laporan_realisasi')->insert(['public_id' => '0197a5aa-0000-7000-8000-000000000003', 'id_sekolah' => '1', 'bulan' => 6, 'status' => 'Menunggu Approval']);

    $this->actingAs(reportUser('operator', 'user', 1))
        ->postJson('/realisasi', ['bulan_realisasi' => 6, 'nama_barang' => 'Meja', 'volume' => 1, 'harga_satuan' => 1])
        ->assertStatus(409);
});

it('exports formula-safe admin CSV for the selected reporting period', function (): void {
    DB::table('kode_sekolah')->insert(['id_sekolah' => 1, 'nama_sekolah' => '=Sekolah A']);
    DB::table('realisasi_barang_sekolah')->insert([
        'public_id' => '0197a5aa-0000-7000-8000-000000000004',
        'id_sekolah' => '1',
        'bulan_realisasi' => 6,
        'nama_barang' => '+Laptop',
        'volume' => 2,
        'harga_satuan' => 300000,
        'nilai_perolehan' => 600000,
        'ba_tgl' => '2026-06-01',
        'no_spk' => '@SPK-1',
    ]);

    $response = $this->actingAs(reportUser('admin', 'admin', null))
        ->get('/admin/laporan/export?bulan=6&tahun=2026')
        ->assertSuccessful()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertHeader('content-disposition', 'attachment; filename=belanja-modal-2026-06.csv');

    expect($response->streamedContent())
        ->toContain("'=Sekolah A")
        ->toContain("'+Laptop")
        ->toContain("'@SPK-1");
});

it('denies report export to school users', function (): void {
    $this->actingAs(reportUser('operator', 'user', 1))
        ->get('/admin/laporan/export?bulan=6&tahun=2026')
        ->assertForbidden();
});

function reportUser(string $username, string $role, ?int $schoolId): User
{
    Role::findOrCreate($role, 'web');
    $user = User::query()->forceCreate(['username' => $username, 'password' => 'unused', 'role' => $role, 'id_sekolah' => $schoolId]);
    $user->assignRole($role);

    return $user->fresh();
}
