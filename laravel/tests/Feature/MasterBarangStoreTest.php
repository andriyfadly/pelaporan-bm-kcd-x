<?php

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    foreach (['role_has_permissions', 'model_has_roles', 'model_has_permissions', 'roles', 'permissions', 'laporan_realisasi', 'master_barang_sekolah', 'users'] as $table) {
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
        $table->string('id_sekolah');
        $table->unsignedTinyInteger('bulan');
        $table->string('status');
    });
    Schema::create('master_barang_sekolah', function (Blueprint $table): void {
        $table->id();
        $table->uuid('public_id')->unique();
        $table->string('id_sekolah');
        $table->string('kategori')->nullable();
        $table->unsignedInteger('id_uraian')->nullable();
        $table->string('no_sp2d');
        $table->string('sumber_perolehan');
        $table->unsignedTinyInteger('bulan_realisasi');
        $table->string('no_spk');
        $table->string('ba_no')->nullable();
        $table->date('ba_tgl')->nullable();
        $table->string('kode_barang');
        $table->string('nama_barang');
        $table->string('jenis_aset');
        $table->string('merk_tipe')->nullable();
        $table->string('no_sertifikat')->nullable();
        $table->string('ukuran_bangunan')->nullable();
        $table->string('satuan');
        $table->decimal('volume', 10, 2);
        $table->decimal('harga_satuan', 15, 2);
        $table->decimal('nilai_perolehan', 15, 2);
        $table->boolean('is_realisasi')->default(false);
    });

    $migration = require database_path('migrations/2026_07_30_162859_create_permission_tables.php');
    $migration->up();
});

it('creates master barang for authenticated school and calculates value server side', function (): void {
    $response = $this->actingAs(masterBarangUser('operator', 1))
        ->postJson('/master-barang', masterBarangPayload(['id_sekolah' => '2', 'nilai_perolehan' => 1]));

    $response->assertCreated()->assertJsonPath('data.id_sekolah', '1');

    expect(DB::table('master_barang_sekolah')->where('id_sekolah', '1')->value('nilai_perolehan'))->toBe('600000.00')
        ->and(DB::table('master_barang_sekolah')->where('id_sekolah', '2')->count())->toBe(0);
});

it('refuses writes after report submit or approval', function (string $status): void {
    DB::table('laporan_realisasi')->insert(['id_sekolah' => '1', 'bulan' => 6, 'status' => $status]);

    $this->actingAs(masterBarangUser('operator', 1))
        ->postJson('/master-barang', masterBarangPayload())
        ->assertStatus(409);

    expect(DB::table('master_barang_sekolah')->count())->toBe(0);
})->with(['Menunggu Approval', 'Disetujui']);

it('batch updates owned items and deletes only owned items with server calculated money', function (): void {
    $owned = DB::table('master_barang_sekolah')->insertGetId(masterBarangPayload(['id_sekolah' => '1', 'public_id' => '0197a5aa-0000-7000-8000-000000000001', 'nilai_perolehan' => 1]));
    DB::table('master_barang_sekolah')->insert(masterBarangPayload(['id_sekolah' => '2', 'public_id' => '0197a5aa-0000-7000-8000-000000000002', 'nilai_perolehan' => 1]));

    $this->actingAs(masterBarangUser('operator', 1))
        ->putJson('/master-barang/batch', [
            'bulan_realisasi' => 6,
            'items' => [[
                'public_id' => '0197a5aa-0000-7000-8000-000000000001',
                'nama_barang' => 'Laptop Baru',
                'volume' => '3',
                'harga_satuan' => '200000.25',
            ]],
            'delete_ids' => [],
        ])
        ->assertOk();

    expect(DB::table('master_barang_sekolah')->where('id', $owned)->value('nama_barang'))->toBe('Laptop Baru')
        ->and(DB::table('master_barang_sekolah')->where('id', $owned)->value('nilai_perolehan'))->toBe('600000.75')
        ->and(DB::table('master_barang_sekolah')->where('id_sekolah', '2')->count())->toBe(1);
});

it('refuses batch mutations when report is locked', function (): void {
    DB::table('laporan_realisasi')->insert(['id_sekolah' => '1', 'bulan' => 6, 'status' => 'Menunggu Approval']);

    $this->actingAs(masterBarangUser('operator', 1))
        ->putJson('/master-barang/batch', ['bulan_realisasi' => 6, 'items' => [], 'delete_ids' => []])
        ->assertStatus(409);
});

it('refuses a batch when an item is foreign or has been realized', function (): void {
    DB::table('master_barang_sekolah')->insert([
        masterBarangPayload([
            'id_sekolah' => '1',
            'public_id' => '0197a5aa-0000-7000-8000-000000000004',
            'is_realisasi' => true,
            'nilai_perolehan' => 600000,
        ]),
        masterBarangPayload([
            'id_sekolah' => '2',
            'public_id' => '0197a5aa-0000-7000-8000-000000000005',
            'is_realisasi' => false,
            'nilai_perolehan' => 600000,
        ]),
    ]);

    $this->actingAs(masterBarangUser('operator', 1))
        ->putJson('/master-barang/batch', [
            'bulan_realisasi' => 6,
            'items' => [[
                'public_id' => '0197a5aa-0000-7000-8000-000000000005',
                'nama_barang' => 'Foreign item',
                'volume' => 1,
                'harga_satuan' => 1,
            ]],
            'delete_ids' => ['0197a5aa-0000-7000-8000-000000000004'],
        ])
        ->assertForbidden();

    expect(DB::table('master_barang_sekolah')->count())->toBe(2);
});

function masterBarangUser(string $username, int $schoolId): User
{
    Role::findOrCreate('user', 'web');
    $user = User::query()->forceCreate([
        'username' => $username,
        'password' => 'unused',
        'role' => 'user',
        'id_sekolah' => $schoolId,
    ]);
    $user->assignRole('user');

    return $user->fresh();
}

function masterBarangPayload(array $overrides = []): array
{
    return array_replace([
        'kategori' => 'Peralatan & Mesin',
        'no_sp2d' => 'SP2D-1',
        'sumber_perolehan' => 'BOS Reguler',
        'bulan_realisasi' => 6,
        'no_spk' => 'SPK-1',
        'kode_barang' => '1.02',
        'nama_barang' => 'Laptop',
        'jenis_aset' => 'Komputer',
        'satuan' => 'Unit',
        'volume' => 2,
        'harga_satuan' => 300000,
    ], $overrides);
}
