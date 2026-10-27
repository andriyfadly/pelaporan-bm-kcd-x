<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

trait TenantScope
{
    /**
     * Data sekolah lain dianggap tidak ada (404): UUID sekolah lain tidak boleh
     * membocorkan keberadaan unit/ruang/pegawai.
     */
    protected function pastikanSekolahSendiri(Request $request, ?string $sekolahId): void
    {
        $sekolahPengguna = $this->resolveSekolahId($request);

        if ($sekolahPengguna !== null && $sekolahPengguna !== $sekolahId) {
            abort(404);
        }
    }
}
