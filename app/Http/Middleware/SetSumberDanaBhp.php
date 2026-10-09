<?php

namespace App\Http\Middleware;

use App\Support\DistribusiBhp\SumberDana;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tetapkan sumber dana BHP aktif untuk request ini dari input/query
 * `sumber_dana`. Nilai invalid ditolak (422) — tidak fallback ke BOS.
 * Hanya bila parameter tidak diberikan sama sekali, default BOS dipakai.
 *
 * Route model binding untuk model BHP menangani sendiri pencarian lintas tabel
 * (lihat trait HasSumberDanaTable::resolveRouteBinding) sehingga middleware ini
 * cukup menyetel sumber dana untuk aksi non-binding (index/store).
 */
class SetSumberDanaBhp
{
    public function handle(Request $request, Closure $next): Response
    {
        $sumberDana = $request->input('sumber_dana') ?? $request->query('sumber_dana');

        if ($sumberDana !== null) {
            if (! SumberDana::valid($sumberDana)) {
                abort(422, 'Sumber dana tidak valid.');
            }
        } else {
            $sumberDana = SumberDana::BOS;
        }

        $sebelumnya = SumberDana::aktif();

        try {
            SumberDana::setAktif((string) $sumberDana);

            return $next($request);
        } finally {
            SumberDana::setAktif($sebelumnya);
        }
    }
}
