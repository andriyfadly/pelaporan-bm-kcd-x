<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMasterBarangRequest;
use App\Models\LaporanRealisasi;
use App\Models\MasterBarangSekolah;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MasterBarangController extends Controller
{
    public function store(StoreMasterBarangRequest $request): JsonResponse
    {
        $schoolId = (string) $request->user()->id_sekolah;
        $data = $request->validated();

        $locked = LaporanRealisasi::query()
            ->where('id_sekolah', $schoolId)
            ->where('bulan', $data['bulan_realisasi'])
            ->whereIn('status', ['Menunggu Approval', 'Disetujui'])
            ->exists();

        if ($locked) {
            return response()->json(['message' => 'Report is locked.'], 409);
        }

        $item = DB::transaction(function () use ($data, $schoolId): MasterBarangSekolah {
            $data['id_sekolah'] = $schoolId;
            $data['nilai_perolehan'] = $this->moneyProduct($data['volume'], $data['harga_satuan']);

            $item = new MasterBarangSekolah;
            $item->forceFill($data);
            $item->save();

            return $item;
        });

        return response()->json(['data' => $item], 201);
    }

    public function batch(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasRole('user'), 403);

        $data = $request->validate([
            'bulan_realisasi' => ['required', 'integer', 'between:1,12'],
            'items' => ['present', 'array'],
            'items.*.public_id' => ['required', 'uuid'],
            'items.*.nama_barang' => ['required', 'string', 'max:255'],
            'items.*.volume' => ['required', 'numeric', 'gt:0'],
            'items.*.harga_satuan' => ['required', 'numeric', 'gt:0'],
            'delete_ids' => ['present', 'array'],
            'delete_ids.*' => ['uuid'],
        ]);
        $schoolId = (string) $request->user()->id_sekolah;

        if ($this->reportLocked($schoolId, $data['bulan_realisasi'])) {
            return response()->json(['message' => 'Report is locked.'], 409);
        }

        DB::transaction(function () use ($data, $schoolId): void {
            $publicIds = collect($data['items'])->pluck('public_id')
                ->merge($data['delete_ids'])
                ->unique()
                ->values();

            $items = MasterBarangSekolah::query()
                ->forSchool($schoolId)
                ->where('bulan_realisasi', $data['bulan_realisasi'])
                ->whereIn('public_id', $publicIds)
                ->get(['public_id', 'is_realisasi']);

            abort_if($items->count() !== $publicIds->count(), 403);
            abort_if($items->contains('is_realisasi', true), 403, 'Realized items cannot be changed.');

            foreach ($data['items'] as $item) {
                MasterBarangSekolah::query()
                    ->forSchool($schoolId)
                    ->where('public_id', $item['public_id'])
                    ->update([
                        'nama_barang' => $item['nama_barang'],
                        'volume' => $item['volume'],
                        'harga_satuan' => $item['harga_satuan'],
                        'nilai_perolehan' => $this->moneyProduct($item['volume'], $item['harga_satuan']),
                    ]);
            }

            MasterBarangSekolah::query()
                ->forSchool($schoolId)
                ->where('bulan_realisasi', $data['bulan_realisasi'])
                ->whereIn('public_id', $data['delete_ids'])
                ->delete();
        });

        return response()->json(['message' => 'Master barang updated.']);
    }

    private function reportLocked(string $schoolId, int $month): bool
    {
        return LaporanRealisasi::query()
            ->where('id_sekolah', $schoolId)
            ->where('bulan', $month)
            ->whereIn('status', ['Menunggu Approval', 'Disetujui'])
            ->exists();
    }

    private function moneyProduct(string|int|float $volume, string|int|float $price): string
    {
        return bcmul((string) $volume, (string) $price, 2);
    }
}
