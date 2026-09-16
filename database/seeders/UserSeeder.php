<?php

namespace Database\Seeders;

use App\Models\Master\Sekolah;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * CATATAN KEAMANAN (disetujui, risiko diterima sementara):
     * Password default di bawah sengaja plaintext karena merupakan kredensial
     * awal distribusi. Wajib diganti operator saat login pertama: user dengan
     * password_changed_at = null dipaksa middleware EnsurePasswordNotExpired
     * ke halaman /ubah-password sebelum bisa mengakses data.
     *
     * JANGAN jalankan seeder ini di produksi setelah go-live — updateOrCreate
     * akan menimpa password yang sudah diganti user. Gunakan hanya untuk
     * inisialisasi awal atau reset paksa.
     */
    public function run(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin_kcd', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'operator_sekolah', 'guard_name' => 'web']);

        $dev = User::updateOrCreate(
            ['username' => 'developer'],
            [
                'name' => 'Developer / Super Admin',
                'password' => Hash::make('#SidiptaBeuKCD10'),
                'is_active' => true,
                'password_changed_at' => now(),
            ]
        );
        $dev->syncRoles(['super_admin']);

        $admin = User::updateOrCreate(
            ['username' => 'admin_kcd'],
            [
                'name' => 'Administrator KCD X',
                'password' => Hash::make('#SidiptaBeuKCD10'),
                'is_active' => true,
                'password_changed_at' => now(),
            ]
        );
        $admin->syncRoles(['admin_kcd']);

        $sekolahs = Sekolah::whereNotNull('npsn')->get();
        foreach ($sekolahs as $sekolah) {
            $user = User::updateOrCreate(
                ['username' => "{$sekolah->npsn}-admin"],
                [
                    'name' => $sekolah->nama_sekolah,
                    'password' => Hash::make('#SidiptaKCD10'),
                    'sekolah_id' => $sekolah->id,
                    'is_active' => true,
                    'password_changed_at' => null,
                ]
            );
            $user->syncRoles(['operator_sekolah']);
        }
    }
}
