<?php

namespace App\Http\Controllers\DistribusiBhp;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Controller;
use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\Divisi;
use App\Models\DistribusiBhp\Pengajuan;
use App\Services\DistribusiBhp\StokService;
use App\Support\DistribusiBhp\SumberDana;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class PengajuanController extends Controller
{
    use ResolvesSekolah;

    public function __construct(private readonly StokService $stok) {}

    public function index(Request $request): Response
    {
        $sekolahId = $this->resolveSekolahId($request);
        $sumberDana = $request->validate([
            'sumber_dana' => ['nullable', Rule::in(SumberDana::SEMUA)],
        ])['sumber_dana'] ?? SumberDana::BOS;
        $sumberDana = SumberDana::normalisasi($sumberDana);

        return SumberDana::dengan($sumberDana, function () use ($sekolahId, $sumberDana, $request): Response {
            $items = Pengajuan::with(['details.barang'])
                ->when($sekolahId, fn ($q) => $q->where('sekolah_id', $sekolahId))
                // Operator divisi hanya melihat pengajuan divisinya sendiri.
                ->when($request->user()->divisi_id, fn ($q, $divisiId) => $q->where('divisi_id', $divisiId))
                ->latest('id')
                ->paginate(25)
                ->withQueryString();

            // Stok tersedia per barang (scoped sekolah) supaya form menampilkan
            // sisa nyata dan operator tidak mengajukan melebihi stok.
            $barang = Barang::orderBy('nama_barang')->get(['id', 'kode_barang', 'nama_barang', 'satuan']);

            $stok = collect(app(StokService::class)->rekapPerBarang($barang, $sekolahId));

            return Inertia::render('DistribusiBhp/Pengajuan/Index', [
                'items' => $items,
                'sumberDana' => $sumberDana,
                'barang' => $barang->map(fn (Barang $b): array => [
                    'id' => $b->id,
                    'kode_barang' => $b->kode_barang,
                    'nama_barang' => $b->nama_barang,
                    'satuan' => $b->satuan,
                    'tersedia' => $stok[$b->id]['tersedia'] ?? 0,
                ])->values(),
            ]);
        });
    }

    public function store(Request $request): RedirectResponse
    {
        $sekolahId = $this->resolveSekolahId($request);
        $validated = $this->validatePayload($request);
        $sumberDana = SumberDana::normalisasi($validated['sumber_dana']);

        return SumberDana::dengan($sumberDana, function () use ($validated, $sekolahId, $request, $sumberDana): RedirectResponse {
            $this->pastikanDivisiSekolah($request, $sekolahId);
            $barangs = $this->barangsSesuaiDana($validated);

            $pengajuan = DB::transaction(function () use ($validated, $sekolahId, $request, $barangs) {
                $pengajuan = Pengajuan::create([
                    'sekolah_id' => $sekolahId,
                    // divisi_id selalu dari user login; input request tidak pernah dipercaya.
                    'divisi_id' => $request->user()->divisi_id,
                    'user_id' => $request->user()->id,
                    'tanggal' => $validated['tanggal'],
                    'status' => 'draft',
                ]);

                $this->simpanDetail($pengajuan, $validated, $barangs);

                return $pengajuan;
            });

            return redirect()
                ->route('distribusi-bhp.pengajuan.index', ['sumber_dana' => $sumberDana])
                ->with('success', 'Pengajuan berhasil dibuat sebagai draft.');
        });
    }

    /**
     * Ubah draft (tanggal + daftar barang). Hanya draft yang boleh diubah —
     * begitu disubmit, reservasi stok sudah dibentuk dan perubahan harus
     * lewat reject lalu pengajuan baru.
     */
    public function update(Request $request, Pengajuan $pengajuan): RedirectResponse
    {
        $this->authorizeSekolah($request, $pengajuan);

        if (! $pengajuan->isDraft()) {
            return back()->with('error', 'Hanya pengajuan berstatus draft yang dapat diubah.');
        }

        $validated = $this->validatePayload($request);
        $sumberDana = SumberDana::normalisasi($validated['sumber_dana']);

        if ($pengajuan->sumber_dana !== $sumberDana) {
            return back()->with('error', 'Sumber dana pengajuan tidak dapat diubah.');
        }

        SumberDana::dengan($sumberDana, function () use ($pengajuan, $validated, $request) {
            $this->pastikanDivisiSekolah($request, (string) $pengajuan->sekolah_id);
            $barangs = $this->barangsSesuaiDana($validated);

            DB::transaction(function () use ($pengajuan, $validated, $barangs) {
                // Kunci dulu agar edit bersamaan tidak saling menimpa.
                $pengajuan = Pengajuan::whereKey($pengajuan->id)->lockForUpdate()->firstOrFail();

                if ($pengajuan->status !== 'draft') {
                    throw ValidationException::withMessages([
                        'status' => 'Pengajuan sudah tidak berstatus draft.',
                    ]);
                }

                $pengajuan->update(['tanggal' => $validated['tanggal']]);

                $pengajuan->details()->delete();
                $this->simpanDetail($pengajuan, $validated, $barangs);
            });
        });

        return back()->with('success', 'Pengajuan berhasil diperbarui.');
    }

    public function destroy(Request $request, Pengajuan $pengajuan): RedirectResponse
    {
        $this->authorizeSekolah($request, $pengajuan);

        if (! $pengajuan->isDraft()) {
            return back()->with('error', 'Hanya pengajuan draft yang dapat dihapus.');
        }

        if ($pengajuan->sumber_dana !== SumberDana::normalisasi((string) $request->input('sumber_dana', SumberDana::BOS))) {
            return back()->with('error', 'Sumber dana tidak sesuai dengan pengajuan.');
        }

        DB::transaction(function () use ($pengajuan) {
            $pengajuan = Pengajuan::whereKey($pengajuan->id)->lockForUpdate()->firstOrFail();

            if ($pengajuan->status !== 'draft') {
                throw ValidationException::withMessages([
                    'status' => 'Pengajuan sudah tidak berstatus draft.',
                ]);
            }

            $pengajuan->details()->delete();
            $pengajuan->delete();
        });

        return redirect()
            ->route('distribusi-bhp.pengajuan.index', ['sumber_dana' => $pengajuan->sumber_dana])
            ->with('success', 'Pengajuan draft berhasil dihapus.');
    }

    public function submit(Request $request, Pengajuan $pengajuan): RedirectResponse
    {
        $this->authorizeSekolah($request, $pengajuan);

        $sumberDana = SumberDana::normalisasi((string) $request->input('sumber_dana', $pengajuan->sumber_dana));
        if ($pengajuan->sumber_dana !== $sumberDana) {
            return back()->with('error', 'Sumber dana tidak sesuai dengan pengajuan.');
        }

        try {
            $this->stok->submit($pengajuan->load('details'));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pengajuan berhasil disubmit dan stok direservasi.');
    }

    /**
     * @return array<string,mixed>
     */
    private function validatePayload(Request $request): array
    {
        $sumberDana = SumberDana::normalisasi((string) $request->input('sumber_dana'));

        return $request->validate([
            'sumber_dana' => ['required', Rule::in(SumberDana::SEMUA)],
            'tanggal' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.barang_id' => ['required', 'uuid', SumberDana::existsRule('barang', $sumberDana)],
            'items.*.jumlah_diminta' => 'required|integer|min:1',
            'items.*.keperluan' => 'nullable|string|max:255',
        ]);
    }

    /**
     * Barang pada satu pengajuan wajib se-sumber dana dengan pengajuannya,
     * divalidasi SEBELUM transaksi.
     *
     * @param  array<string,mixed>  $validated
     * @return Collection<string, Barang>
     */
    private function barangsSesuaiDana(array $validated): Collection
    {
        $barangIds = collect($validated['items'])->pluck('barang_id')->all();
        $barangs = Barang::whereIn('id', $barangIds)->get()->keyBy('id');

        foreach ($validated['items'] as $index => $item) {
            if (! $barangs->has($item['barang_id'])) {
                throw ValidationException::withMessages([
                    "items.{$index}.barang_id" => 'Barang '.$item['barang_id'].' tidak sesuai dengan sumber dana '.$validated['sumber_dana'].'.',
                ]);
            }
        }

        return $barangs;
    }

    /**
     * Divisi user harus benar-benar milik sekolah pengajuan. Tanpa ini, user
     * dengan divisi_id salah konfigurasi bisa membuat pengajuan lintas sekolah.
     */
    private function pastikanDivisiSekolah(Request $request, ?string $sekolahId): void
    {
        $divisiId = $request->user()->divisi_id;

        if (! $divisiId || ! $sekolahId) {
            return;
        }

        $valid = Divisi::whereKey($divisiId)
            ->where('sekolah_id', $sekolahId)
            ->exists();

        if (! $valid) {
            throw ValidationException::withMessages([
                'divisi_id' => 'Divisi Anda tidak terhubung ke sekolah pengajuan ini.',
            ]);
        }
    }

    /**
     * @param  array<string,mixed>  $validated
     * @param  Collection<string, Barang>  $barangs
     */
    private function simpanDetail(Pengajuan $pengajuan, array $validated, Collection $barangs): void
    {
        foreach ($validated['items'] as $item) {
            /** @var Barang $barang */
            $barang = $barangs->get($item['barang_id']);

            $pengajuan->details()->create([
                'barang_id' => $barang->id,
                'jumlah_diminta' => $item['jumlah_diminta'],
                'keperluan' => $item['keperluan'] ?? null,
            ]);
        }
    }

    private function authorizeSekolah(Request $request, Pengajuan $pengajuan): void
    {
        $sekolahId = $this->resolveSekolahId($request);

        if ($sekolahId && $pengajuan->sekolah_id !== $sekolahId) {
            abort(403, 'Tidak memiliki akses ke pengajuan ini.');
        }

        // Operator divisi hanya boleh menyentuh pengajuan divisinya sendiri.
        $divisiId = $request->user()->divisi_id;
        if ($divisiId && $pengajuan->divisi_id !== $divisiId) {
            abort(403, 'Pengajuan ini bukan milik divisi Anda.');
        }
    }
}
