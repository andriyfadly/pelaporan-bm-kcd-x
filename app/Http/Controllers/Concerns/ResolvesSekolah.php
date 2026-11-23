<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use Illuminate\Http\Request;

trait ResolvesSekolah
{
    /**
     * Tenant scoping ketat: user sekolah selalu dipaksa ke sekolahnya sendiri,
     * input sekolah_id dari request diabaikan. Hanya admin (tanpa sekolah_id)
     * yang boleh memilih sekolah via parameter.
     */
    protected function resolveSekolahId(Request $request): ?string
    {
        $user = $request->user();

        if ($this->isSekolahUser($user)) {
            if ($user->sekolah_id) {
                return $user->sekolah_id;
            }

            abort(403, 'Akun Anda belum terhubung ke sekolah mana pun.');
        }

        if ($user->sekolah_id) {
            return $user->sekolah_id;
        }

        return $request->input('sekolah_id') ?: null;
    }

    /**
     * Sama seperti resolveSekolahId(), tetapi WAJIB menghasilkan sekolah.
     * Dipakai domain yang tidak mengenal pool pusat (stok/saldo awal BHP):
     * pemakaian data lintas sekolah tanpa konteks sekolah harus ditolak.
     */
    protected function resolveSekolahIdWajib(Request $request): string
    {
        $sekolahId = $this->resolveSekolahId($request);

        if ($sekolahId === null || trim((string) $sekolahId) === '') {
            abort(422, 'Operasi ini memerlukan konteks sekolah. Pilih sekolah terlebih dahulu.');
        }

        return (string) $sekolahId;
    }

    protected function isSekolahUser(User $user): bool
    {
        return $user->hasRole(['operator_sekolah', 'bendahara_sekolah']) || (bool) $user->sekolah_id;
    }
}
