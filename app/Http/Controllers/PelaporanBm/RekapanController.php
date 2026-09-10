<?php

namespace App\Http\Controllers\PelaporanBm;

use App\Http\Controllers\Controller;
use App\Models\Master\Sekolah;
use App\Models\PelaporanBm\Acuan;
use App\Models\PelaporanBm\KunciLaporan;
use App\Models\PelaporanBm\Spj;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RekapanController extends Controller
{
    public function index(Request $request): Response
    {
        $bulan = (int) ($request->input('bulan') ?: date('n'));
        $search = trim((string) $request->input('search', ''));

        $sekolahs = Sekolah::query()
            ->when($search, function ($q) use ($search) {
                $q->where('nama_sekolah', 'like', "%{$search}%");
            })
            ->orderBy('nama_sekolah')
            ->get();

        $allAcuan = Acuan::where('bulan', $bulan)->get()->groupBy('sekolah_id');
        $allSpj = Spj::where('bulan_realisasi', $bulan)->get()->groupBy('sekolah_id');
        $kunciMap = KunciLaporan::where('bulan', (string) $bulan)->get()->keyBy('sekolah_id');

        $counter = ['tuntas' => 0, 'belum' => 0];

        $items = $sekolahs->map(function ($sekolah) use ($bulan, $allAcuan, $allSpj, $kunciMap, &$counter) {
            $id = $sekolah->id;
            $acuans = $allAcuan->get($id, collect());
            $spjs = $allSpj->get($id, collect());
            $kunci = $kunciMap->get($id);

            $statusKirimRaw = $kunci?->status_kirim ?? 'draft';
            $statusKirim = match (strtolower($statusKirimRaw)) {
                'menunggu_approval' => 'Menunggu Approval',
                'disetujui' => 'Disetujui',
                default => 'Belum Kirim',
            };

            $npsn = $acuans->first()?->npsn ?? '-';

            $realisasiPerAcuan = [];
            foreach ($spjs as $s) {
                if ($s->acuan_id) {
                    $realisasiPerAcuan[$s->acuan_id] = ($realisasiPerAcuan[$s->acuan_id] ?? 0) + (float) $s->nilai_perolehan;
                }
            }

            $groupedKodering = [];
            foreach ($acuans as $ac) {
                $kodering = trim($ac->kodering) ?: 'TANPA KODERING';
                $nominalAcuan = (float) $ac->nominal;
                $nominalRealisasi = $realisasiPerAcuan[$ac->id] ?? 0;

                if (! isset($groupedKodering[$kodering])) {
                    $groupedKodering[$kodering] = [
                        'kodering' => $kodering,
                        'acuan' => 0,
                        'realisasi' => 0,
                        'kekurangan' => 0,
                        'is_match' => false,
                        'uraian' => [],
                    ];
                }

                $groupedKodering[$kodering]['acuan'] += $nominalAcuan;
                $groupedKodering[$kodering]['realisasi'] += $nominalRealisasi;
                if ($ac->uraian && ! in_array($ac->uraian, $groupedKodering[$kodering]['uraian'], true)) {
                    $groupedKodering[$kodering]['uraian'][] = $ac->uraian;
                }
            }

            $totalSpjNominal = (float) $spjs->sum('nilai_perolehan');
            $sumGroupedRealisasi = array_sum(array_column($groupedKodering, 'realisasi'));
            if ($sumGroupedRealisasi == 0 && $totalSpjNominal > 0 && count($groupedKodering) > 0) {
                $firstKey = array_key_first($groupedKodering);
                $groupedKodering[$firstKey]['realisasi'] = $totalSpjNominal;
            }

            $matchKoderingCount = 0;
            $totalKodering = 0;
            foreach ($groupedKodering as &$grp) {
                if ($grp['kodering'] !== 'TANPA KODERING') {
                    $totalKodering++;
                    if ($grp['realisasi'] >= $grp['acuan'] && $grp['acuan'] > 0) {
                        $matchKoderingCount++;
                        $grp['is_match'] = true;
                    }
                }
                $grp['kekurangan'] = max(0, $grp['acuan'] - $grp['realisasi']);
            }
            unset($grp);

            $isTuntas = ($statusKirim === 'Disetujui') || ($matchKoderingCount >= $totalKodering && $totalKodering > 0);
            $status = $isTuntas ? 'TUNTAS' : 'BELUM';

            if ($isTuntas) {
                $counter['tuntas']++;
            } else {
                $counter['belum']++;
            }

            $logFisik = $spjs->map(function ($s) use ($statusKirim) {
                $tgl = $s->ba_tgl ? strtotime($s->ba_tgl) : null;

                return [
                    'id' => $s->id,
                    'no_sp2d' => $s->no_sp2d ?? '-',
                    'tanggal' => $tgl ? date('d', $tgl) : '-',
                    'bulan' => $tgl ? date('m', $tgl) : '-',
                    'tahun' => $tgl ? date('Y', $tgl) : '-',
                    'bulan_realisasi' => (string) $s->bulan_realisasi,
                    'kodering' => $s->kategori ?? '-',
                    'jenis_aset' => $s->jenis_aset ?? '-',
                    'kode_barang' => $s->kode_barang ?? '-',
                    'nama_barang' => $s->nama_barang ?? '-',
                    'merk_tipe' => $s->merk_tipe ?? '-',
                    'no_sertifikat' => $s->no_sertifikat ?? '-',
                    'volume' => (float) ($s->volume ?? 0),
                    'satuan' => $s->satuan ?? 'Unit',
                    'harga_satuan' => (float) ($s->harga_satuan ?? 0),
                    'nilai_perolehan' => (float) ($s->nilai_perolehan ?? 0),
                    'is_locked' => $statusKirim === 'Disetujui' || $statusKirim === 'Menunggu Approval',
                ];
            })->values()->all();

            return [
                'id' => $id,
                'nama' => strtoupper($sekolah->nama_sekolah),
                'nama_sekolah' => $sekolah->nama_sekolah,
                'kota_kab' => $sekolah->kota_kab,
                'npsn' => $npsn,
                'bulan_disp' => $bulan,
                'status' => $status,
                'status_kirim' => $statusKirim,
                'is_locked' => (bool) ($kunci?->status_kunci ?? false),
                'progres' => [
                    'match' => $matchKoderingCount,
                    'total' => $totalKodering,
                    'is_selesai' => $isTuntas,
                ],
                'rekening_acuan' => array_values($groupedKodering),
                'log_fisik' => $logFisik,
                'total_acuan' => (float) $acuans->sum('nominal'),
                'total_realisasi' => $totalSpjNominal,
            ];
        });

        $sortedItems = $items->sort(function ($a, $b) {
            if ($a['status'] === $b['status']) {
                return strcasecmp($a['nama'], $b['nama']);
            }

            return ($a['status'] === 'TUNTAS') ? -1 : 1;
        })->values();

        $namaBulanIndo = [
            1 => 'JANUARI', 2 => 'FEBRUARI', 3 => 'MARET', 4 => 'APRIL',
            5 => 'MEI', 6 => 'JUNI', 7 => 'JULI', 8 => 'AGUSTUS',
            9 => 'SEPTEMBER', 10 => 'OKTOBER', 11 => 'NOVEMBER', 12 => 'DESEMBER',
        ];

        return Inertia::render('PelaporanBm/Rekapan/Index', [
            'items' => $sortedItems,
            'bulan' => $bulan,
            'namaBulan' => $namaBulanIndo[$bulan] ?? 'BULAN '.$bulan,
            'search' => $search,
            'counter' => $counter,
            'totalKeseluruhanAcuan' => (float) $items->sum('total_acuan'),
            'totalKeseluruhanRealisasi' => (float) $items->sum('total_realisasi'),
        ]);
    }
}
