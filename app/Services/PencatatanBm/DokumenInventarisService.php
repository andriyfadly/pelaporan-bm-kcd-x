<?php

namespace App\Services\PencatatanBm;

use App\Models\Master\KodeBarang;
use App\Models\Master\Pegawai;
use App\Models\Master\Ruang;
use App\Models\Master\Sekolah;
use App\Models\PencatatanBm\Dokumen;
use App\Models\PencatatanBm\Foto;
use App\Models\PencatatanBm\InventarisUnit;
use App\Models\PencatatanBm\Mutasi;
use App\Services\Master\PenugasanPegawaiService;
use App\Support\NamaGelar;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Cetak dokumen inventaris. Data sekolah dan penandatangan dibaca dari master
 * data, lalu snapshot-nya disimpan di payload dokumen — cetak ulang tetap sama
 * setelah koreksi master.
 */
class DokumenInventarisService
{
    private const F4 = [0, 0, 609.45, 935.43];

    private const F4_LANDSCAPE = [0, 0, 935.43, 609.45];

    /** @var array<string, string> */
    private const VIEW = [
        'bast' => 'pencatatan-bm.bast',
        'fakta_integritas' => 'pencatatan-bm.fakta-integritas',
        'pengembalian' => 'pencatatan-bm.berita-acara-pengembalian',
        'surat_pengembalian' => 'pencatatan-bm.surat-pengembalian',
    ];

    public function __construct(
        private readonly QrLabelService $qr,
        private readonly PenugasanPegawaiService $penugasan,
        private readonly NomorDokumenInventarisService $nomor,
        private readonly NomorDokumenGuard $guard,
    ) {}

    /**
     * Kartu Inventaris Ruangan. Opsi penanda tangan mengikuti acuan
     * (`show_ks`/`show_wks`/`show_pb`/`show_pj`).
     *
     * @param  Collection<int, InventarisUnit>  $units
     * @param  array{show_ks?: bool, show_wks?: bool, show_pb?: bool, show_pj?: bool, tanggal?: string, penanggung_jawab?: ?string}  $opsi
     */
    public function kirRuang(Ruang $ruang, Collection $units, array $opsi = []): Response
    {
        $tanggal = $opsi['tanggal'] ?? now()->toDateString();
        $pj = ! empty($opsi['penanggung_jawab'])
            ? Pegawai::where('sekolah_id', $ruang->sekolah_id)->find($opsi['penanggung_jawab'])
            : null;

        return $this->pdf('pencatatan-bm.kir', [
            'judul' => 'Kartu Inventaris Ruangan',
            'ruang' => $ruang,
            'units' => $units,
            'sekolah' => $ruang->sekolah,
            'pejabat' => $this->pejabat($ruang->sekolah_id, $tanggal),
            'labelJabatan' => $this->penugasan->labelPenandatangan((string) $ruang->sekolah_id),
            'opsi' => [
                'show_ks' => (bool) ($opsi['show_ks'] ?? false),
                'show_wks' => (bool) ($opsi['show_wks'] ?? false),
                'show_pb' => (bool) ($opsi['show_pb'] ?? false),
                'show_pj' => (bool) ($opsi['show_pj'] ?? false),
                'tanggal' => Carbon::parse($tanggal),
                'penanggung_jawab' => $pj,
            ],
        ], self::F4_LANDSCAPE, 'landscape')->download($this->namaFile('KIR', $ruang->nama_ruang));
    }

    /**
     * @param  Collection<int, InventarisUnit>  $units
     */
    public function bukuInventaris(Sekolah $sekolah, Collection $units, int $tahun, ?string $tanggal = null): Response
    {
        return $this->pdf('pencatatan-bm.buku-inventaris', [
            'judul' => 'Buku Inventaris',
            'tahun' => $tahun,
            'units' => $units,
            'sekolah' => $sekolah,
            'pejabat' => $this->pejabat($sekolah->id, $tanggal),
            'labelJabatan' => $this->penugasan->labelPenandatangan((string) $sekolah->id),
            'tanggal' => Carbon::parse($tanggal ?? now()->toDateString()),
        ], self::F4_LANDSCAPE, 'landscape')
            ->download($this->namaFile('Buku-Inventaris', $tahun > 0 ? (string) $tahun : 'Semua'));
    }

    /**
     * @param  Collection<int, InventarisUnit>  $units
     */
    public function sensus(Sekolah $sekolah, Collection $units, string $search = ''): Response
    {
        return $this->pdf('pencatatan-bm.sensus', [
            'judul' => 'Sensus Aset',
            'units' => $units,
            'sekolah' => $sekolah,
            'search' => $search,
        ], self::F4_LANDSCAPE, 'landscape')
            ->download($this->namaFile('Sensus-Aset', $search !== '' ? $search : 'Semua'));
    }

    /**
     * @param  array<string, mixed>  $tambahan
     */
    /**
     * @param  array<string, mixed>  $tambahan
     */
    public function simpan(Dokumen $dokumen, ?string $view = null, array $tambahan = []): Dokumen
    {
        // Label jabatan penanda tangan dibekukan saat dokumen dibuat; koreksi
        // master jabatan setelahnya tidak boleh mengubah cetak ulang.
        $payload = $dokumen->payload ?? [];

        if (! isset($payload['labelJabatan'])) {
            $payload['labelJabatan'] = $this->penugasan->labelPenandatangan((string) $dokumen->sekolah_id);
            $dokumen->update(['payload' => $payload]);
        }

        $pdf = $this->pdf($view ?? $this->view($dokumen->jenis), array_merge([
            'judul' => $this->judul($dokumen->jenis),
            'dokumen' => $dokumen,
            'payload' => $payload,
            'sekolah' => $dokumen->sekolah,
            'pejabat' => $this->pejabatTersimpan($dokumen),
            'labelJabatan' => $payload['labelJabatan'],
        ], $tambahan), self::F4);

        $path = "inventaris/dokumen/{$dokumen->sekolah_id}/{$dokumen->jenis}-{$dokumen->id}.pdf";

        Storage::disk('local')->put($path, $pdf->output());

        $dokumen->update(['file_path' => $path]);

        return $dokumen;
    }

    public function unduh(Dokumen $dokumen): Response
    {
        $pdf = $this->pdf($this->view($dokumen->jenis), [
            'judul' => $this->judul($dokumen->jenis),
            'dokumen' => $dokumen,
            'payload' => $dokumen->payload,
            'sekolah' => $dokumen->sekolah,
            'pejabat' => $this->pejabatTersimpan($dokumen),
            'labelJabatan' => $dokumen->payload['labelJabatan']
                ?? $this->penugasan->labelPenandatangan((string) $dokumen->sekolah_id),
        ], self::F4);

        return $pdf->download($this->namaFile($this->judul($dokumen->jenis), $dokumen->nomor ?? $dokumen->id));
    }

    /**
     * Label 2-up untuk banyak unit sekaligus.
     *
     * @param  Collection<int, InventarisUnit>  $units
     */
    public function labelMassal(Sekolah $sekolah, Collection $units): Response
    {
        // Uraian diambil dari katalog kode barang (sumber nama resmi aset);
        // fallback ke nama barang yang tersimpan di unit.
        $katalog = KodeBarang::query()
            ->whereIn('kode_barang', $units->pluck('kode_barang')->unique())
            ->pluck('uraian', 'kode_barang');

        $label = $units->mapWithKeys(fn (InventarisUnit $unit): array => [
            $unit->id => [
                'uraian' => $katalog[$unit->kode_barang] ?? ($unit->nama_barang ?? '-'),
                'qr' => $this->qr->svg($unit),
            ],
        ])->all();

        return $this->pdf('pencatatan-bm.label', [
            'sekolah' => $sekolah,
            'units' => $units,
            'label' => $label,
        ])->download($this->namaFile('Label', $sekolah->nama_sekolah));
    }

    public function labelUnit(InventarisUnit $unit): Response
    {
        return $this->labelMassal($unit->sekolah()->firstOrFail(), collect([$unit]));
    }

    /**
     * Peta unit => nomor BAST aktif yang snapshot penitipannya masih cocok
     * dengan penerima/ruang unit saat ini. BAST lama tanpa snapshot custody
     * hanya bisa dibandingkan lewat `inventaris_pegawai_id`.
     *
     * @param  array<int, string>|null  $unitIds  null = seluruh unit sekolah
     * @return array<string, string>
     */
    public function petaBastAktif(string $sekolahId, ?array $unitIds = null): array
    {
        $dokumen = Dokumen::query()
            ->where('sekolah_id', $sekolahId)
            ->where('jenis', 'bast')
            ->whereNotNull('nomor')
            ->whereNull('nomor_dibatalkan_pada')
            ->orderByDesc('tanggal')
            ->orderByDesc('created_at')
            ->get(['id', 'nomor', 'inventaris_unit_id', 'inventaris_pegawai_id', 'payload']);

        if ($dokumen->isEmpty()) {
            return [];
        }

        $kandidatIds = $unitIds ?? $dokumen->flatMap(function (Dokumen $bast): array {
            $ids = [];

            if ($bast->inventaris_unit_id !== null) {
                $ids[] = $bast->inventaris_unit_id;
            }

            foreach (($bast->payload['units'] ?? []) as $snapshot) {
                if (is_array($snapshot) && is_string($snapshot['id'] ?? null)) {
                    $ids[] = $snapshot['id'];
                }
            }

            return $ids;
        })->unique()->values()->all();

        if ($kandidatIds === []) {
            return [];
        }

        $custody = InventarisUnit::query()
            ->where('sekolah_id', $sekolahId)
            ->whereIn('id', $kandidatIds)
            ->get(['id', 'pegawai_id', 'ruang_id'])
            ->keyBy('id');

        $peta = [];

        foreach ($dokumen as $bast) {
            foreach (($bast->payload['units'] ?? []) as $snapshot) {
                $id = is_array($snapshot) ? ($snapshot['id'] ?? null) : null;

                if (! is_string($id) || isset($peta[$id])) {
                    continue;
                }

                $unit = $custody->get($id);

                if ($unit === null) {
                    continue;
                }

                $punyaCustody = array_key_exists('pegawai_id', $snapshot) || array_key_exists('ruang_id', $snapshot);

                if (! $punyaCustody) {
                    continue;
                }

                if ((string) ($snapshot['pegawai_id'] ?? '') === (string) $unit->pegawai_id
                    && (string) ($snapshot['ruang_id'] ?? '') === (string) $unit->ruang_id) {
                    $peta[$id] = (string) $bast->nomor;
                }
            }

            // Dokumen lama satu unit tidak menyimpan ruang, jadi hanya pegawai
            // yang bisa dibandingkan. Jangan menebak ruang yang tidak disnapshot.
            $id = $bast->inventaris_unit_id;

            if ($id === null || isset($peta[$id])) {
                continue;
            }

            $punyaCustody = collect($bast->payload['units'] ?? [])->contains(
                fn (mixed $snapshot): bool => is_array($snapshot)
                    && ($snapshot['id'] ?? null) === $id
                    && (array_key_exists('pegawai_id', $snapshot) || array_key_exists('ruang_id', $snapshot)),
            );

            $unit = $custody->get($id);

            if (! $punyaCustody && $unit !== null
                && (string) $bast->inventaris_pegawai_id === (string) $unit->pegawai_id) {
                $peta[$id] = (string) $bast->nomor;
            }
        }

        return $peta;
    }

    /**
     * Payload Berita Acara Pengembalian untuk satu baris register: banyak unit +
     * mutasi pengembalian + nomor BAST terakhir, semuanya dibekukan ke dokumen.
     *
     * @param  Collection<int, InventarisUnit>  $units
     * @param  Collection<int, Mutasi>  $mutasis
     * @return array<string, mixed>
     */
    public function payloadPengembalianBanyak(Collection $units, Collection $mutasis, string $tanggal): array
    {
        $pertama = $units->first();
        $unitIds = $units->pluck('id')->all();
        $mutasiPertama = $mutasis->first();
        $mutasiPertama->loadMissing('asalPegawai');

        $bastPerUnit = $this->petaBastAktif((string) $pertama->sekolah_id, $unitIds);
        $nomorUnik = collect($bastPerUnit)->unique()->values();

        $penerima = $mutasiPertama->asalPegawai;

        return [
            'sekolah_id' => $pertama->sekolah_id,
            'sekolah' => [
                'nama_sekolah' => $pertama->sekolah?->nama_sekolah,
                'npsn' => $pertama->sekolah?->npsn,
                'alamat' => $pertama->sekolah?->alamat,
                'telepon' => $pertama->sekolah?->telepon,
                'email' => $pertama->sekolah?->email,
                'website' => $pertama->sekolah?->website,
                'kota_kab' => $pertama->sekolah?->kota_kab,
                'kode_pos' => $pertama->sekolah?->kode_pos,
            ],
            // `unit` dipertahankan untuk pembaca payload lama; `units` adalah
            // sumber rincian pada template.
            'unit' => $this->qr->snapshotUnit($pertama),
            'units' => $units->map(fn (InventarisUnit $unit): array => $this->qr->snapshotUnit($unit))->values()->all(),
            'penerima' => [
                'nama' => $penerima?->nama_lengkap,
                'nip' => $penerima?->nip,
                'pangkat_golongan' => $penerima?->pangkat_golongan,
                'jabatan' => null,
            ],
            'penerima_id' => $penerima?->id,
            'nama_format' => NamaGelar::format($penerima?->nama_lengkap),
            // Satu nomor hanya bila seluruh unit merujuk BAST yang sama.
            'nomor_bast' => $nomorUnik->count() === 1 ? $nomorUnik->first() : null,
            'bast_per_unit' => $bastPerUnit,
            'mutasi_ids' => $mutasis->pluck('id')->values()->all(),
            'mutasi' => $this->qr->snapshotMutasi($mutasiPertama),
            'pejabat' => $this->pejabat($pertama->sekolah_id, $tanggal),
            'tanggal' => $tanggal,
        ];
    }

    /**
     * Terbitkan Berita Acara Pengembalian bernomor + berkas PDF-nya.
     *
     * @param  array<string, mixed>  $payload
     */
    public function simpanPengembalianBanyak(array $payload, string $tanggal, ?string $userId): Dokumen
    {
        $nomor = $this->nomor->berikutnya($payload['sekolah_id'], 'pengembalian');
        $this->guard->pastikanTersedia($payload['sekolah_id'], 'pengembalian', $nomor);

        $dokumen = Dokumen::create([
            'sekolah_id' => $payload['sekolah_id'],
            'jenis' => 'pengembalian',
            'nomor' => $nomor,
            'tanggal' => $tanggal,
            'inventaris_unit_id' => null,
            'inventaris_pegawai_id' => $payload['penerima_id'] ?? null,
            'payload' => $payload,
            'user_id' => $userId,
        ]);

        return $this->simpan($dokumen, 'pencatatan-bm.berita-acara-pengembalian');
    }

    public function unduhFoto(Foto $foto): StreamedResponse
    {
        return Storage::disk('public')->download($foto->path);
    }

    /**
     * Snapshot tiga jabatan penandatangan pada tanggal dokumen; dokumen tetap
     * bisa dicetak walau salah satu jabatan belum diisi (tidak seperti
     * approval pengajuan BHP).
     *
     * @return array<string, array<string, mixed>|null>
     */
    public function pejabat(string $sekolahId, ?string $tanggal = null): array
    {
        return $this->penugasan->pejabatPada($sekolahId, $tanggal);
    }

    /**
     * Nama jabatan penandatangan dari `master_data_jabatan`, dipakai blok tanda
     * tangan PDF.
     *
     * @return array<string, string>
     */
    public function labelPenandatangan(string $sekolahId): array
    {
        return $this->penugasan->labelPenandatangan($sekolahId);
    }

    /**
     * Penandatangan yang sudah dibekukan di payload dokumen. Reprint dokumen
     * lama harus memakai ini, bukan lookup hidup, supaya tidak berubah ketika
     * pejabat berganti.
     *
     * @return array<string, array<string, mixed>|null>
     */
    private function pejabatTersimpan(Dokumen $dokumen): array
    {
        $tersimpan = $dokumen->payload['pejabat'] ?? null;

        if (is_array($tersimpan)) {
            return $tersimpan;
        }

        // shortcut: dokumen lama tanpa snapshot pejabat jatuh ke data hidup;
        // tidak ada dokumen seperti itu di database saat migrasi ini dibuat.
        return $this->pejabat($dokumen->sekolah_id, $dokumen->tanggal?->toDateString());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function pdf(string $view, array $data, string|array $paper = 'a4', string $orientation = 'portrait')
    {
        return Pdf::loadView($view, $data)->setPaper($paper, $orientation);
    }

    private function namaFile(string $jenis, string $identitas): string
    {
        return trim(str_replace(['/', '\\'], '-', "{$jenis}-{$identitas}")).'.pdf';
    }

    private function view(string $jenis): string
    {
        // Jenis dokumen di luar Dokumen::JENIS tidak bisa diterbitkan modul ini.
        return self::VIEW[$jenis];
    }

    private function judul(string $jenis): string
    {
        return Dokumen::LABEL_JENIS[$jenis] ?? ucwords(str_replace('_', ' ', $jenis));
    }
}
