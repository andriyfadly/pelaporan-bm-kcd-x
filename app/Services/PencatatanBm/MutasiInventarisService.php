<?php

namespace App\Services\PencatatanBm;

use App\Models\PencatatanBm\Dokumen;
use App\Models\PencatatanBm\InventarisUnit;
use App\Models\PencatatanBm\Mutasi;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Satu pintu perubahan lokasi unit, mengikuti istilah aplikasi acuan:
 *
 * - penyaluran  : penempatan pertama barang ke ruang/penerima
 * - mutasi      : perpindahan setelah barang disalurkan
 * - pengembalian: barang kembali ke gudang (lokasi dikosongkan)
 * - koreksi     : perubahan kondisi/keterangan tanpa perpindahan
 *
 * Semua perubahan state didahului satu baris histori; `sekolah_id` dan
 * `realisasi_id` tidak pernah berubah.
 */
class MutasiInventarisService
{
    public function __construct(private readonly DokumenInventarisService $dokumen) {}

    /**
     * @param  array{ruang_id: string, pegawai_id?: ?string, kondisi?: ?string, tanggal: string, keterangan?: ?string}  $data
     */
    public function salurkan(InventarisUnit $unit, array $data, ?string $userId): Mutasi
    {
        return $this->ubahLokasi($unit, $data, $userId, 'penyaluran');
    }

    /**
     * @param  array{ruang_id?: ?string, pegawai_id?: ?string, kondisi?: ?string, tanggal: string, keterangan?: ?string}  $data
     */
    public function mutasikan(InventarisUnit $unit, array $data, ?string $userId): Mutasi
    {
        return $this->ubahLokasi($unit, $data, $userId, 'mutasi');
    }

    /**
     * Dari halaman detail unit: jenis histori ditentukan dari state unit, bukan
     * dari halaman yang dipakai, supaya tidak ada dua aturan berbeda.
     *
     * @param  array{ruang_id?: ?string, pegawai_id?: ?string, kondisi?: ?string, tanggal: string, keterangan?: ?string}  $data
     */
    public function koreksi(InventarisUnit $unit, array $data, ?string $userId): Mutasi
    {
        return $this->ubahLokasi($unit, $data, $userId, null);
    }

    /**
     * Pengembalian banyak unit dalam satu Berita Acara Pengembalian bernomor,
     * seperti acuan yang mengembalikan satu baris penyaluran (rentang register)
     * per surat. Satu unit = satu baris histori; satu dokumen untuk semuanya.
     *
     * @param  Collection<int, InventarisUnit>  $units
     * @param  array{kondisi: string, tanggal: string, keterangan?: ?string}  $data
     * @return array{mutasis: Collection<int, Mutasi>, dokumen: Dokumen}
     */
    public function kembalikanBanyak(Collection $units, array $data, ?string $userId): array
    {
        return DB::transaction(function () use ($units, $data, $userId): array {
            $terkunci = InventarisUnit::query()
                ->whereIn('id', $units->pluck('id')->all())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($terkunci as $unit) {
                $aktif = $unit->status === InventarisUnit::STATUS_AKTIF
                    && ($unit->ruang_id !== null || $unit->pegawai_id !== null);

                if (! $aktif) {
                    throw ValidationException::withMessages([
                        'unit' => 'Unit ini tidak sedang ditempatkan, jadi tidak bisa dikembalikan.',
                    ]);
                }
            }

            // Satu BA hanya boleh memuat satu jenis barang dan satu penerima,
            // supaya narasi "PIHAK KEDUA" dan rincian barangnya konsisten.
            $pertama = $terkunci->first();
            $seragam = $terkunci->every(fn (InventarisUnit $unit): bool => $unit->kode_barang === $pertama->kode_barang
                && $unit->pegawai_id === $pertama->pegawai_id);

            if (! $seragam) {
                throw ValidationException::withMessages([
                    'unit' => 'Semua unit dalam satu Berita Acara harus barang dan penerima yang sama.',
                ]);
            }

            $mutasis = $terkunci->map(function (InventarisUnit $unit) use ($data, $userId): Mutasi {
                $mutasi = $this->catat($unit, [
                    'jenis' => 'pengembalian',
                    'tujuan_ruang_id' => null,
                    'tujuan_pegawai_id' => null,
                    'tujuan_kondisi' => $data['kondisi'],
                    'tanggal' => $data['tanggal'],
                    'keterangan' => $data['keterangan'] ?? null,
                    'user_id' => $userId,
                ]);

                $unit->update([
                    'ruang_id' => null,
                    'pegawai_id' => null,
                    'kondisi' => $data['kondisi'],
                    'status' => InventarisUnit::STATUS_DIKEMBALIKAN,
                ]);

                return $mutasi;
            })->values();

            $payload = $this->dokumen->payloadPengembalianBanyak(
                $terkunci->values(),
                $mutasis,
                $data['tanggal'],
            );
            $dokumen = $this->dokumen->simpanPengembalianBanyak($payload, $data['tanggal'], $userId);

            return ['mutasis' => $mutasis, 'dokumen' => $dokumen];
        });
    }

    /**
     * @param  array{ruang_id?: ?string, pegawai_id?: ?string, kondisi?: ?string, tanggal: string, keterangan?: ?string}  $data
     * @param  string|null  $jenisDiminta  null = tentukan dari state unit
     */
    private function ubahLokasi(InventarisUnit $unit, array $data, ?string $userId, ?string $jenisDiminta): Mutasi
    {
        return DB::transaction(function () use ($unit, $data, $userId, $jenisDiminta): Mutasi {
            $unit = InventarisUnit::whereKey($unit->id)->lockForUpdate()->firstOrFail();

            if ($unit->status === InventarisUnit::STATUS_DIHAPUS_KOREKSI) {
                throw ValidationException::withMessages([
                    'unit' => 'Unit sudah dihapus karena koreksi sumber dan tidak bisa diubah.',
                ]);
            }

            $tujuanRuang = array_key_exists('ruang_id', $data) ? $data['ruang_id'] : $unit->ruang_id;
            $tujuanPegawai = array_key_exists('pegawai_id', $data) ? $data['pegawai_id'] : $unit->pegawai_id;

            $sudahDitempatkan = $unit->ruang_id !== null || $unit->pegawai_id !== null;
            $pindahTempat = $tujuanRuang !== $unit->ruang_id || $tujuanPegawai !== $unit->pegawai_id;
            $akanDitempatkan = $tujuanRuang !== null || $tujuanPegawai !== null;

            if ($jenisDiminta === 'penyaluran') {
                if ($sudahDitempatkan) {
                    throw ValidationException::withMessages([
                        'unit' => 'Unit sudah disalurkan. Gunakan mutasi untuk memindahkannya.',
                    ]);
                }

                if (! $akanDitempatkan) {
                    throw ValidationException::withMessages([
                        'ruang_id' => 'Penyaluran wajib menentukan ruang atau penanggung jawab.',
                    ]);
                }
            }

            if ($jenisDiminta === 'mutasi') {
                if (! $sudahDitempatkan) {
                    throw ValidationException::withMessages([
                        'unit' => 'Unit belum berada di lokasi. Gunakan penyaluran untuk penempatan pertama.',
                    ]);
                }

                // Mengosongkan lokasi adalah pengembalian, bukan mutasi: kalau
                // dibiarkan, historinya mengklaim perpindahan yang tidak terjadi.
                if (! $akanDitempatkan) {
                    throw ValidationException::withMessages([
                        'ruang_id' => 'Mutasi wajib menentukan ruang atau penanggung jawab tujuan.',
                    ]);
                }
            }

            $jenis = match (true) {
                ! $pindahTempat => 'koreksi',
                ! $sudahDitempatkan && $akanDitempatkan => 'penyaluran',
                default => 'mutasi',
            };

            $kondisiBaru = $data['kondisi'] ?? $unit->kondisi;

            $mutasi = $this->catat($unit, [
                'jenis' => $jenis,
                'tujuan_ruang_id' => $tujuanRuang,
                'tujuan_pegawai_id' => $tujuanPegawai,
                'tujuan_kondisi' => $kondisiBaru,
                'tanggal' => $data['tanggal'],
                'keterangan' => $data['keterangan'] ?? null,
                'user_id' => $userId,
            ]);

            // Status hanya kembali aktif kalau unit benar-benar ditempatkan;
            // unit yang dikembalikan tetap berstatus dikembalikan.
            $unit->update([
                'ruang_id' => $tujuanRuang,
                'pegawai_id' => $tujuanPegawai,
                'kondisi' => $kondisiBaru,
                'status' => $akanDitempatkan ? InventarisUnit::STATUS_AKTIF : $unit->status,
            ]);

            return $mutasi;
        });
    }

    /**
     * @param  array<string, mixed>  $atribut
     */
    private function catat(InventarisUnit $unit, array $atribut): Mutasi
    {
        return Mutasi::create($atribut + [
            'sekolah_id' => $unit->sekolah_id,
            'inventaris_unit_id' => $unit->id,
            'asal_ruang_id' => $unit->ruang_id,
            'asal_pegawai_id' => $unit->pegawai_id,
            'asal_kondisi' => $unit->kondisi,
        ]);
    }
}
