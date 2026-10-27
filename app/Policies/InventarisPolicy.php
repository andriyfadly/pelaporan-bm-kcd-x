<?php

namespace App\Policies;

use App\Models\PencatatanBm\InventarisUnit;
use App\Models\PencatatanBm\Pegawai;
use App\Models\PencatatanBm\Ruang;
use App\Models\User;

/**
 * Policy modul Pencatatan BM: hanya gerbang permission. Batas tenant ditegakkan
 * oleh scope query di controller (data sekolah lain = 404, bukan 403).
 */
class InventarisPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('lihat-pencatatan-bm');
    }

    public function view(User $user, InventarisUnit $unit): bool
    {
        return $user->can('lihat-pencatatan-bm');
    }

    public function update(User $user, InventarisUnit $unit): bool
    {
        return $user->can('kelola-inventaris-bm');
    }

    public function mutasi(User $user, InventarisUnit $unit): bool
    {
        return $user->can('mutasi-inventaris-bm');
    }

    public function kembalikan(User $user, InventarisUnit $unit): bool
    {
        return $user->can('proses-pengembalian-bm');
    }

    public function cetak(User $user): bool
    {
        return $user->can('cetak-inventaris-bm');
    }

    public function kelolaRuang(User $user, Ruang $ruang): bool
    {
        return $user->can('kelola-ruang-bm');
    }

    public function kelolaPegawai(User $user, Pegawai $pegawai): bool
    {
        return $user->can('kelola-pegawai-bm');
    }
}
