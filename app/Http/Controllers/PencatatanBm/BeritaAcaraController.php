<?php

namespace App\Http\Controllers\PencatatanBm;

use App\Exports\RekapanBastExport;
use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Concerns\SearchesText;
use App\Http\Controllers\Concerns\TenantScope;
use App\Http\Controllers\Controller;
use App\Models\Master\Sekolah;
use App\Models\PencatatanBm\Dokumen;
use App\Services\PencatatanBm\NomorDokumenGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Rekap berita acara: koreksi/batal nomor BA. Pembatalan hanya mengosongkan
 * nomor dan menyimpan alasan di payload; dokumennya tetap ada.
 */
class BeritaAcaraController extends Controller
{
    use ResolvesSekolah, SearchesText, TenantScope;

    public function __construct(private readonly NomorDokumenGuard $guard) {}

    public function index(Request $request): Response
    {
        $sekolahId = $this->resolveSekolahId($request);
        $search = trim((string) $request->input('search', ''));

        $daftar = $this->daftar($sekolahId, $search);

        return Inertia::render('PencatatanBm/BeritaAcara/Index', [
            'daftar' => $daftar,
            'kodeBast' => $sekolahId ? (string) Sekolah::whereKey($sekolahId)->value('kode_bast') : '',
            'total' => count($daftar),
            'search' => $search,
            'canCetak' => $request->user()->can('cetak-inventaris-bm'),
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $sekolahId = $this->resolveSekolahIdWajib($request);
        $search = trim((string) $request->input('search', ''));

        $baris = collect($this->daftar($sekolahId, $search))->values()->map(fn (array $item, int $index): array => [
            $index + 1,
            $item['no_urut'] ?: '',
            $item['nomor'] ?: '',
            $item['nama_barang'],
            $item['no_reg'],
            $item['kondisi'],
            $item['penerima'],
            $item['tanggal'],
        ]);

        return Excel::download(
            new RekapanBastExport($baris->all()),
            'Laporan_Rekapan_BAST_'.now()->format('Y-m-d').'.xlsx',
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function daftar(?string $sekolahId, string $search = ''): array
    {
        return Dokumen::query()
            ->when($sekolahId, fn (Builder $q) => $q->where('sekolah_id', $sekolahId))
            ->where('jenis', 'bast')
            // Pencarian harus di query, bukan setelah limit: BAST yang cocok
            // bisa berada di luar 100 dokumen terbaru.
            ->when($search !== '', fn (Builder $q) => $this->whereAnyLike(
                $q,
                ['payload->nama_format', 'payload->penerima->nama'],
                $search,
            ))
            ->latest('created_at')
            ->limit(100)
            ->get(['id', 'nomor', 'tanggal', 'nomor_dibatalkan_pada', 'payload', 'created_at'])
            ->map(function (Dokumen $dokumen): array {
                $payload = $dokumen->payload ?? [];
                $unit = $payload['units'][0] ?? $payload['unit'] ?? [];
                $labelPenerima = ['B' => 'Baik', 'RR' => 'Rusak Ringan', 'RB' => 'Rusak Berat', 'H' => 'Hilang'];
                $nomor = (string) $dokumen->nomor;

                return [
                    'id' => $dokumen->id,
                    'nomor' => $dokumen->nomor,
                    'no_urut' => $nomor !== '' ? explode('/', $nomor)[0] : '',
                    'tanggal' => $dokumen->tanggal?->translatedFormat('d M Y'),
                    'dibatalkan' => $dokumen->nomor_dibatalkan_pada !== null,
                    'penerima' => $payload['nama_format'] ?? $payload['penerima']['nama'] ?? '-',
                    'nama_barang' => $unit['nama_barang'] ?? '-',
                    'no_reg' => $unit['nomor_register'] ?? '-',
                    'kondisi' => $labelPenerima[$unit['kondisi'] ?? ''] ?? '-',
                ];
            })
            ->values()
            ->all();
    }

    public function update(Request $request, Dokumen $dokumen): RedirectResponse
    {
        $this->pastikanSekolahSendiri($request, $dokumen->sekolah_id);
        abort_unless($dokumen->jenis === 'bast', 404);

        $validated = $request->validate([
            'nomor' => ['required', 'string', 'max:150'],
        ]);

        // Lock sekolah + guard di transaksi yang sama: nomor yang sudah pernah
        // terbit (termasuk yang dibatalkan) tidak boleh dipakai dokumen lain.
        DB::transaction(function () use ($dokumen, $validated): void {
            Sekolah::whereKey($dokumen->sekolah_id)->lockForUpdate()->firstOrFail();
            $this->guard->pastikanTersedia($dokumen->sekolah_id, 'bast', $validated['nomor'], $dokumen->id);

            $dokumen->update(['nomor' => $validated['nomor'], 'nomor_dibatalkan_pada' => null]);
        });

        return back()->with('success', 'Nomor urut surat berhasil diperbarui!');
    }

    public function destroy(Request $request, Dokumen $dokumen): RedirectResponse
    {
        $this->pastikanSekolahSendiri($request, $dokumen->sekolah_id);
        abort_unless($dokumen->jenis === 'bast', 404);

        $validated = $request->validate([
            'alasan' => ['required', 'string', 'max:500'],
        ]);

        $dokumen->update([
            'nomor' => null,
            'nomor_dibatalkan_pada' => now(),
            'payload' => array_merge($dokumen->payload ?? [], [
                'pembatalan' => [
                    'alasan' => $validated['alasan'],
                    'oleh' => $request->user()->name,
                    'pada' => now()->toIso8601String(),
                    // Serial disimpan supaya penomoran otomatis berikutnya tidak
                    // memakai ulang nomor yang pernah terbit.
                    'nomor_urut' => $dokumen->nomor !== null ? explode('/', (string) $dokumen->nomor)[0] : null,
                ],
            ]),
        ]);

        return back()->with('success', 'Nomor urut berhasil dikosongkan kembali!');
    }
}
