<?php

namespace App\Http\Middleware;

use App\Support\DistribusiBhp\SumberDana;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tetapkan sumber dana BHP aktif dari route prefix `{bos|bopd}` di URL.
 * Middleware ini hanya dipasang di grup `distribusi-bhp/bos/*` dan `distribusi-bhp/bopd/*`
 * — route di luar grup (dashboard/settings/divisi) tidak disetel.
 */
class SetSumberDanaBhp
{
    public function handle(Request $request, Closure $next): Response
    {
        // Parse sumber dana dari URL path: /distribusi-bhp/{bos|bopd}/...
        $path = $request->path();
        $sumberDana = null;

        if (preg_match('#^distribusi-bhp/(bos|bopd)(/|$)#', $path, $m)) {
            $sumberDana = $m[1];
        }

        if ($sumberDana === null) {
            $sumberDana = $request->input('sumber_dana') ?? $request->query('sumber_dana');
        }

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
