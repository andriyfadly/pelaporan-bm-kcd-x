<?php

namespace App\Http\Controllers\PelaporanBm;

use App\Http\Controllers\Controller;
use App\Models\Master\Sekolah;
use App\Models\PelaporanBm\Acuan;
use App\Models\PelaporanBm\KunciLaporan;
use App\Models\PelaporanBm\Realisasi;
use App\Models\PelaporanBm\Spj;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $isAdmin = ! $user->sekolah_id;

        $namaBulanArr = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        if ($isAdmin) {
            $filterBulan = (int) $request->input('bulan', date('n'));
            $filterTahun = (int) $request->input('tahun', date('Y'));

            $currY = (int) date('Y');
            $years = [];
            for ($y = $currY; $y >= $currY - 4; $y--) {
                $years[] = $y;
            }

            // 1. Target = sekolah yang memiliki data acuan di bulan & tahun
            // terpilih (paritas dengan legacy index_admin.php). Tidak ada
            // fallback ke semua sekolah: bulan tanpa acuan = target 0.
            $acuanQuery = Acuan::query()
                ->where('bulan', $filterBulan)
                ->whereNotNull('sekolah_id')
                ->where(function ($q) use ($filterTahun) {
                    $q->whereYear('tanggal', $filterTahun)
                        ->orWhereNull('tanggal')
                        ->orWhereYear('created_at', $filterTahun);
                });

            $targetSekolah = Sekolah::query()
                ->whereIn('id', $acuanQuery->select('sekolah_id'))
                ->orderBy('nama_sekolah')
                ->get(['id', 'nama_sekolah']);

            // 2. Sekolah yang sudah realisasi di bulan & tahun terpilih
            // (hanya Realisasi final; Spj = katalog draft tidak dihitung).
            $selesaiIds = Realisasi::query()
                ->where('bulan_realisasi', $filterBulan)
                ->where(function ($q) use ($filterTahun) {
                    $q->whereYear('ba_tgl', $filterTahun)
                        ->orWhereNull('ba_tgl')
                        ->orWhereYear('created_at', $filterTahun);
                })
                ->whereNotNull('sekolah_id')
                ->distinct()
                ->pluck('sekolah_id')
                ->toArray();

            $listSelesai = [];
            $listBelum = [];

            foreach ($targetSekolah as $sch) {
                if (in_array($sch->id, $selesaiIds)) {
                    $listSelesai[] = ['id' => $sch->id, 'nama' => $sch->nama_sekolah];
                } else {
                    $listBelum[] = ['id' => $sch->id, 'nama' => $sch->nama_sekolah];
                }
            }

            return Inertia::render('PelaporanBm/Dashboard', [
                'isAdmin' => true,
                'filterBulan' => $filterBulan,
                'filterTahun' => $filterTahun,
                'namaBulan' => $namaBulanArr[$filterBulan] ?? 'Januari',
                'years' => $years,
                'totalTarget' => count($targetSekolah),
                'totalSelesai' => count($listSelesai),
                'totalBelum' => count($listBelum),
                'listSelesai' => $listSelesai,
                'listBelum' => $listBelum,
                // Backward compatibility
                'totalSpj' => Realisasi::count() ?: Spj::count(),
                'totalNominal' => (float) (Realisasi::sum('nilai_perolehan') ?: Spj::sum('nilai_perolehan')),
                'totalAcuan' => (float) Acuan::sum('nominal'),
                'rekapBulanan' => [],
            ]);
        }

        // --- DASHBOARD SEKOLAH ---
        $sekolahId = $user->sekolah_id;
        $sekolah = $user->sekolah ?: Sekolah::find($sekolahId);

        $bulanSekarang = (int) date('n');
        $bulanLapor = $this->bulanLapor($bulanSekarang);

        $kunciLapor = KunciLaporan::where('sekolah_id', $sekolahId)
            ->where('bulan', $bulanLapor)
            ->first();

        $statusRaw = strtolower(trim($kunciLapor?->status_kirim ?? ''));
        $statusBulanLapor = in_array($statusRaw, ['disetujui', 'selesai', 'menunggu_approval', 'menunggu approval'])
            ? 'SELESAI'
            : 'BELUM SELESAI';

        $acuanPerBulan = Acuan::where('sekolah_id', $sekolahId)
            ->selectRaw('bulan, SUM(nominal) as total')
            ->groupBy('bulan')
            ->pluck('total', 'bulan');

        $spjPerBulan = Spj::where('sekolah_id', $sekolahId)
            ->selectRaw('bulan_realisasi, SUM(nilai_perolehan) as total_realisasi, COUNT(id) as total_aset, COUNT(DISTINCT CASE WHEN TRIM(no_spk) != \'\' THEN no_spk END) as berkas_spk')
            ->groupBy('bulan_realisasi')
            ->get()
            ->keyBy('bulan_realisasi');

        $kunciPerBulan = KunciLaporan::where('sekolah_id', $sekolahId)
            ->pluck('status_kirim', 'bulan');

        $rekapBulanan = [];
        $totalKeseluruhanAcuan = 0;
        $totalKeseluruhanRealisasi = 0;
        $totalKeseluruhanAset = 0;
        $totalKeseluruhanSpk = 0;

        for ($m = 1; $m <= 12; $m++) {
            $spjM = $spjPerBulan->get($m);
            $nilaiAcuan = (float) ($acuanPerBulan[$m] ?? 0);
            $totalReal = (float) ($spjM?->total_realisasi ?? 0);
            $totalAst = (int) ($spjM?->total_aset ?? 0);
            $berkasSpk = (int) ($spjM?->berkas_spk ?? 0);

            $totalKeseluruhanAcuan += $nilaiAcuan;
            $totalKeseluruhanRealisasi += $totalReal;
            $totalKeseluruhanAset += $totalAst;
            $totalKeseluruhanSpk += $berkasSpk;

            $rekapBulanan[] = [
                'bulan' => $m,
                'bulan_nama' => $namaBulanArr[$m],
                'nilai_acuan' => $nilaiAcuan,
                'total_realisasi' => $totalReal,
                'total_aset' => $totalAst,
                'berkas_spk' => $berkasSpk,
                'status' => $kunciPerBulan[(string) $m] ?? 'draft',
            ];
        }

        return Inertia::render('PelaporanBm/Dashboard', [
            'isAdmin' => false,            'sekolah' => $sekolah,
            'bulanSekarang' => $bulanSekarang,
            'bulanLapor' => $bulanLapor,
            'namaBulanSekarang' => $namaBulanArr[$bulanSekarang] ?? 'Januari',
            'namaBulanLapor' => $namaBulanArr[$bulanLapor] ?? 'Desember',
            'statusBulanLapor' => $statusBulanLapor,
            'totalAcuan' => $totalKeseluruhanAcuan,
            'totalRealisasi' => $totalKeseluruhanRealisasi,
            'totalAset' => $totalKeseluruhanAset,
            'totalSpk' => $totalKeseluruhanSpk,
            'rekapBulanan' => $rekapBulanan,
            // Backward compatibility
            'totalSpj' => $totalKeseluruhanAset,
            'totalNominal' => $totalKeseluruhanRealisasi,
        ]);
    }

    /**
     * Bulan lapor (periode yang dilaporkan) = bulan berjalan - 1,
     * dengan Januari dibulatkan mundur ke Desember.
     */
    protected function bulanLapor(?int $bulanSekarang = null): int
    {
        $bulan = $bulanSekarang ?? (int) date('n');

        return $bulan === 1 ? 12 : $bulan - 1;
    }
}
