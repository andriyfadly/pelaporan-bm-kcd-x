<?php

namespace App\Http\Controllers\PelaporanBm;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Controller;
use App\Models\Master\Sekolah;
use App\Models\PelaporanBm\Realisasi;
use App\Models\PelaporanBm\Spj;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CetakController extends Controller
{
    use ResolvesSekolah;

    public function show(Request $request): Response
    {
        $user = $request->user();
        $sekolahId = $this->resolveSekolahId($request);
        $bulan = (int) ($request->input('bulan') ?: date('n'));

        $years = Realisasi::whereNotNull('ba_tgl')
            ->pluck('ba_tgl')
            ->map(fn ($d) => (int) date('Y', strtotime((string) $d)))
            ->unique()
            ->sortDesc()
            ->values()
            ->toArray();

        if (empty($years)) {
            $years = [(int) date('Y')];
        }

        $sekolah = $sekolahId ? Sekolah::find($sekolahId) : Sekolah::first();
        if (! $sekolah) {
            $sekolah = new Sekolah([
                'id' => '',
                'nama_sekolah' => 'Belum Ada Sekolah',
                'kota_kab' => '-',
            ]);
        }

        $items = $sekolah->id ? Spj::where('sekolah_id', $sekolah->id)
            ->where('bulan_realisasi', $bulan)
            ->orderBy('no_spk')
            ->get() : collect();

        $sekolahs = ! $user->sekolah_id
            ? Sekolah::orderBy('nama_sekolah')->select('id', 'nama_sekolah')->get()
            : [];

        return Inertia::render('PelaporanBm/Cetak/Index', [
            'years' => $years,
            'defaultBulan' => $bulan,
            'defaultTahun' => (int) ($request->input('tahun') ?: $years[0]),
            'sekolah' => $sekolah,
            'sekolahs' => $sekolahs,
            'items' => $items,
            'bulan' => $bulan,
            'totalNominal' => (float) $items->sum('nilai_perolehan'),
        ]);
    }

    public function check(Request $request): JsonResponse
    {
        $bulan = (int) $request->input('bulan', date('n'));
        $tahun = (int) $request->input('tahun', date('Y'));

        $query = Realisasi::where('bulan_realisasi', $bulan)
            ->where(function ($q) use ($tahun) {
                $q->whereYear('ba_tgl', $tahun)
                    ->orWhereNull('ba_tgl');
            });

        if ($request->user()->sekolah_id) {
            $query->where('sekolah_id', $request->user()->sekolah_id);
        }

        $count = $query->count();

        return response()->json(['total_rows' => $count]);
    }

    public function unduh(Request $request): StreamedResponse
    {
        $bulan = (int) $request->input('bulan', date('n'));
        $tahun = (int) $request->input('tahun', date('Y'));

        $filename = "Daftar_Pengadaan_Belanja_Modal_Bulan_{$bulan}_{$tahun}.csv";

        $query = Realisasi::with(['sekolah', 'acuan'])
            ->where('bulan_realisasi', $bulan)
            ->where(function ($q) use ($tahun) {
                $q->whereYear('ba_tgl', $tahun)
                    ->orWhereNull('ba_tgl');
            });

        if ($request->user()->sekolah_id) {
            $query->where('sekolah_id', $request->user()->sekolah_id);
        }

        $items = $query->orderBy('sekolah_id')
            ->orderBy('ba_tgl')
            ->get();

        return response()->streamDownload(function () use ($items) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'No',
                'No. SP2D',
                'Sumber Perolehan',
                'Kodering Belanja',
                'No. SPK / Faktur / Kuitansi',
                'BA Penerimaan No',
                'BA Penerimaan Tgl',
                'BA Penerimaan Bln',
                'BA Penerimaan Thn',
                'Kode Barang',
                'Nama Barang',
                'Merk/Tipe',
                'No. Sertifikat/ No. Rangka/ No. Mesin',
                'Ukuran (Gedung/ Bangunan)',
                'Satuan',
                'Volume',
                'Harga Satuan',
                'Nilai Perolehan',
                'Kodering Aset',
                'Nama Rekening Aset',
                'Umur Ekonomis',
                'Intrakomptabel (Nilai Perolehan)',
                'Beban Penyusutan',
                'Ekstrakomptabel',
                'Nama Sekolah',
                'Kab/Kota',
            ]);

            $no = 1;
            foreach ($items as $item) {
                $tgl = $item->ba_tgl ? date('d', strtotime($item->ba_tgl)) : '';
                $bln = $item->ba_tgl ? date('m', strtotime($item->ba_tgl)) : '';
                $thn = $item->ba_tgl ? date('Y', strtotime($item->ba_tgl)) : '';

                $volume = (int) ($item->volume ?? 0);
                $harga = (float) ($item->harga_satuan ?? 0);
                $nilai = (float) ($item->nilai_perolehan ?? ($volume * $harga));

                $ekstra = ($harga <= 1000000) ? $nilai : 0;
                $intra = $nilai;

                $namaSekolah = $item->sekolah?->nama_sekolah ?? "Sekolah ID: {$item->sekolah_id}";
                $kotaKab = $item->sekolah?->kota_kab ?? '-';

                fputcsv($handle, [
                    $no++,
                    $item->no_sp2d ?? '-',
                    $item->sumber_perolehan ?? 'BOSP',
                    $item->kodering_belanja ?? '-',
                    $item->no_spk ?? '-',
                    $item->ba_no ?? '-',
                    $tgl,
                    $bln,
                    $thn,
                    $item->kode_barang,
                    $item->nama_barang,
                    $item->merk_tipe ?? '-',
                    $item->no_sertifikat ?? '-',
                    $item->ukuran_bangunan ?? '-',
                    $item->satuan ?? 'Unit',
                    $volume,
                    $harga,
                    $nilai,
                    $item->acuan?->kodering_aset ?? '-',
                    $item->nama_barang,
                    $item->acuan?->umur_ekonomis ?? 0,
                    $intra,
                    0,
                    $ekstra,
                    $namaSekolah,
                    $kotaKab,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
