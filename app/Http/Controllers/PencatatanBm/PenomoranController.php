<?php

namespace App\Http\Controllers\PencatatanBm;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Controller;
use App\Services\PencatatanBm\NomorDokumenInventarisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pratinjau nomor surat untuk form cetak. Nomor final tetap dihitung ulang dan
 * dikunci saat dokumen disimpan.
 */
class PenomoranController extends Controller
{
    use ResolvesSekolah;

    public function __construct(private readonly NomorDokumenInventarisService $nomor) {}

    public function pratinjau(Request $request, string $jenis): JsonResponse
    {
        $sekolahId = $this->resolveSekolahIdWajib($request);

        /** @var 'bast'|'fakta_integritas'|'pengembalian' $jenis */
        return response()->json([
            'nomor' => $this->nomor->pratinjau($sekolahId, $jenis),
        ]);
    }
}
