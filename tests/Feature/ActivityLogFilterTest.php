<?php

namespace Tests\Feature;

use App\Models\Master\Sekolah;
use App\Models\PelaporanBm\Spj;
use App\Models\User;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ActivityLogFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);
    }

    private function superAdmin(): User
    {
        $admin = activity()->withoutLogging(fn () => User::create([
            'name' => 'Super Admin',
            'username' => 'super_filter',
            'password' => bcrypt('Password123!'),
            'password_changed_at' => now(),
        ]));
        $admin->assignRole('super_admin');

        return $admin;
    }

    private function logEntry(array $over = []): Activity
    {
        return Activity::create(array_merge([
            'log_name' => 'sistem',
            'description' => 'aktivitas uji',
            'event' => 'uju-coba',
            'subject_type' => Spj::class,
            'created_at' => now(),
            'updated_at' => now(),
        ], $over));
    }

    public function test_filter_subject_type_menyaring_jenis_subjek(): void
    {
        $admin = $this->superAdmin();
        Activity::query()->delete();

        $this->logEntry(['subject_type' => Spj::class, 'event' => 'a']);
        $this->logEntry(['subject_type' => Sekolah::class, 'event' => 'b']);

        $this->actingAs($admin)
            ->get(route('admin.log-aktivitas.index', ['subject_type' => Sekolah::class]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('items.data', 1)
                ->where('items.data.0.subject_type', Sekolah::class)
            );
    }

    public function test_filter_tanggal_dari_dan_sampai(): void
    {
        $admin = $this->superAdmin();
        Activity::query()->delete();

        $this->logEntry(['event' => 'lama', 'created_at' => '2026-01-01 08:00:00', 'updated_at' => '2026-01-01 08:00:00']);
        $this->logEntry(['event' => 'baru', 'created_at' => '2026-06-15 08:00:00', 'updated_at' => '2026-06-15 08:00:00']);

        // Hanya rentang Juni yang diharapkan.
        $this->actingAs($admin)
            ->get(route('admin.log-aktivitas.index', ['dari' => '2026-06-01', 'sampai' => '2026-06-30']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('items.data', 1)
                ->where('items.data.0.event', 'baru')
            );

        // Hanya dari (>=) tanpa sampai.
        $this->actingAs($admin)
            ->get(route('admin.log-aktivitas.index', ['dari' => '2026-06-01']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('items.data', 1)
                ->where('items.data.0.event', 'baru')
            );

        // Hanya sampai (<=) tanpa dari.
        $this->actingAs($admin)
            ->get(route('admin.log-aktivitas.index', ['sampai' => '2026-01-31']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('items.data', 1)
                ->where('items.data.0.event', 'lama')
            );
    }

    public function test_filter_q_dan_sekolah_dan_event(): void
    {
        $admin = $this->superAdmin();
        Activity::query()->delete();

        $sekolah = activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => 'SMKN Filter', 'kota_kab' => 'Kota Bandung',
        ]));

        $this->logEntry([
            'event' => 'target-event',
            'description' => 'kata kunci unik',
            'properties' => ['sekolah_id' => $sekolah->id, 'ringkasan' => 'ringkas unik'],
        ]);
        $this->logEntry(['event' => 'lain', 'description' => 'sesuatu lain', 'properties' => []]);

        // event
        $this->actingAs($admin)
            ->get(route('admin.log-aktivitas.index', ['event' => 'target-event']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('items.data', 1));

        // q mencocokkan description
        $this->actingAs($admin)
            ->get(route('admin.log-aktivitas.index', ['q' => 'kata kunci']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('items.data', 1));

        // sekolah_id mencocokkan properties->sekolah_id
        $this->actingAs($admin)
            ->get(route('admin.log-aktivitas.index', ['sekolah_id' => $sekolah->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('items.data', 1));
    }
}
