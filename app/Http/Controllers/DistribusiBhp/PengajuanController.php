<?php

namespace App\Http\Controllers\DistribusiBhp;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Controller;
use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\Pengajuan;
use App\Services\DistribusiBhp\StokService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'sumber_dana' => ['nullable', Rule::in(['BOS', 'BOPD'])],
        ])['sumber_dana'] ?? 'BOS';
        $sumberDana = strtoupper((string) $sumberDana);

        $items = Pengajuan::with(['details'])
            ->when($sekolahId, fn ($q) => $q->where('sekolah_id', $sekolahId))
            // Operator divisi hanya melihat pengajuan divisinya sendiri.
            ->when($request->user()->divisi_id, fn ($q, $divisiId) => $q->where('divisi_id', $divisiId))
            ->where('sumber_dana', $sumberDana)
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('DistribusiBhp/Pengajuan/Index', [
            'items' => $items,
            'sumberDana' => $sumberDana,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $sekolahId = $this->resolveSekolahId($request);

        $validated = $request->validate([
            'sumber_dana' => ['required', Rule::in(['BOS', 'BOPD'])],
            'tanggal' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.barang_id' => 'required|uuid|exists:bhp_barang,id',
            'items.*.jumlah_diminta' => 'required|integer|min:1',
            'items.*.keperluan' => 'nullable|string|max:255',
        ]);

        // Validasi isolasi sumber dana SEBELUM transaksi: barang pada satu
        // pengajuan wajib se-sumber dana dengan pengajuannya.
        $barangIds = collect($validated['items'])->pluck('barang_id')->all();
        $barangs = Barang::whereIn('id', $barangIds)->get()->keyBy('id');

        foreach ($validated['items'] as $index => $item) {
            $barang = $barangs->get($item['barang_id']);
            if ($barang && $barang->sumber_dana !== $validated['sumber_dana']) {
                throw ValidationException::withMessages([
                    "items.{$index}.barang_id" => 'Barang '.$barang->nama_barang.' tidak sesuai dengan sumber dana '.$validated['sumber_dana'].'.',
                ]);
            }
        }

        $pengajuan = DB::transaction(function () use ($validated, $sekolahId, $request, $barangs) {
            $pengajuan = Pengajuan::create([
                'sekolah_id' => $sekolahId,
                // divisi_id selalu dari user login; input request tidak pernah dipercaya.
                'divisi_id' => $request->user()->divisi_id,
                'user_id' => $request->user()->id,
                'tanggal' => $validated['tanggal'],
                'sumber_dana' => $validated['sumber_dana'],
                'status' => 'draft',
            ]);

            foreach ($validated['items'] as $item) {
                /** @var Barang $barang */
                $barang = $barangs->get($item['barang_id']);

                $pengajuan->details()->create([
                    'barang_id' => $barang->id,
                    'jumlah_diminta' => $item['jumlah_diminta'],
                    'keperluan' => $item['keperluan'] ?? null,
                ]);
            }

            return $pengajuan;
        });

        return redirect()
            ->route('distribusi-bhp.pengajuan.index', ['sumber_dana' => $pengajuan->sumber_dana])
            ->with('success', 'Pengajuan berhasil dibuat sebagai draft.');
    }

    public function submit(Request $request, Pengajuan $pengajuan): RedirectResponse
    {
        $this->authorizeSekolah($request, $pengajuan);

        try {
            $this->stok->submit($pengajuan->load('details'));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pengajuan berhasil disubmit dan stok direservasi.');
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
