<?php

namespace App\Http\Controllers\PencatatanBm;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Concerns\TenantScope;
use App\Http\Controllers\Controller;
use App\Models\PencatatanBm\Foto;
use App\Models\PencatatanBm\InventarisUnit;
use App\Services\PencatatanBm\DokumenInventarisService;
use App\Services\PencatatanBm\QrLabelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UnitInventarisController extends Controller
{
    use ResolvesSekolah, TenantScope;

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
}
