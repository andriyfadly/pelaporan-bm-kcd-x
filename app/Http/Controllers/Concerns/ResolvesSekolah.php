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

        if ($user->sekolah_id) {
            return $user->sekolah_id;
        }

        if ($this->isSekolahUser($user)) {
            abort(403, 'Akun Anda belum terhubung ke sekolah mana pun.');
        }

        return $request->input('sekolah_id') ?: null;
    }

    protected function isSekolahUser(User $user): bool
    {
        return $user->hasRole(['operator_sekolah', 'bendahara_sekolah']);
    }
}
