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
use Illuminate\Validation\ValidationException;
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

        $unit = $this->pendaftaran->daftarkan($sekolahId, [
            ...$request->validated(),
            'kondisi' => $request->validated('kondisi') ?? 'B',
        ]);

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
        // Kunci koleksi dipertahankan supaya nomor baris Excel bisa dilaporkan.
        $dataRows = $rows instanceof Collection ? $rows->slice(1) : collect();

        // ponytail: batas 1000 baris per impor, naikkan jika kebutuhan riil melebihi
        if ($dataRows->count() > 1000) {
            return back()->with('error', 'Berkas terlalu besar: maksimal 1000 baris data per impor.');
        }

        $dataRows = $dataRows->filter(fn ($row) => trim((string) ($row[0] ?? '')) !== '');

        if ($dataRows->isEmpty()) {
            return back()->with('error', 'Berkas tidak berisi data untuk diimpor.');
        }

        $errors = [];
        $baris = [];

        foreach ($dataRows as $index => $row) {
            $nomor = (int) $index + 1;

            [$data, $galat] = $this->bacaBaris($row);

            foreach ($galat as $field => $pesan) {
                $errors["baris.{$nomor}.{$field}"] = "Baris {$nomor}: {$pesan}";
            }

            $baris[$nomor] = $data;
        }

        // Validasi seluruh berkas sebelum menulis apa pun: satu baris salah
        // membatalkan impor, dan nomor barisnya bisa ditindaklanjuti operator.
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $jumlahUnit = 0;
        DB::beginTransaction();

        try {
            foreach ($baris as $data) {
                $jumlahUnit += count($this->pendaftaran->daftarkan($sekolahId, $data));
            }
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->with('error', "Impor dibatalkan: {$e->getMessage()}");
        }

        DB::commit();

        return back()->with('success', "Berhasil mengimpor {$jumlahUnit} unit inventaris.");
    }

    /**
     * Baca satu baris 11 kolom dan validasi dengan aturan yang sama seperti
     * input manual. `kondisi` tidak diimpor.
     *
     * @param  Collection<int, mixed>  $row
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private function bacaBaris(Collection $row): array
    {
        $errors = [];

        $kode = trim((string) ($row[0] ?? ''));
        if ($kode === '') {
            $errors['kode_barang'] = 'Kode barang wajib diisi.';
        } elseif (mb_strlen($kode) > 100) {
            $errors['kode_barang'] = 'Kode barang maksimal 100 karakter.';
        }

        $asal = trim((string) ($row[1] ?? ''));
        if (mb_strlen($asal) > 150) {
            $errors['asal_perolehan'] = 'Asal perolehan maksimal 150 karakter.';
        }

        $nama = trim((string) ($row[2] ?? ''));
        if (mb_strlen($nama) > 255) {
            $errors['nama_barang'] = 'Nama barang maksimal 255 karakter.';
        }

        $merk = trim((string) ($row[3] ?? ''));
        if (mb_strlen($merk) > 255) {
            $errors['merk_tipe'] = 'Merek/tipe maksimal 255 karakter.';
        }

        $bahan = trim((string) ($row[4] ?? ''));
        if (mb_strlen($bahan) > 100) {
            $errors['bahan'] = 'Bahan maksimal 100 karakter.';
        }

        $tanggal = null;
        try {
            $tanggal = $this->tanggalPerolehan($row);
        } catch (\RuntimeException $e) {
            $errors['tanggal_perolehan'] = $e->getMessage();
        }

        $satuan = trim((string) ($row[8] ?? ''));
        if (mb_strlen($satuan) > 50) {
            $errors['satuan'] = 'Satuan maksimal 50 karakter.';
        }

        $volume = 1;
        $volumeMentah = $row[9] ?? null;

        if ($volumeMentah !== null && trim((string) $volumeMentah) !== '') {
            if (! is_numeric($volumeMentah) || (float) $volumeMentah !== (float) (int) $volumeMentah) {
                $errors['volume'] = 'Volume harus berupa bilangan bulat.';
            } else {
                $volume = (int) $volumeMentah;

                if ($volume < 1 || $volume > 500) {
                    $errors['volume'] = 'Volume harus antara 1 dan 500.';
                }
            }
        }

        $harga = 0.0;
        $hargaMentah = trim(str_replace([',', ' '], '', (string) ($row[10] ?? '')));

        if ($hargaMentah !== '') {
            if (! is_numeric($hargaMentah)) {
                $errors['harga_satuan'] = 'Harga satuan harus berupa angka.';
            } else {
                $harga = (float) $hargaMentah;

                if ($harga < 0) {
                    $errors['harga_satuan'] = 'Harga satuan tidak boleh negatif.';
                }
            }
        }

        return [[
            'kode_barang' => $kode,
            'asal_perolehan' => $asal !== '' ? $asal : null,
            'nama_barang' => $nama,
            'merk_tipe' => $merk !== '' ? $merk : null,
            'bahan' => $bahan !== '' ? $bahan : null,
            'tanggal_perolehan' => $tanggal ?? now()->toDateString(),
            'satuan' => $satuan !== '' ? $satuan : null,
            'volume' => $volume,
            'harga_satuan' => $harga,
        ], $errors];
    }

    public function template(): BinaryFileResponse
    {
        $contoh = KodeBarang::orderBy('kode_barang')
            ->limit(2)
            ->get()
            ->map(fn (KodeBarang $kode): array => [
                $kode->kode_barang,
                '',                          // Sumber Perolehan
                $kode->uraian,
                '',                          // Merek / Type
                '',                          // Bahan
                now()->day,
                now()->month,
                now()->year,
                $kode->satuan ?: 'Unit',
                1,
                (float) ($kode->harga_standar ?? 0),
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
        $tanggal = $row[5] ?? null;

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
        $bulan = (int) ($row[6] ?? 0);
        $tahun = (int) ($row[7] ?? 0);

        if ($tahun < 1900) {
            throw new \RuntimeException('Tahun perolehan wajib diisi: nomor register diulang tiap tahun.');
        }

        $bulan = $bulan ?: 1;
        $hari = $hari ?: 1;

        try {
            $tanggal = Carbon::create($tahun, $bulan, $hari);
        } catch (\Throwable) {
            throw new \RuntimeException("Tanggal perolehan {$hari}/{$bulan}/{$tahun} tidak valid.");
        }

        // Carbon menggeser tanggal mustahil (mis. 31/2) ke bulan berikutnya;
        // tolak nilai yang tidak round-trip supaya periode tidak berubah diam-diam.
        if ($tanggal->year !== $tahun || $tanggal->month !== $bulan || $tanggal->day !== $hari) {
            throw new \RuntimeException("Tanggal perolehan {$hari}/{$bulan}/{$tahun} tidak valid.");
        }

        return $tanggal->toDateString();
    }
}
