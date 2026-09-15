<?php

namespace Database\Seeders;

use App\Models\Master\Sekolah;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['username' => 'admin_kcd'],
            [
                'name' => 'Administrator KCD X',
                'password' => Hash::make('password'),
                'is_active' => true,
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
