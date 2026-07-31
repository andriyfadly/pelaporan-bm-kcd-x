<?php

namespace App\Http\Controllers;

use App\Models\KodeBarang;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KodeBarangSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $keyword = trim(mb_strtolower((string) $request->query('q', ''), 'UTF-8'));

        if ($keyword === '') {
            return response()->json([]);
        }

        $like = '%'.addcslashes($keyword, '%_').'%';

        try {
            $items = KodeBarang::query()
                ->from('kode_barang as k1')
                ->select(['k1.id', 'k1.kode_barang', 'k1.uraian', 'k1.kodering_aset', 'k1.jenis_aset', 'k1.umur_ekonomis'])
                ->where(function ($query) use ($like): void {
                    $query->whereRaw('LOWER(k1.kode_barang) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(k1.uraian) LIKE ?', [$like]);
                })
                ->whereNotExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('kode_barang as k2')
                        ->whereRaw("k2.kode_barang LIKE CONCAT(k1.kode_barang, '%')")
                        ->whereColumn('k2.kode_barang', '<>', 'k1.kode_barang');
                })
                ->limit(100)
                ->get()
                ->map(fn (KodeBarang $item): array => [
                    'id' => $item->id,
                    'kode_barang' => $item->kode_barang,
                    'nama_barang' => $item->uraian,
                    'kodering_aset' => $item->kodering_aset,
                    'jenis_aset' => $item->jenis_aset,
                    'umur_ekonomis' => $item->umur_ekonomis ?? 0,
                ]);
        } catch (QueryException) {
            return response()->json(['message' => 'Inventory catalog unavailable.'], 503);
        }

        return response()->json($items);
    }
}
