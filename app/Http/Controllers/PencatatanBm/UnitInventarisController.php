<?php

namespace App\Http\Controllers\PencatatanBm;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Concerns\SearchesText;
use App\Http\Controllers\Concerns\TenantScope;
use App\Http\Controllers\Controller;
use App\Models\Master\Sekolah;
use App\Models\PencatatanBm\Foto;
use App\Models\PencatatanBm\InventarisUnit;
use App\Services\PencatatanBm\DokumenInventarisService;
use App\Services\PencatatanBm\QrLabelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UnitInventarisController extends Controller
{
    use ResolvesSekolah, SearchesText, TenantScope;

    public function __construct(
        private readonly DokumenInventarisService $dokumen,
        private readonly QrLabelService $qr,
    ) {}

    public function storeFoto(Request $request, InventarisUnit $unit): RedirectResponse
    {
        $this->pastikanSekolahSendiri($request, $unit->sekolah_id);
        Gate::authorize('update', $unit);

        $request->validate([
            'foto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $berkas = $request->file('foto');
        $path = $berkas->store("inventaris/foto/{$unit->sekolah_id}", 'public');

        $foto = Foto::create([
            'sekolah_id' => $unit->sekolah_id,
            'inventaris_unit_id' => $unit->id,
            'path' => (string) $path,
            'mime' => (string) $berkas->getMimeType(),
            'size' => (int) $berkas->getSize(),
            'checksum' => hash_file('sha256', $berkas->getRealPath()) ?: '',
            'uploaded_by' => $request->user()->id,
        ]);

        $unit->update(['foto_path' => $foto->path]);

        return back()->with('success', 'Foto unit diunggah.');
    }

    public function foto(Foto $foto): StreamedResponse
    {
        $this->pastikanSekolahSendiri(request(), $foto->sekolah_id);
        Gate::authorize('view', $foto->unit);

        return $this->dokumen->unduhFoto($foto);
    }

    public function qr(InventarisUnit $unit): Response
    {
        $this->pastikanSekolahSendiri(request(), $unit->sekolah_id);

        return response($this->qr->svg($unit), 200, ['Content-Type' => 'image/svg+xml']);
    }

    public function label(InventarisUnit $unit): Response
    {
        $this->pastikanSekolahSendiri(request(), $unit->sekolah_id);

        return $this->dokumen->labelUnit($unit);
    }

    /**
     * Halaman labeling (acuan: cetak_label.php): daftar unit dengan checkbox,
     * cari, dan filter tahun — label dicetak hanya untuk yang dipilih.
     */
    public function halaman(Request $request): InertiaResponse
    {
        $sekolahId = $this->resolveSekolahIdWajib($request);
        $search = trim((string) $request->input('search', ''));
        $tahun = (int) ($request->input('tahun') ?: 0);

        $units = InventarisUnit::query()
            ->with('ruang:id,nama_ruang')
            ->where('sekolah_id', $sekolahId)
            ->whereIn('status', [InventarisUnit::STATUS_AKTIF, InventarisUnit::STATUS_DIKEMBALIKAN])
            ->when($tahun > 0, fn ($q) => $q->where('tahun_perolehan', $tahun))
            ->when($search !== '', fn ($q) => $this->whereAnyLike(
                $q,
                ['nama_barang', 'merk_tipe', 'kode_barang', 'nomor_register'],
                $search,
            ))
            ->orderBy('kode_barang')
            ->orderBy('nomor_register')
            ->limit(500)
            ->get();

        return Inertia::render('PencatatanBm/Label/Index', [
            'units' => $units->map(fn (InventarisUnit $unit): array => [
                'id' => $unit->id,
                'kode_barang' => $unit->kode_barang,
                'nama_barang' => $unit->nama_barang,
                'merk_tipe' => $unit->merk_tipe,
                'nomor_register' => $unit->nomor_register,
                'tahun_perolehan' => $unit->tahun_perolehan,
                'ruang' => $unit->ruang?->nama_ruang,
            ])->all(),
            'tahunList' => InventarisUnit::query()
                ->where('sekolah_id', $sekolahId)
                ->distinct()
                ->orderByDesc('tahun_perolehan')
                ->pluck('tahun_perolehan'),
            'filter' => ['search' => $search, 'tahun' => (string) ($tahun ?: '')],
        ]);
    }

    /**
     * Cetak label untuk unit yang dipilih di halaman labeling.
     */
    public function cetakMassal(Request $request): Response
    {
        $sekolahId = $this->resolveSekolahIdWajib($request);

        $validated = $request->validate([
            'unit_ids' => ['array'],
            'unit_ids.*' => ['uuid', 'exists:inventaris_unit,id'],
        ]);

        $unitIds = $validated['unit_ids'] ?? [];

        if ($unitIds === []) {
            return back()->with('error', 'Silakan pilih minimal satu barang untuk dicetak!');
        }

        $units = InventarisUnit::query()
            ->where('sekolah_id', $sekolahId)
            ->whereIn('id', $unitIds)
            ->orderBy('kode_barang')
            ->orderBy('nomor_register')
            ->get();

        if ($units->count() !== count($unitIds)) {
            abort(404);
        }

        return $this->dokumen->labelMassal($this->sekolah($sekolahId), $units);
    }

    private function sekolah(string $sekolahId): Sekolah
    {
        return Sekolah::findOrFail($sekolahId);
    }
}
