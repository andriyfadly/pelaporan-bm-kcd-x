<?php

namespace App\Services\Master;

use App\Models\Master\Jabatan;
use App\Models\Master\Pegawai;
use App\Models\Master\PenugasanPegawai;
use App\Models\Master\Sekolah;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Riwayat penugasan pegawai pada jabatan sekolah.
 *
 * Aturan inti: satu jabatan hanya punya satu pemegang efektif pada satu
 * tanggal. Karena itu menambah penugasan aktif menutup penugasan sebelumnya
 * (H-1), bukan menimpanya — pegawai dan periodenya tetap tersimpan sebagai
 * histori. PLT/PLH mengikuti aturan yang sama seperti definitif.
 */
class PenugasanPegawaiService
{
    /**
     * Simpan penugasan baru atau ubah yang sudah ada. `$data` berisi identitas
     * pegawai sekaligus (nama/nip/pangkat) agar form tunggal bisa membuat
     * pegawai yang belum terdaftar.
     *
     * @param  array{nama: string, nip?: ?string, pangkat_golongan?: ?string, jabatan: string, jenis_penugasan?: ?string, mulai_menjabat: string, selesai_menjabat?: ?string}  $data
     */
    public function simpan(string $sekolahId, string $userId, array $data, ?PenugasanPegawai $penugasan = null): PenugasanPegawai
    {
        return DB::transaction(function () use ($sekolahId, $userId, $data, $penugasan): PenugasanPegawai {
            // Kunci baris sekolah: semua perhitungan periode jadi serial per
            // sekolah, sehingga dua penulisan bersamaan tidak lolos overlap.
            Sekolah::whereKey($sekolahId)->lockForUpdate()->firstOrFail();

            $jabatan = $this->jabatan($sekolahId, $data['jabatan']);
            $pegawai = $this->pegawai($sekolahId, $data);

            $mulai = Carbon::parse($data['mulai_menjabat']);
            $selesai = ! empty($data['selesai_menjabat']) ? Carbon::parse($data['selesai_menjabat']) : null;

            if ($selesai && $selesai->lt($mulai)) {
                throw new RuntimeException('Tanggal selesai menjabat tidak boleh sebelum tanggal mulai.');
            }

            if ($selesai === null) {
                $this->tutupPenugasanTerbuka($sekolahId, $jabatan->id, $mulai, $userId, $penugasan?->id);
            }

            if ($this->adaTumpangTindih($sekolahId, $jabatan->id, $mulai, $selesai ?? $mulai, $penugasan?->id)) {
                throw new RuntimeException('Periode masa jabatan tumpang tindih dengan penugasan lain pada jabatan yang sama.');
            }

            $atribut = [
                'pegawai_id' => $pegawai->id,
                'jabatan_id' => $jabatan->id,
                'jenis_penugasan' => $data['jenis_penugasan'] ?? 'definitif',
                'mulai_menjabat' => $mulai->toDateString(),
                'selesai_menjabat' => $selesai?->toDateString(),
                'updated_by' => $userId,
            ];

            if ($penugasan) {
                $penugasan->update($atribut);

                return $penugasan->refresh();
            }

            return PenugasanPegawai::create($atribut + [
                'sekolah_id' => $sekolahId,
                'created_by' => $userId,
            ]);
        });
    }

    /** Akhiri penugasan; baris tetap ada sebagai histori. */
    public function akhiri(string $sekolahId, string $userId, PenugasanPegawai $penugasan, string $tanggal): PenugasanPegawai
    {
        abort_unless($penugasan->sekolah_id === $sekolahId, 404);

        $akhir = Carbon::parse($tanggal);

        if ($akhir->lt(Carbon::parse($penugasan->mulai_menjabat))) {
            throw new RuntimeException('Tanggal selesai tidak boleh sebelum tanggal mulai menjabat.');
        }

        $penugasan->update([
            'selesai_menjabat' => $akhir->toDateString(),
            'updated_by' => $userId,
        ]);

        return $penugasan->refresh();
    }

    /**
     * Pindahkan pegawai ke jabatan lain: penugasan lama ditutup H-1 dari
     * tanggal mulai yang baru, lalu penugasan baru dibuat.
     *
     * @param  array{jabatan: string, mulai_menjabat: string, jenis_penugasan?: ?string, selesai_menjabat?: ?string}  $data
     */
    public function mutasi(string $sekolahId, string $userId, PenugasanPegawai $lama, array $data): PenugasanPegawai
    {
        abort_unless($lama->sekolah_id === $sekolahId, 404);

        return DB::transaction(function () use ($sekolahId, $userId, $lama, $data): PenugasanPegawai {
            $mulaiBaru = Carbon::parse($data['mulai_menjabat']);

            if ($lama->selesai_menjabat === null || $lama->selesai_menjabat->gte($mulaiBaru)) {
                $lama->update([
                    'selesai_menjabat' => $mulaiBaru->copy()->subDay()->toDateString(),
                    'updated_by' => $userId,
                ]);
            }

            return $this->simpan($sekolahId, $userId, $data + [
                'nama' => $lama->pegawai->nama_lengkap,
                'nip' => $lama->pegawai->nip,
                'pangkat_golongan' => $lama->pegawai->pangkat_golongan,
            ]);
        });
    }

    /**
     * Snapshot tiga jabatan penandatangan yang berlaku pada tanggal tertentu.
     * Melempar RuntimeException bila ada jabatan yang belum terisi — pesan
     * menyebut semua jabatan yang bermasalah sekaligus.
     *
     * @return array<string, array<string, mixed>>
     */
    public function snapshotPenandatangan(string $sekolahId, string $tanggal): array
    {
        $masalah = [];
        $snapshot = [];

        foreach (Jabatan::KODE_PENANDATANGAN as $kode) {
            $penugasan = $this->berlakuPada($sekolahId, $kode, $tanggal);

            if (! $penugasan) {
                $masalah[] = Jabatan::LABEL_PENANDATANGAN[$kode];
            } elseif (trim((string) $penugasan->pegawai?->nama_lengkap) === '') {
                $masalah[] = Jabatan::LABEL_PENANDATANGAN[$kode].' (nama kosong)';
            } else {
                $snapshot[$kode] = $penugasan->toSnapshot();
            }
        }

        if ($masalah !== []) {
            throw new RuntimeException('Approval tidak dapat dilakukan, pejabat penandatangan belum lengkap: '.implode(', ', $masalah).'. Lengkapi di menu Penugasan Pegawai.');
        }

        return $snapshot;
    }

    /**
     * Snapshot semua jabatan penandatangan pada tanggal tertentu. Tidak
     * melempar: jabatan yang kosong bernilai null supaya dokumen tetap bisa
     * dicetak (sama seperti perilaku cetak inventaris).
     *
     * @return array<string, array<string, mixed>|null>
     */
    public function pejabatPada(string $sekolahId, ?string $tanggal = null): array
    {
        $tanggal ??= now()->toDateString();
        $hasil = [];

        foreach (Jabatan::KODE_PENANDATANGAN as $kode) {
            $hasil[$kode] = $this->berlakuPada($sekolahId, $kode, $tanggal)?->toSnapshot();
        }

        return $hasil;
    }

    /** Penugasan yang menjabat efektif pada tanggal; null bila tidak ada. */
    public function berlakuPada(string $sekolahId, string $kodeJabatan, string $tanggal): ?PenugasanPegawai
    {
        $jabatan = Jabatan::where('sekolah_id', $sekolahId)->where('kode', $kodeJabatan)->first();

        if (! $jabatan) {
            return null;
        }

        return PenugasanPegawai::berlakuPada($sekolahId, $jabatan->id, $tanggal)
            ->with(['pegawai', 'jabatan'])
            ->first();
    }

    /**
     * Penugasan seorang pegawai yang berlaku pada tanggal dokumen. Dipakai
     * snapshot fakta integritas: jabatan yang tercetak adalah jabatan saat itu.
     */
    public function penugasanPegawaiPada(string $sekolahId, string $pegawaiId, string $tanggal): ?PenugasanPegawai
    {
        return PenugasanPegawai::where('sekolah_id', $sekolahId)
            ->where('pegawai_id', $pegawaiId)
            ->whereDate('mulai_menjabat', '<=', $tanggal)
            ->where(fn ($q) => $q
                ->whereNull('selesai_menjabat')
                ->orWhereDate('selesai_menjabat', '>=', $tanggal))
            ->orderByRaw("CASE jenis_penugasan WHEN 'definitif' THEN 0 WHEN 'plt' THEN 1 ELSE 2 END")
            ->orderByDesc('mulai_menjabat')
            ->with('jabatan')
            ->first();
    }

    private function jabatan(string $sekolahId, string $kode): Jabatan
    {
        return Jabatan::firstOrCreate(
            ['sekolah_id' => $sekolahId, 'kode' => $kode],
            ['nama' => Jabatan::LABEL_PENANDATANGAN[$kode] ?? ucwords(str_replace('_', ' ', $kode))],
        );
    }

    /**
     * @param  array{nama: string, nip?: ?string, pangkat_golongan?: ?string}  $data
     */
    private function pegawai(string $sekolahId, array $data): Pegawai
    {
        $nip = trim((string) ($data['nip'] ?? ''));

        if ($nip !== '') {
            $ada = Pegawai::withTrashed()
                ->where('sekolah_id', $sekolahId)
                ->where('nip', $nip)
                ->first();

            if ($ada) {
                if ($ada->trashed()) {
                    $ada->restore();
                }

                $ada->update([
                    'nama_lengkap' => $data['nama'],
                    'pangkat_golongan' => $data['pangkat_golongan'] ?? $ada->pangkat_golongan,
                ]);

                return $ada;
            }
        }

        // Tanpa NIP, orang yang sama dikenali dari nama (case-insensitive).
        $ada = Pegawai::withTrashed()
            ->where('sekolah_id', $sekolahId)
            ->whereRaw('lower(nama_lengkap) = ?', [mb_strtolower(trim($data['nama']))])
            ->first();

        if ($ada) {
            if ($ada->trashed()) {
                $ada->restore();
            }

            $ada->update([
                'nip' => $nip !== '' ? $nip : $ada->nip,
                'pangkat_golongan' => $data['pangkat_golongan'] ?? $ada->pangkat_golongan,
            ]);

            return $ada;
        }

        return Pegawai::create([
            'sekolah_id' => $sekolahId,
            'nama_lengkap' => $data['nama'],
            'nip' => $nip !== '' ? $nip : null,
            'pangkat_golongan' => $data['pangkat_golongan'] ?? null,
        ]);
    }

    /** Tutup penugasan terbuka lain pada jabatan yang sama, sehari sebelum mulai. */
    private function tutupPenugasanTerbuka(string $sekolahId, string $jabatanId, Carbon $mulai, string $userId, ?string $kecualiId): void
    {
        PenugasanPegawai::where('sekolah_id', $sekolahId)
            ->where('jabatan_id', $jabatanId)
            ->when($kecualiId, fn ($q) => $q->whereKeyNot($kecualiId))
            ->whereNull('selesai_menjabat')
            ->lockForUpdate()
            ->get()
            ->each(function (PenugasanPegawai $lama) use ($mulai, $userId): void {
                $lama->update([
                    'selesai_menjabat' => $mulai->copy()->subDay()->toDateString(),
                    'updated_by' => $userId,
                ]);
            });
    }

    private function adaTumpangTindih(string $sekolahId, string $jabatanId, Carbon $mulai, Carbon $selesai, ?string $kecualiId): bool
    {
        return PenugasanPegawai::where('sekolah_id', $sekolahId)
            ->where('jabatan_id', $jabatanId)
            ->when($kecualiId, fn ($q) => $q->whereKeyNot($kecualiId))
            ->whereDate('mulai_menjabat', '<=', $selesai)
            ->where(fn ($q) => $q
                ->whereNull('selesai_menjabat')
                ->orWhereDate('selesai_menjabat', '>=', $mulai))
            ->lockForUpdate()
            ->exists();
    }
}
