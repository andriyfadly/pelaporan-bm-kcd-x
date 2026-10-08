<?php

namespace App\Http\Controllers\DistribusiBhp;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Controller;
use App\Models\DistribusiBhp\Pengajuan;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DokumenController extends Controller
{
    use ResolvesSekolah;

    public function show(Request $request, Pengajuan $pengajuan): Response
    {
        $this->authorizeSekolah($request, $pengajuan);

        return Inertia::render('DistribusiBhp/Dokumen/Show', [
            'pengajuan' => $pengajuan->load(['details.barang', 'dokumen']),
        ]);
    }

    private function authorizeSekolah(Request $request, Pengajuan $pengajuan): void
    {
        $sekolahId = $this->resolveSekolahId($request);

        if ($sekolahId && $pengajuan->sekolah_id !== $sekolahId) {
            abort(403, 'Tidak memiliki akses ke dokumen ini.');
        }
    }
}
