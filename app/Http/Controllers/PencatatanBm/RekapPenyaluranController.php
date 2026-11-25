<?php

namespace App\Http\Controllers\PencatatanBm;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Controller;
use App\Models\Master\Sekolah;
use App\Models\PencatatanBm\InventarisUnit;
use App\Services\PencatatanBm\DokumenInventarisService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rekap penyaluran PDF, persis `buku_salur_pdf.php` acuan: 16 kolom dua baris
 * header, satu baris per unit disalurkan.
 */
class RekapPenyaluranController extends Controller
{
    use ResolvesSekolah;

    public function __construct(
        private readonly DokumenInventarisService $dokumen,
    ) {}

    public function unduh(Request $request): Response
    {
        $sekolahId = $this->resolveSekolahIdWajib($request);
        $filter = (string) $request->input('filter', 'semua');

        $query = $this->query($sekolahId);

        if ($filter === 'tahun_sumber') {
            $tahun = $request->input('tahun');

            if (blank($tahun)) {
                throw ValidationException::withMessages([
                    'tahun' => 'Harap pilih Tahun terlebih dahulu!',
                ]);
            }

            $asal = array_filter((array) $request->input('asal', []));

            if ($asal === []) {
                throw ValidationException::withMessages([
                    'asal' => 'Harap centang minimal satu Sumber Perolehan!',
                ]);
            }

            $query->where('tahun_perolehan', (int) $tahun)->whereIn('asal_perolehan', $asal);
        }

        if ($filter === 'rentang') {
            $tahunDari = $request->input('tahun_dari');
            $tahunSampai = $request->input('tahun_sampai');
            $semesterDari = (int) $request->input('semester_dari');
            $semesterSampai = (int) $request->input('semester_sampai');

            if (blank($tahunDari) || blank($tahunSampai)) {
                throw ValidationException::withMessages([
                    'tahun_dari' => 'Harap pilih Tahun Dari dan Tahun Sampai terlebih dahulu!',
                ]);
            }

            if (! in_array($semesterDari, [1, 2], true) || ! in_array($semesterSampai, [1, 2], true)) {
                throw ValidationException::withMessages([
                    'semester_dari' => 'Harap pilih Semester Dari dan Semester Sampai terlebih dahulu!',
                ]);
            }

            if ((int) $tahunSampai < (int) $tahunDari) {
                throw ValidationException::withMessages([
                    'tahun_sampai' => 'Tahun Akhir tidak boleh lebih kecil dari Tahun Awal!',
                ]);
            }

            $skorAwal = ((int) $tahunDari * 10) + ($semesterDari === 2 ? 2 : 1);
            $skorAkhir = ((int) $tahunSampai * 10) + ($semesterSampai === 1 ? 1 : 2);

            if ($skorAkhir < $skorAwal) {
                throw ValidationException::withMessages([
                    'semester_sampai' => 'Rentang Tahun/Semester Akhir tidak boleh lebih kecil dari Tahun/Semester Awal!',
                ]);
            }

            $bulanAwal = $semesterDari === 1 ? 1 : 7;
            $bulanAkhir = $semesterSampai === 1 ? 6 : 12;
            [$tahunAwal, $tahunAkhir] = [(int) $tahunDari, (int) $tahunSampai];

            $query->where(fn (Builder $q) => $q
                ->where('tahun_perolehan', '>', $tahunAwal)
                ->orWhere(fn (Builder $q) => $q->where('tahun_perolehan', $tahunAwal)->where('bulan_perolehan', '>=', $bulanAwal)))
                ->where(fn (Builder $q) => $q
                    ->where('tahun_perolehan', '<', $tahunAkhir)
                    ->orWhere(fn (Builder $q) => $q->where('tahun_perolehan', $tahunAkhir)->where('bulan_perolehan', '<=', $bulanAkhir)));
        }

        if ($filter === 'ruang') {
            $ruangId = (string) $request->input('ruang');

            if ($ruangId === '') {
                throw ValidationException::withMessages([
                    'ruang' => 'Harap pilih Ruangan terlebih dahulu!',
                ]);
            }

            $query->where('ruang_id', $ruangId);
        }

        $units = $query->get();

        if ($filter === 'ruang' || $request->input('berdasarkan_ruang') === 'ya') {
            $units = $units->sortBy(fn (InventarisUnit $unit): string => sprintf(
                '%s|%s',
                $unit->ruang_id ? mb_strtolower((string) $unit->ruang?->nama_ruang) : 'zzz',
                $unit->nomor_register,
            ))->values();
        }

        $sekolah = Sekolah::findOrFail($sekolahId);
        $tanggalCetak = now()->toDateString();

        $pdf = Pdf::loadView('pencatatan-bm.rekap-penyaluran', [
            'judul' => 'Rekap Penyaluran Belanja Modal',
            'sekolah' => $sekolah,
            'units' => $units,
            'rincian' => $this->rincian($units),
            'pejabat' => $this->dokumen->pejabat($sekolahId, $tanggalCetak),
            'labelJabatan' => $this->dokumen->labelPenandatangan($sekolahId),
        ])->setPaper([0, 0, 935.43, 609.45], 'landscape');

        return $pdf->download('Rekap-Penyaluran-'.now()->format('Ymd').'.pdf');
    }

    /** @return Builder<InventarisUnit> */
    private function query(string $sekolahId): Builder
    {
        return InventarisUnit::query()
            ->with(['ruang:id,nama_ruang', 'pegawai:id,nama_lengkap'])
            ->where('sekolah_id', $sekolahId)
            ->whereIn('status', [InventarisUnit::STATUS_AKTIF, InventarisUnit::STATUS_DIKEMBALIKAN])
            ->orderBy('tahun_perolehan')
            ->orderBy('bulan_perolehan')
            ->orderBy('nomor_register');
    }

    /**
     * @param  Collection<int, InventarisUnit>  $units
     * @return array<int, array<string, mixed>>
     */
    private function rincian($units): array
    {
        return $units->map(fn (InventarisUnit $unit): array => [
            'kode_barang' => $unit->kode_barang,
            'nama_barang' => $unit->nama_barang,
            'merk_tipe' => $unit->merk_tipe,
            'tanggal_perolehan' => $unit->tanggal_perolehan?->format('d/m/Y'),
            'bulan_perolehan' => $unit->bulan_perolehan,
            'tahun_perolehan' => $unit->tahun_perolehan,
            'volume' => 1,
            'satuan' => $unit->satuan,
            'harga_satuan' => (float) $unit->harga_satuan,
            'lokasi' => $unit->ruang?->nama_ruang,
            'penerima' => $unit->pegawai?->nama_lengkap,
            'nomor_register' => $unit->nomor_register,
            'keterangan' => (string) DB::table('inventaris_mutasi')
                ->where('inventaris_unit_id', $unit->id)
                ->where('jenis', 'penyaluran')
                ->orderByDesc('tanggal')
                ->value('keterangan'),
            'disalurkan' => $unit->ruang_id !== null || $unit->pegawai_id !== null,
        ])->all();
    }
}
