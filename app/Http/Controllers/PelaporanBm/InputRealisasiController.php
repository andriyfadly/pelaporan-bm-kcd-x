<?php

namespace App\Http\Controllers\PelaporanBm;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Controller;
use App\Models\PelaporanBm\Acuan;
use App\Models\PelaporanBm\KunciLaporan;
use App\Models\PelaporanBm\Realisasi;
use App\Models\PelaporanBm\Spj;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InputRealisasiController extends Controller
{
    use ResolvesSekolah;

    public function pilihBulan(Request $request): Response
    {
        $bulan = (int) ($request->input('bulan_realisasi') ?: ($request->input('bulan') ?: date('n')));
        if ($bulan < 1 || $bulan > 12) {
            $bulan = (int) date('n');
        }

        return Inertia::render('PelaporanBm/InputRealisasi/PilihBulan', [
            'bulanAwal' => $bulan,
        ]);
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $sekolahId = $this->resolveSekolahId($request);
        $bulan = (int) ($request->input('bulan_realisasi') ?: ($request->input('bulan') ?: date('n')));
        if ($bulan < 1 || $bulan > 12) {
            $bulan = (int) date('n');
        }

        $isLocked = false;
        $statusKirim = 'draft';
        if ($sekolahId) {
            $kunci = KunciLaporan::where('sekolah_id', $sekolahId)
                ->where('bulan', $bulan)
                ->first();
            $isLocked = (bool) ($kunci?->status_kunci ?? false);
            $statusKirim = $kunci?->status_kirim ?? 'draft';
        }

        // Ambil target acuan kerja bulan berjalan
        $acuanList = Acuan::where('bulan', $bulan)
            ->when($sekolahId, fn ($q) => $q->where('sekolah_id', $sekolahId))
            ->get();

        // Kelompokkan per kodering
        $grouped = [];
        foreach ($acuanList as $acuan) {
            $k = trim($acuan->kodering);
            if (! isset($grouped[$k])) {
                $grouped[$k] = [
                    'kodering' => $k,
                    'acuan_id' => $acuan->id,
                    'nominal_acuan' => 0.0,
                    'nominal_realisasi' => 0.0,
                    'kekurangan' => 0.0,
                    'list_uraian' => [],
                ];
            }
            $grouped[$k]['nominal_acuan'] += (float) $acuan->nominal;
            if ($acuan->uraian && ! in_array($acuan->uraian, $grouped[$k]['list_uraian'], true)) {
                $grouped[$k]['list_uraian'][] = $acuan->uraian;
            }
        }

        // Ambil data realisasi per kodering dari tabel pelaporan_bm_realisasi
        if ($sekolahId && ! empty($grouped)) {
            $realisasiSums = Realisasi::where('sekolah_id', $sekolahId)
                ->where('bulan_realisasi', $bulan)
                ->whereIn('kodering_belanja', array_keys($grouped))
                ->groupBy('kodering_belanja')
                ->selectRaw('kodering_belanja, SUM(nilai_perolehan) as total')
                ->pluck('total', 'kodering_belanja');

            foreach ($grouped as $k => &$item) {
                $real = (float) ($realisasiSums[$k] ?? 0.0);
                $item['nominal_realisasi'] = $real;
                $item['kekurangan'] = $item['nominal_acuan'] - $real;
            }
            unset($item);
        }

        $totalAcuan = array_sum(array_column($grouped, 'nominal_acuan'));
        $totalRealisasi = array_sum(array_column($grouped, 'nominal_realisasi'));
        $totalKekurangan = array_sum(array_column($grouped, 'kekurangan'));

        return Inertia::render('PelaporanBm/InputRealisasi/Index', [
            'daftarRekening' => array_values($grouped),
            'totalAcuan' => $totalAcuan,
            'totalRealisasi' => $totalRealisasi,
            'totalKekurangan' => $totalKekurangan,
            'bulan' => $bulan,
            'isLocked' => $isLocked,
            'statusKirim' => $statusKirim,
        ]);
    }

    public function tambah(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $sekolahId = $this->resolveSekolahId($request);
        $kodering = trim($request->input('kodering', ''));
        $bulan = (int) $request->input('bulan_realisasi', date('n'));

        if (empty($kodering) || $bulan < 1 || $bulan > 12) {
            return redirect()->route('pelaporan-bm.input-realisasi.index')->with('error', 'Parameter tidak valid.');
        }

        // Cek lock
        $kunci = KunciLaporan::where('sekolah_id', $sekolahId)->where('bulan', $bulan)->first();
        if ($kunci?->status_kunci || in_array($kunci?->status_kirim, ['menunggu_approval', 'disetujui'], true)) {
            return redirect()->route('pelaporan-bm.input-realisasi.index', ['bulan_realisasi' => $bulan])
                ->with('error', 'Laporan bulan ini telah dikunci atau dikirim.');
        }

        // Pagu acuan & list uraian
        $acuanRows = Acuan::where('bulan', $bulan)
            ->when($sekolahId, fn ($q) => $q->where('sekolah_id', $sekolahId))
            ->where('kodering', $kodering)
            ->get();

        $paguAcuan = (float) $acuanRows->sum('nominal');
        $listUraian = $acuanRows->pluck('uraian')->unique()->filter()->values()->all();

        // Hitung realisasi yang sudah ada
        $totalRealisasiSaatIni = (float) Realisasi::where('sekolah_id', $sekolahId)
            ->where('bulan_realisasi', $bulan)
            ->where('kodering_belanja', $kodering)
            ->sum('nilai_perolehan');

        $sisaAnggaran = $paguAcuan - $totalRealisasiSaatIni;

        // Ambil data SPJ di Data Barang bulan ini + id yang sudah dialokasikan di kodering manapun
        $spjItems = Spj::where('sekolah_id', $sekolahId)
            ->where('bulan_realisasi', $bulan)
            ->orderBy('no_spk')
            ->orderBy('nama_barang')
            ->get();
        $teralokasiIds = Realisasi::where('sekolah_id', $sekolahId)
            ->where('bulan_realisasi', $bulan)
            ->whereNotNull('spj_id')
            ->pluck('spj_id')
            ->all();

        // Kelompokkan per SPK
        $spkGroups = [];
        foreach ($spjItems as $item) {
            $key = $item->no_spk ?: 'TANPA_NOMOR_SPK';
            if (! isset($spkGroups[$key])) {
                $spkGroups[$key] = [
                    'no_spk' => $key,
                    'no_sp2d' => $item->no_sp2d ?: '-',
                    'sumber_perolehan' => $item->sumber_perolehan ?: 'BOS Reguler',
                    'ba_no' => $item->ba_no ?: '-',
                    'ba_tgl' => $item->ba_tgl ?: '-',
                    'total_belanja_spk' => 0.0,
                    'items' => [],
                ];
            }
            $spkGroups[$key]['total_belanja_spk'] += (float) $item->nilai_perolehan;
            $spkGroups[$key]['items'][] = $item;
        }

        return Inertia::render('PelaporanBm/InputRealisasi/Tambah', [
            'kodering' => $kodering,
            'bulan' => $bulan,
            'paguAcuan' => $paguAcuan,
            'totalRealisasiSaatIni' => $totalRealisasiSaatIni,
            'sisaAnggaran' => $sisaAnggaran,
            'listUraian' => $listUraian,
            'spkGroups' => array_values($spkGroups),
            'teralokasiIds' => $teralokasiIds,
        ]);
    }

    public function simpan(Request $request): RedirectResponse
    {
        $user = $request->user();
        $sekolahId = $this->resolveSekolahId($request);
        $kodering = trim($request->input('kodering', ''));
        $bulan = (int) $request->input('bulan_realisasi', 0);
        $itemIds = $request->input('item_ids', []);

        if (empty($kodering) || $bulan < 1 || $bulan > 12 || empty($itemIds) || ! is_array($itemIds)) {
            return back()->with('error', 'Pilih minimal satu item barang untuk direalisasikan.');
        }

        // Cek kunci
        $kunci = KunciLaporan::where('sekolah_id', $sekolahId)->where('bulan', $bulan)->first();
        if ($kunci?->status_kunci || in_array($kunci?->status_kirim, ['menunggu_approval', 'disetujui'], true)) {
            return back()->with('error', 'Laporan bulan ini telah dikunci atau dikirim.');
        }

        // Ambil acuan
        $acuanRows = Acuan::where('bulan', $bulan)
            ->when($sekolahId, fn ($q) => $q->where('sekolah_id', $sekolahId))
            ->where('kodering', $kodering)
            ->get();

        if ($acuanRows->isEmpty()) {
            return back()->with('error', 'Target acuan dengan kodering tersebut tidak ditemukan.');
        }

        $acuan = $acuanRows->first();

        // Ambil item SPJ yang dipilih
        $items = Spj::where('sekolah_id', $sekolahId)
            ->where('bulan_realisasi', $bulan)
            ->whereIn('id', $itemIds)
            ->get();

        if ($items->isEmpty()) {
            return back()->with('error', 'Data barang tidak valid.');
        }

        // Proteksi batas anggaran: total item yang dipilih tidak boleh melebihi sisa anggaran kodering
        $totalPilihan = (float) $items->sum('nilai_perolehan');
        $totalRealisasiSaatIni = (float) Realisasi::where('sekolah_id', $sekolahId)
            ->where('bulan_realisasi', $bulan)
            ->where('kodering_belanja', $kodering)
            ->sum('nilai_perolehan');
        $sisaAnggaran = (float) $acuanRows->sum('nominal') - $totalRealisasiSaatIni;

        if ($totalPilihan > $sisaAnggaran + 0.01) {
            return back()->with('error', 'Gagal Simpan: Jumlah inputan Rekening '.$kodering.' (Rp '.number_format($totalPilihan, 0, ',', '.').') melebihi sisa anggaran acuan target (Rp '.number_format(max($sisaAnggaran, 0), 0, ',', '.').').');
        }

        DB::transaction(function () use ($items, $sekolahId, $kodering, $bulan, $acuan) {
            foreach ($items as $item) {
                // Buat record di pelaporan_bm_realisasi
                Realisasi::create([
                    'spj_id' => $item->id,
                    'sekolah_id' => $sekolahId,
                    'acuan_id' => $acuan->id,
                    'no_sp2d' => $item->no_sp2d,
                    'sumber_perolehan' => $item->sumber_perolehan,
                    'kodering_belanja' => $kodering,
                    'bulan_realisasi' => $bulan,
                    'no_spk' => $item->no_spk,
                    'ba_no' => $item->ba_no,
                    'ba_tgl' => $item->ba_tgl,
                    'kode_barang' => $item->kode_barang,
                    'nama_barang' => $item->nama_barang,
                    'jenis_aset' => $item->jenis_aset,
                    'merk_tipe' => $item->merk_tipe,
                    'no_sertifikat' => $item->no_sertifikat,
                    'ukuran_bangunan' => $item->ukuran_bangunan,
                    'satuan' => $item->satuan,
                    'volume' => $item->volume,
                    'harga_satuan' => $item->harga_satuan,
                    'nilai_perolehan' => $item->nilai_perolehan,
                ]);
            }
        });

        return redirect()->route('pelaporan-bm.input-realisasi.index', ['bulan_realisasi' => $bulan])
            ->with('success', 'Barang SPJ berhasil dialokasikan ke rekening realisasi.');
    }

    public function edit(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $sekolahId = $this->resolveSekolahId($request);
        $kodering = trim($request->input('kodering', ''));
        $bulan = (int) $request->input('bulan_realisasi', date('n'));

        if (empty($kodering) || $bulan < 1 || $bulan > 12) {
            return redirect()->route('pelaporan-bm.input-realisasi.index')->with('error', 'Parameter tidak valid.');
        }

        $kunci = KunciLaporan::where('sekolah_id', $sekolahId)->where('bulan', $bulan)->first();
        $isReadOnly = (bool) ($kunci?->status_kunci || in_array($kunci?->status_kirim, ['menunggu_approval', 'disetujui'], true));

        // Pagu acuan
        $paguAcuan = (float) Acuan::where('bulan', $bulan)
            ->when($sekolahId, fn ($q) => $q->where('sekolah_id', $sekolahId))
            ->where('kodering', $kodering)
            ->sum('nominal');

        // Realisasi aktif
        $items = Realisasi::where('sekolah_id', $sekolahId)
            ->where('bulan_realisasi', $bulan)
            ->where('kodering_belanja', $kodering)
            ->orderBy('no_spk')
            ->orderBy('nama_barang')
            ->get();

        return Inertia::render('PelaporanBm/InputRealisasi/Edit', [
            'kodering' => $kodering,
            'bulan' => $bulan,
            'paguAcuan' => $paguAcuan,
            'items' => $items,
            'isReadOnly' => $isReadOnly,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $sekolahId = $this->resolveSekolahId($request);
        $kodering = trim($request->input('kodering', ''));
        $bulan = (int) $request->input('bulan_realisasi', 0);
        // IDs di pelaporan_bm_realisasi yang DIHAPUS (uncheck)
        $uncheckIds = $request->input('uncheck_ids', []);

        if (empty($kodering) || $bulan < 1 || $bulan > 12) {
            return back()->with('error', 'Parameter tidak valid.');
        }

        $kunci = KunciLaporan::where('sekolah_id', $sekolahId)->where('bulan', $bulan)->first();
        if ($kunci?->status_kunci || in_array($kunci?->status_kirim, ['menunggu_approval', 'disetujui'], true)) {
            return back()->with('error', 'Laporan bulan ini telah dikunci atau dikirim.');
        }

        if (! empty($uncheckIds) && is_array($uncheckIds)) {
            // Scope ketat: hanya boleh menghapus baris pada bulan & kodering yang
            // sedang diedit — cegah hapus lintas periode (ID ditebak/dikirim sembarang).
            Realisasi::where('sekolah_id', $sekolahId)
                ->where('bulan_realisasi', $bulan)
                ->where('kodering_belanja', $kodering)
                ->whereIn('id', $uncheckIds)
                ->delete();
        }

        return redirect()->route('pelaporan-bm.input-realisasi.index', ['bulan_realisasi' => $bulan])
            ->with('success', 'Perubahan alokasi realisasi berhasil disimpan.');
    }

    public function kirimLaporan(Request $request): RedirectResponse
    {
        $user = $request->user();
        $sekolahId = $this->resolveSekolahId($request);
        $bulan = (int) $request->input('bulan_realisasi', 0);

        if (! $sekolahId || $bulan < 1 || $bulan > 12) {
            return back()->with('error', 'Parameter tidak valid.');
        }

        $acuanList = Acuan::where('bulan', $bulan)
            ->where('sekolah_id', $sekolahId)
            ->get();

        if ($acuanList->isEmpty()) {
            return back()->with('error', 'Tidak ada target acuan untuk bulan ini.');
        }

        $totalAcuan = (float) $acuanList->sum('nominal');
        $totalRealisasi = (float) Realisasi::where('sekolah_id', $sekolahId)
            ->where('bulan_realisasi', $bulan)
            ->sum('nilai_perolehan');

        if ($totalRealisasi < $totalAcuan) {
            return back()->with('error', 'Belum bisa kirim! Seluruh target acuan realisasi harus terpenuhi (balance) terlebih dahulu.');
        }

        $kunci = KunciLaporan::updateOrCreate(
            ['sekolah_id' => $sekolahId, 'bulan' => $bulan],
            ['status_kirim' => 'menunggu_approval', 'status_kunci' => true]
        );

        activity('sistem')
            ->performedOn($kunci)
            ->event('kirim-laporan')
            ->withProperties([
                'ringkasan' => "Kirim laporan bulan {$bulan} (Rp ".number_format($totalRealisasi, 0, ',', '.').')',
                'sekolah_id' => $sekolahId,
                'bulan' => $bulan,
                'total_acuan' => $totalAcuan,
                'total_realisasi' => $totalRealisasi,
            ])
            ->log('kirim-laporan');

        return redirect()->route('pelaporan-bm.input-realisasi.index', ['bulan_realisasi' => $bulan])
            ->with('success', 'Laporan realisasi berhasil dikirim ke Admin KCD.');
    }
}
