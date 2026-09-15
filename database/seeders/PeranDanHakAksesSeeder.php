<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PeranDanHakAksesSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $hakAkses = [
            'lihat-laporan-bm',
            'input-spj-bm',
            'edit-spj-bm',
            'hapus-spj-bm',
            'verifikasi-laporan-bm',
            'kunci-laporan-bm',
            'unduh-rekap-bm',
            'cetak-laporan-bm',
            'kelola-user',
        ];

        foreach ($hakAkses as $hak) {
            Permission::firstOrCreate(['name' => $hak, 'guard_name' => 'web']);
        }

        $adminKcd = Role::firstOrCreate(['name' => 'admin_kcd', 'guard_name' => 'web']);
        $adminKcd->syncPermissions($hakAkses);

        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::all());

        $operatorSekolah = Role::firstOrCreate(['name' => 'operator_sekolah', 'guard_name' => 'web']);
        $operatorSekolah->syncPermissions([
            'lihat-laporan-bm',
            'input-spj-bm',
            'edit-spj-bm',
            'hapus-spj-bm',
            'cetak-laporan-bm',
        ]);
    }
}
