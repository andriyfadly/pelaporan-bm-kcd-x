<?php

namespace App\Http\Controllers\PencatatanBm;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Controller;
use App\Models\PencatatanBm\Dokumen;
use App\Models\PencatatanBm\InventarisUnit;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Data pengguna barang: daftar aset per pegawai pemegang (acuan:
 * `data_pengguna.php`), termasuk tombol permohonan pengembalian per pegawai.
 */
class DataPenggunaController extends Controller
{
    use ResolvesSekolah;

    public function index(Request $request): Response
    {
        $sekolahId = $this->resolveSekolahIdWajib($request);
        $search = trim((string) $request->input('search', ''));

        $units = InventarisUnit::query()
            ->with(['pegawai:id,nama_lengkap,nip', 'ruang:id,nama_ruang'])
            ->where('sekolah_id', $sekolahId)
            ->whereIn('status', [InventarisUnit::STATUS_AKTIF, InventarisUnit::STATUS_DIKEMBALIKAN])
            ->whereNotNull('pegawai_id')
            ->orderBy('kode_barang')
            ->orderBy('nomor_register')
            ->get();

        // Tanggal BAST terbaru per unit, dipakai kolom Waktu Penyaluran.
        $tanggalBast = Dokumen::query()
            ->where('sekolah_id', $sekolahId)
            ->where('jenis', 'bast')
            ->whereIn('inventaris_unit_id', $units->pluck('id'))
            ->orderByDesc('tanggal')
            ->orderByDesc('created_at')
            ->get(['inventaris_unit_id', 'tanggal'])
            ->keyBy('inventaris_unit_id');

        $kelompok = $units
            ->filter(fn (InventarisUnit $unit): bool => $search === '' || $this->cocok($unit, $search))
            ->groupBy(fn (InventarisUnit $unit): string => (string) $unit->pegawai_id)
            ->map(function ($daftar, string $pegawaiId) use ($tanggalBast): array {
                $pertama = $daftar->first();

                return [
                    'pegawai' => [
                        'id' => $pegawaiId,
                        'nama' => $pertama->pegawai?->nama_lengkap,
                        'nip' => $pertama->pegawai?->nip,
                    ],
                    'ruang_dominan' => $daftar->map(fn (InventarisUnit $unit) => $unit->ruang?->nama_ruang)->filter()->countBy()->sortDesc()->keys()->first(),
                    'jumlah_unit' => $daftar->count(),
                    'items' => $daftar->map(fn (InventarisUnit $unit): array => [
                        'id' => $unit->id,
                        'nomor_register' => $unit->nomor_register,
                        'kode_barang' => $unit->kode_barang,
                        'nama_barang' => $unit->nama_barang,
                        'merk_tipe' => $unit->merk_tipe,
                        'ruang' => $unit->ruang?->nama_ruang,
                        'tahun_perolehan' => $unit->tahun_perolehan,
                        'tanggal_perolehan' => $unit->tanggal_perolehan?->format('Y-m-d'),
                        'volume' => 1,
                        'satuan' => $unit->satuan,
                        'tanggal_bast' => $tanggalBast->get($unit->id)?->tanggal?->format('Y-m-d'),
                        'keterangan' => $unit->keterangan,
                    ])->values()->all(),
                ];
            })
            ->sortBy(fn (array $item): string => (string) $item['pegawai']['nama'])
            ->values()
            ->all();

        return Inertia::render('PencatatanBm/Pengguna/Index', [
            'kelompok' => $kelompok,
            'filter' => ['search' => $search],
            'canCetak' => $request->user()->can('cetak-inventaris-bm'),
        ]);
    }

    private function cocok(InventarisUnit $unit, string $search): bool
    {
        $kunci = mb_strtolower(implode(' ', array_filter([
            $unit->pegawai?->nama_lengkap,
            $unit->nama_barang,
            $unit->merk_tipe,
            $unit->nomor_register,
        ])));

        return str_contains($kunci, mb_strtolower($search));
    }
}
