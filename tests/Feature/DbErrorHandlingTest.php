<?php

namespace Tests\Feature;

use App\Models\Master\Sekolah;
use App\Models\User;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DbErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);

        Route::middleware('web')->group(function () {
            Route::get('/__db-gagal', function () {
                throw new QueryException('sqlite', 'select 1', [], new \Exception('boom'));
            })->name('__db-gagal');
        });
    }

    private function operator(): User
    {
        $sekolah = Sekolah::create(['nama_sekolah' => 'SMKN 1 DB', 'kota_kab' => 'Kota Bandung']);
        $user = User::create([
            'name' => 'Operator',
            'username' => 'op_dberror',
            'password' => bcrypt('Password123!'),
            'sekolah_id' => $sekolah->id,
            'password_changed_at' => now(),
        ]);
        $user->assignRole('operator_sekolah');

        return $user;
    }

    public function test_db_error_web_kembali_dengan_pesan_dan_tercatat_di_file_log(): void
    {
        Log::spy();

        $response = $this->actingAs($this->operator())->from('/dashboard')->get('/__db-gagal');

        $response->assertRedirect('/dashboard');
        $response->assertSessionHas('error', 'Terjadi gangguan database. Coba lagi beberapa saat.');
        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $pesan, array $konteks) => str_starts_with($pesan, 'db-error: ')
                && ($konteks['route'] ?? null) === '__db-gagal'
                && ($konteks['username'] ?? null) === 'op_dberror'
                && array_key_exists('sql', $konteks));
    }

    public function test_db_error_json_500_tanpa_sql_bocor(): void
    {
        $response = $this->actingAs($this->operator())->getJson('/__db-gagal');

        $response->assertStatus(500);
        $response->assertJson(['message' => 'Terjadi gangguan database. Coba lagi beberapa saat.']);
        $this->assertStringNotContainsString('select 1', $response->getContent());
    }
}
