<?php

namespace Tests\Feature;

use App\Models\Master\KodeBarang;
use App\Models\User;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class KodeBarangTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);
    }

    public function test_admin_can_manage_and_import_kode_barang(): void
    {
        $admin = User::create([
            'name' => 'Admin KCD',
            'username' => 'admin_kb',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin_kcd');

        // Index without & with search
        $this->actingAs($admin)
            ->get(route('admin.kode-barang.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.kode-barang.index', ['search' => 'Laptop']))
            ->assertOk();

        // Store
        $this->actingAs($admin)
            ->post(route('admin.kode-barang.store'), [
                'kode_barang' => '1.3.2.05.01',
                'uraian' => 'Laptop Core i7',
                'kodering_aset' => '5.2.02.01',
                'jenis_aset' => 'Peralatan dan Mesin',
                'umur_ekonomis' => 5,
                'satuan' => 'Unit',
                'harga_standar' => 15000000,
            ])
            ->assertRedirect();

        $kb = KodeBarang::where('kode_barang', '1.3.2.05.01')->first();
        $this->assertNotNull($kb);
        $this->assertEquals('5.2.02.01', $kb->kodering_aset);
        $this->assertEquals(5, $kb->umur_ekonomis);

        // Ajax Search
        $this->actingAs($admin)
            ->get(route('admin.kode-barang.index', ['ajax' => 1, 'q' => 'Laptop']))
            ->assertOk()
            ->assertJsonFragment(['kode_barang' => '1.3.2.05.01']);

        // Import CSV
        $csvData = "Kode,Uraian,Kodering,Jenis,Umur,Satuan,Harga\n1.3.2.05.02,Komputer PC,5.2.02.02,Peralatan dan Mesin,5,Unit,\"10,000,000\"\n";
        $file = UploadedFile::fake()->createWithContent('kode_barang.csv', $csvData);

        $this->actingAs($admin)
            ->post(route('admin.kode-barang.import'), [
                'file' => $file,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('master_data_kode_barang', [
            'kode_barang' => '1.3.2.05.02',
            'kodering_aset' => '5.2.02.02',
        ]);

        // Destroy
        $this->actingAs($admin)
            ->delete(route('admin.kode-barang.destroy', $kb))
            ->assertRedirect();

        $this->assertDatabaseMissing('master_data_kode_barang', [
            'id' => $kb->id,
        ]);
    }
}
