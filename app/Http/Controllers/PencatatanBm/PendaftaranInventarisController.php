<?php

namespace App\Http\Controllers\PencatatanBm;

use App\Exports\InventarisTemplateExport;
use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Controller;
use App\Http\Requests\PencatatanBm\PendaftaranInventarisRequest;
use App\Models\Master\KodeBarang;
use App\Services\PencatatanBm\PendaftaranInventarisService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Pendaftaran unit inventaris di luar realisasi: input manual dan impor Excel.
 * Keduanya memakai layanan yang sama supaya penomoran register tetap satu pintu.
 */
class PendaftaranInventarisController extends Controller
{
    use ResolvesSekolah;

    public function __construct(private readonly PendaftaranInventarisService $pendaftaran) {}

    public function store(PendaftaranInventarisRequest $request): RedirectResponse
    {
        $sekolahId = $this->resolveSekolahIdWajib($request);

        $unit = $this->pendaftaran->daftarkan($sekolahId, $request->validated());

        return back()->with('success', sprintf(
            '%d unit inventaris terdaftar dengan register %s.',
            count($unit),
            $unit[0]->nomor_register,
        ));
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:xlsx,xls,csv'],
        ]);

        $sekolahId = $this->resolveSekolahIdWajib($request);

        $rows = Excel::toCollection(null, $request->file('file'))->first();
        $dataRows = $rows instanceof Collection ? $rows->slice(1)->values() : collect();

        // ponytail: batas 1000 baris per impor, naikkan jika kebutuhan riil melebihi
        if ($dataRows->count() > 1000) {
            return back()->with('error', 'Berkas terlalu besar: maksimal 1000 baris data per impor.');
        }

        $dataRows = $dataRows->filter(fn ($row) => trim((string) ($row[0] ?? '')) !== '');

        if ($dataRows->isEmpty()) {
            return back()->with('error', 'Berkas tidak berisi data untuk diimpor.');
        }

        $jumlahUnit = 0;
        // All-or-nothing seperti impor acuan: satu baris salah membatalkan
        // seluruh berkas supaya register tidak terbit untuk data yang batal.
        DB::beginTransaction();

        try {
            foreach ($dataRows as $index => $row) {
                $unit = $this->pendaftaran->daftarkan($sekolahId, [
                    'kode_barang' => trim((string) ($row[0] ?? '')),
                    'nama_barang' => trim((string) ($row[1] ?? '')),
                    'merk_tipe' => trim((string) ($row[2] ?? '')) ?: null,
                    'tanggal_perolehan' => $this->tanggalPerolehan($row),
                    'satuan' => trim((string) ($row[6] ?? '')) ?: null,
                    'volume' => (int) ($row[7] ?? 1),
                    'harga_satuan' => (float) str_replace([',', ' '], '', (string) ($row[8] ?? 0)),
                    'asal_perolehan' => trim((string) ($row[9] ?? '')) ?: null,
                ]);

                $jumlahUnit += count($unit);
            }
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->with('error', "Impor dibatalkan: {$e->getMessage()}");
        }

        DB::commit();

        return back()->with('success', "Berhasil mengimpor {$jumlahUnit} unit inventaris.");
    }

    public function template(): BinaryFileResponse
    {
        $contoh = KodeBarang::orderBy('kode_barang')
            ->limit(2)
            ->get()
            ->map(fn (KodeBarang $kode): array => [
                $kode->kode_barang,
                $kode->uraian,
                '',
                now()->day,
                now()->month,
                now()->year,
                $kode->satuan ?: 'Unit',
                1,
                (float) ($kode->harga_standar ?? 0),
                '',
            ]);

        return Excel::download(new InventarisTemplateExport($contoh), 'template-import-inventaris.xlsx');
    }

    /**
     * Tanggal/Bulan/Tahun pada template acuan dipisah ke tiga kolom. Excel
     * kadang mengirimnya sebagai tanggal utuh, jadi dua bentuk itu diterima.
     *
     * @param  Collection<int, mixed>  $row
     */
    private function tanggalPerolehan(Collection $row): string
    {
        $tanggal = $row[3] ?? null;

        if ($tanggal instanceof \DateTimeInterface) {
            return Carbon::instance($tanggal)->toDateString();
        }

        $mentah = trim((string) $tanggal);

        // Kolom tanggal berisi teks bebas (bukan angka hari / tanggal Excel):
        // parse langsung supaya nilai ngawur ditolak, bukan diam-diam jadi 1.
        if ($mentah !== '' && ! is_numeric($mentah)) {
            try {
                return Carbon::parse($mentah)->toDateString();
            } catch (\Throwable) {
                throw new \RuntimeException("Tanggal perolehan '{$mentah}' tidak dikenali.");
            }
        }

        $hari = (int) $mentah;
        $bulan = (int) ($row[4] ?? 0);
        $tahun = (int) ($row[5] ?? 0);

        if ($tahun < 1900) {
            throw new \RuntimeException('Tahun perolehan wajib diisi: nomor register diulang tiap tahun.');
        }

        return Carbon::create($tahun, max(1, min(12, $bulan ?: 1)), max(1, min(31, $hari ?: 1)))->toDateString();
    }
}
