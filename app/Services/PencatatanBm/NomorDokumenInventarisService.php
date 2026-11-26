<?php

namespace App\Services\PencatatanBm;

use App\Models\Master\Sekolah;
use App\Models\PencatatanBm\Dokumen;

/**
 * Penomoran surat dinas inventaris: `{serial}/{kode klasifikasi}` per jenis
 * dokumen, serial 3 digit per sekolah per jenis. Nomor yang sudah dibatalkan
 * tidak pernah dipakai ulang — nomor surat dinas yang pernah terbit tidak boleh
 * menunjuk dokumen lain.
 *
 * Pemanggil wajib sudah berada di dalam transaksi: langkah 1 mengunci baris
 * sekolah sebagai mutex per sekolah.
 */
class NomorDokumenInventarisService
{
    /** @var array<string, string> */
    private const KOLOM_KODE = [
        'bast' => 'kode_bast',
        'fakta_integritas' => 'kode_fakta',
        'pengembalian' => 'kode_pengembalian',
    ];

    /**
     * @param  'bast'|'fakta_integritas'|'pengembalian'  $jenis
     */
    public function berikutnya(string $sekolahId, string $jenis): string
    {
        return $this->nomor($sekolahId, $jenis, kunci: true);
    }

    /**
     * Pratinjau untuk form (tanpa lock, tanpa menulis). Nilainya bisa berubah
     * bila ada pengguna lain menyimpan lebih dulu; nomor final tetap dihitung
     * ulang saat dokumen dibuat.
     *
     * @param  'bast'|'fakta_integritas'|'pengembalian'  $jenis
     */
    public function pratinjau(string $sekolahId, string $jenis): string
    {
        return $this->nomor($sekolahId, $jenis, kunci: false);
    }

    /**
     * @param  'bast'|'fakta_integritas'|'pengembalian'  $jenis
     */
    private function nomor(string $sekolahId, string $jenis, bool $kunci): string
    {
        $query = Sekolah::query()->whereKey($sekolahId);

        if ($kunci) {
            $query->lockForUpdate();
        }

        $sekolah = $query->firstOrFail();

        $serial = $this->serialBerikutnya($sekolahId, $jenis);
        $kode = $this->kodeKlasifikasi($sekolah, $jenis);

        return $kode === '' ? $serial : "{$serial}/{$kode}";
    }

    private function serialBerikutnya(string $sekolahId, string $jenis): string
    {
        $tertinggi = 0;

        $dokumen = Dokumen::query()
            ->where('sekolah_id', $sekolahId)
            ->where('jenis', $jenis)
            ->get(['nomor', 'payload']);

        foreach ($dokumen as $baris) {
            // Dokumen yang nomornya dibatalkan menyimpan serial aslinya di
            // payload; nomor yang pernah terbit tidak boleh dipakai ulang.
            $nilai = $baris->nomor !== null
                ? (string) $baris->nomor
                : (string) ($baris->payload['pembatalan']['nomor_urut'] ?? '');

            if ($nilai === '') {
                continue;
            }

            $tertinggi = max($tertinggi, (int) explode('/', $nilai)[0]);
        }

        return str_pad((string) ($tertinggi + 1), 3, '0', STR_PAD_LEFT);
    }

    private function kodeKlasifikasi(Sekolah $sekolah, string $jenis): string
    {
        $kolom = self::KOLOM_KODE[$jenis] ?? null;

        if ($kolom === null) {
            return '';
        }

        $kode = trim((string) $sekolah->{$kolom});

        // Acuan `pengembalian.php:14-26` jatuh ke kode BA/BAST bila kode
        // pengembalian kosong. Skema baru tidak punya kolom `kode_ba`, jadi
        // `kode_bast` adalah padanan terdekat yang tersedia.
        if ($kode === '' && $jenis === 'pengembalian') {
            $kode = trim((string) $sekolah->kode_bast);
        }

        return $kode;
    }
}
