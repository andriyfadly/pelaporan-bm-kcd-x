<?php

namespace App\Http\Controllers\PelaporanBm;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Controller;
use App\Models\Master\Sekolah;
use App\Models\PelaporanBm\Acuan;
use App\Models\PelaporanBm\KunciLaporan;
use App\Models\PelaporanBm\Realisasi;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RekapanController extends Controller
{
    use ResolvesSekolah;

    public function index(Request $request): Response
    {
        $bulan = (int) ($request->input('bulan') ?: date('n'));
        $search = trim((string) $request->input('search', ''));

        // Tenant isolation: operator tanpa sekolah_id ditolak 403; operator sekolah
        // dipaksa ke sekolahnya (cegah bocor rekap lintas sekolah).
        $sekolahId = $this->resolveSekolahId($request);

        // Paritas legacy/rekapan_admin.php: baris tabel = sekolah dengan acuan
        // pada bulan terpilih (tanpa fallback ke semua sekolah).
        $allAcuan = Acuan::query()
            ->where('bulan', $bulan)
            ->whereNotNull('sekolah_id')
            ->when($sekolahId, fn ($q) => $q->where('sekolah_id', $sekolahId))
            ->get()
            ->groupBy('sekolah_id');

        $sekolahs = Sekolah::query()
            ->whereIn('id', $allAcuan->keys())
            ->when($search, function ($q) use ($search) {
                $escaped = addcslashes($search, '%_\\');
                $q->where(fn ($w) => $w
                    ->where('nama_sekolah', 'like', "%{$escaped}%")
                    ->orWhere('npsn', 'like', "%{$escaped}%"));
            })
            ->orderBy('nama_sekolah')
            ->get();

        // Paritas legacy: realisasi dihitung dari baris yang dialokasikan ke
        // acuan (id_uraian), bukan dari seluruh SPJ bulan tersebut.
        $acuanIds = $allAcuan->flatten()->pluck('id');
        $allRealisasi = Realisasi::query()
            ->whereIn('acuan_id', $acuanIds)
            ->get()
            ->groupBy('acuan_id');
        $kunciMap = KunciLaporan::where('bulan', $bulan)->get()->keyBy('sekolah_id');

        $counter = ['tuntas' => 0, 'belum' => 0];

        $items = $sekolahs->map(function ($sekolah) use ($bulan, $allAcuan, $allRealisasi, $kunciMap, &$counter) {
            $id = $sekolah->id;
            $acuans = $allAcuan->get($id, collect());
            $kunci = $kunciMap->get($id);

            $statusKirimRaw = $kunci?->status_kirim ?? 'draft';
            $statusKirim = match (strtolower($statusKirimRaw)) {
                'menunggu_approval' => 'Menunggu Approval',
                'disetujui' => 'Disetujui',
                default => 'Belum Kirim',
            };

            $npsn = $sekolah->npsn ?? '-';

            $groupedKodering = [];
            foreach ($acuans as $ac) {
                $kodering = trim($ac->kodering) ?: 'TANPA KODERING';
                $nominalAcuan = (float) $ac->nominal;
                $nominalRealisasi = (float) $allRealisasi->get($ac->id, collect())->sum('nilai_perolehan');

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

            $totalRealisasi = (float) array_sum(array_column($groupedKodering, 'realisasi'));

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

            $logFisik = $acuans->pluck('id')
                ->flatMap(fn ($acuanId) => $allRealisasi->get($acuanId, collect()))
                ->map(function ($r) use ($statusKirim) {
                    $tgl = $r->ba_tgl ? strtotime($r->ba_tgl) : null;

                    return [
                        'id' => $r->id,
                        'no_sp2d' => $r->no_sp2d ?? '-',
                        'tanggal' => $tgl ? date('d', $tgl) : '-',
                        'bulan' => $tgl ? date('m', $tgl) : '-',
                        'tahun' => $tgl ? date('Y', $tgl) : '-',
                        'bulan_realisasi' => (string) $r->bulan_realisasi,
                        'kodering' => $r->kodering_belanja ?? '-',
                        'jenis_aset' => $r->jenis_aset ?? '-',
                        'kode_barang' => $r->kode_barang ?? '-',
                        'nama_barang' => $r->nama_barang ?? '-',
                        'merk_tipe' => $r->merk_tipe ?? '-',
                        'no_sertifikat' => $r->no_sertifikat ?? '-',
                        'volume' => (float) ($r->volume ?? 0),
                        'satuan' => $r->satuan ?? 'Unit',
                        'harga_satuan' => (float) ($r->harga_satuan ?? 0),
                        'nilai_perolehan' => (float) ($r->nilai_perolehan ?? 0),
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
                'total_realisasi' => $totalRealisasi,
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
