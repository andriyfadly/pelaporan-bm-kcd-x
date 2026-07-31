<?php

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    foreach (['role_has_permissions', 'model_has_roles', 'model_has_permissions', 'roles', 'permissions', 'realisasi_barang_sekolah', 'laporan_realisasi', 'master_barang_sekolah', 'data_barang_acuan', 'kode_sekolah', 'users'] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('username')->unique();
        $table->string('password');
        $table->enum('role', ['admin', 'user']);
        $table->unsignedInteger('id_sekolah')->nullable();
        $table->string('nama_sekolah')->nullable();
    });
    Schema::create('kode_sekolah', function (Blueprint $table): void {
        $table->id();
        $table->string('nama_sekolah');
    });
    Schema::create('data_barang_acuan', function (Blueprint $table): void {
        $table->id();
        $table->string('id_sekolah');
        $table->string('satuan_pendidikan');
        $table->decimal('nominal', 15, 2);
        $table->integer('bulan');
        $table->date('tanggal')->nullable();
        $table->timestamp('created_at')->nullable();
    });
    Schema::create('master_barang_sekolah', function (Blueprint $table): void {
        $table->id();
        $table->string('id_sekolah');
        $table->integer('bulan_realisasi');
        $table->string('no_spk')->nullable();
        $table->decimal('nilai_perolehan', 15, 2);
    });
    Schema::create('laporan_realisasi', function (Blueprint $table): void {
        $table->id();
        $table->string('id_sekolah');
        $table->integer('bulan');
        $table->string('status');
    });
    Schema::create('realisasi_barang_sekolah', function (Blueprint $table): void {
        $table->id();
        $table->string('id_sekolah');
        $table->integer('bulan_realisasi');
        $table->date('ba_tgl')->nullable();
        $table->timestamp('created_at')->nullable();
        $table->boolean('is_realisasi')->default(false);
    });

    $migration = require database_path('migrations/2026_07_30_162859_create_permission_tables.php');
    $migration->up();

    DB::table('kode_sekolah')->insert([
        ['id' => 1, 'nama_sekolah' => 'Sekolah A'],
        ['id' => 2, 'nama_sekolah' => 'Sekolah B'],
    ]);
});

it('redirects guests and routes authenticated roles to their dashboard', function (): void {
    $this->get('/')->assertRedirect('/login');

    $user = legacyUser('operator', 'user', 1);
    $admin = legacyUser('admin', 'admin', null);

    $this->actingAs($user)->get('/')->assertRedirectToRoute('dashboard');
    $this->actingAs($admin)->get('/')->assertRedirectToRoute('admin.dashboard');
});

it('forbids users from the other role dashboard', function (): void {
    $user = legacyUser('operator', 'user', 1);
    $admin = legacyUser('admin', 'admin', null);

    $this->actingAs($user)->get('/admin/dashboard')->assertForbidden();
    $this->actingAs($admin)->get('/dashboard')->assertForbidden();
});

it('shows only the authenticated school aggregates even with a foreign school input', function (): void {
    DB::table('data_barang_acuan')->insert([
        ['id_sekolah' => '1', 'satuan_pendidikan' => 'Sekolah A', 'nominal' => 1000, 'bulan' => 6],
        ['id_sekolah' => '2', 'satuan_pendidikan' => 'Sekolah B', 'nominal' => 9000, 'bulan' => 6],
    ]);
    DB::table('master_barang_sekolah')->insert([
        ['id_sekolah' => '1', 'bulan_realisasi' => 6, 'no_spk' => 'A-1', 'nilai_perolehan' => 600],
        ['id_sekolah' => '2', 'bulan_realisasi' => 6, 'no_spk' => 'B-1', 'nilai_perolehan' => 8000],
    ]);

    $response = $this->actingAs(legacyUser('operator', 'user', 1))
        ->get('/dashboard?id_sekolah=2');

    $response->assertSuccessful()
        ->assertSee('Sekolah A')
        ->assertSee('1.000')
        ->assertSee('600')
        ->assertDontSee('Sekolah B')
        ->assertDontSee('9.000')
        ->assertDontSee('8.000');
});

it('shows admin progress for the selected month and year', function (): void {
    DB::table('data_barang_acuan')->insert([
        ['id_sekolah' => '1', 'satuan_pendidikan' => 'Sekolah A', 'nominal' => 1000, 'bulan' => 6, 'tanggal' => '2026-06-01', 'created_at' => '2026-06-01 00:00:00'],
        ['id_sekolah' => '2', 'satuan_pendidikan' => 'Sekolah B', 'nominal' => 9000, 'bulan' => 6, 'tanggal' => '2026-06-01', 'created_at' => '2026-06-01 00:00:00'],
    ]);
    DB::table('realisasi_barang_sekolah')->insert([
        'id_sekolah' => '1', 'bulan_realisasi' => 6, 'ba_tgl' => '2026-06-01',
        'created_at' => '2026-06-01 00:00:00', 'is_realisasi' => true,
    ]);

    $this->actingAs(legacyUser('admin', 'admin', null))
        ->get('/admin/dashboard?bulan=6&tahun=2026')
        ->assertSuccessful()
        ->assertSee('Total Sekolah: 2')
        ->assertSee('Selesai: 1')
        ->assertSee('Belum: 1');
});

function legacyUser(string $username, string $role, ?int $schoolId): User
{
    Role::findOrCreate($role, 'web');

    $user = (new User)->forceFill([
        'id' => $role === 'admin' ? 100 : $schoolId,
        'username' => $username,
        'password' => 'unused',
        'role' => $role,
        'id_sekolah' => $schoolId,
    ]);

    $user->save();
    $user->assignRole($role);

    return $user->fresh();
}
