<?php

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    foreach (['role_has_permissions', 'model_has_roles', 'model_has_permissions', 'roles', 'permissions', 'data_barang_acuan', 'users'] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('username')->unique();
        $table->string('password');
        $table->enum('role', ['admin', 'user']);
        $table->unsignedInteger('id_sekolah')->nullable();
    });
    Schema::create('data_barang_acuan', function (Blueprint $table): void {
        $table->id();
        $table->string('id_sekolah');
        $table->string('satuan_pendidikan');
        $table->string('npsn')->nullable();
        $table->date('tanggal')->nullable();
        $table->string('kodering')->nullable();
        $table->string('bku')->nullable();
        $table->text('uraian')->nullable();
        $table->decimal('nominal', 15, 2)->default(0);
        $table->string('bulan')->nullable();
        $table->timestamp('created_at')->nullable();
    });
    config()->set('database.connections.inventory', config('database.connections.mysql'));
    DB::purge('inventory');
    Schema::connection('inventory')->dropIfExists('kode_barang');
    Schema::connection('inventory')->create('kode_barang', function (Blueprint $table): void {
        $table->id();
        $table->string('kode_barang');
        $table->string('uraian');
        $table->string('kodering_aset')->nullable();
        $table->string('jenis_aset')->nullable();
        $table->unsignedInteger('umur_ekonomis')->nullable();
    });

    $migration = require database_path('migrations/2026_07_30_162859_create_permission_tables.php');
    $migration->up();
});

it('scopes acuan rows and aggregates to the authenticated school', function (): void {
    DB::table('data_barang_acuan')->insert([
        ['id_sekolah' => '1', 'satuan_pendidikan' => 'Sekolah A', 'npsn' => 'A', 'uraian' => 'Acuan Milik A', 'nominal' => 1000, 'bulan' => 'Juni'],
        ['id_sekolah' => '2', 'satuan_pendidikan' => 'Sekolah B', 'npsn' => 'B', 'uraian' => 'Acuan Milik B', 'nominal' => 9000, 'bulan' => 'Juni'],
    ]);

    $this->actingAs(readUser('operator', 1))
        ->get('/acuan?id_sekolah=2&bulan=Juni')
        ->assertSuccessful()
        ->assertSee('Acuan Milik A')
        ->assertSee('1.000')
        ->assertDontSee('Acuan Milik B')
        ->assertDontSee('9.000');
});

it('returns matching inventory leaf items for authenticated users', function (): void {
    DB::connection('inventory')->table('kode_barang')->insert([
        ['kode_barang' => '1.01', 'uraian' => 'Laptop Induk', 'kodering_aset' => null, 'jenis_aset' => null, 'umur_ekonomis' => null],
        ['kode_barang' => '1.01.01', 'uraian' => 'Laptop Anak', 'kodering_aset' => null, 'jenis_aset' => null, 'umur_ekonomis' => null],
        ['kode_barang' => '1.02', 'uraian' => 'Laptop Daun', 'kodering_aset' => 'A', 'jenis_aset' => 'Komputer', 'umur_ekonomis' => 4],
    ]);

    $this->actingAs(readUser('operator', 1))
        ->getJson('/inventory/kode-barang?q=laptop')
        ->assertSuccessful()
        ->assertJsonCount(2)
        ->assertJsonFragment(['kode_barang' => '1.01.01', 'nama_barang' => 'Laptop Anak'])
        ->assertJsonFragment(['kode_barang' => '1.02', 'nama_barang' => 'Laptop Daun'])
        ->assertJsonMissing(['kode_barang' => '1.01']);
});

function readUser(string $username, int $schoolId): User
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
