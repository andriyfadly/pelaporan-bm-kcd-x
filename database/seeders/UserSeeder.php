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
                'email' => 'admin.kcd@disdik.jabarprov.go.id',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );
        $admin->assignRole('admin_kcd');

        $sekolah = Sekolah::firstOrCreate(
            ['nama_sekolah' => 'SMKN 1 Contoh'],
            ['kota_kab' => 'Kota Bandung']
        );

        $operator = User::firstOrCreate(
            ['username' => 'operator_smkn1'],
            [
                'name' => 'Operator SMKN 1 Contoh',
                'email' => 'smkn1@sekolah.belajar.id',
                'password' => Hash::make('password'),
                'sekolah_id' => $sekolah->id,
                'is_active' => true,
            ]
        );
        $operator->assignRole('operator_sekolah');
    }
}
