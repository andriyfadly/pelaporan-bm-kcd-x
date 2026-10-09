<?php

namespace Database\Seeders;

use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\HargaBarang;
use App\Models\DistribusiBhp\KodeRekening;
use App\Models\DistribusiBhp\Penerimaan;
use App\Models\DistribusiBhp\PenerimaanDetail;
use App\Models\DistribusiBhp\Penyedia;
use App\Models\Master\Sekolah;
use App\Support\DistribusiBhp\SumberDana;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Impor data Distribusi BHP dari database SQLite sumber (aplikasi Next/Prisma).
 *
 * Sumber default: ../sistem-manajemen-aset/prisma/dev.db.backup.db. Dapat
 * diarahkan ke file lain via properti $sourcePath atau setConnection() untuk
 * pengujian.
 *
 * Yang diimpor: master (kode rekening, barang, harga, penyedia) + penerimaan
 * beserta detailnya. Baris soft-deleted (deleted_at) dan detail keranjang
 * (cart_status != 'saved') dilewati.
 *
 * Idempotent: memakai updateOrCreate berbasis natural key sehingga aman
 * dijalankan berulang.
 */
class DistribusiBhpImportSeeder extends Seeder
{
    /** Koneksi yang dipakai untuk membaca sumber. */
    protected string $connection = 'bhp_import';

    /** Path absolut DB sumber; null = pakai default. */
    protected ?string $sourcePath = null;

    /**
     * Sekolah tujuan untuk penerimaan. Sumber lama single-school sehingga tidak
     * punya kolom sekolah; null = ambil sekolah pertama yang ada. Karena tidak
     * ada pool pusat, penerimaan WAJIB punya sekolah — bila tidak ada sekolah
     * sama sekali, impor penerimaan dilewati dengan peringatan.
     */
    protected ?string $sekolahId = null;

    private int $kodeRekeningCount = 0;

    private int $barangCount = 0;

    private int $hargaCount = 0;

    private int $penyediaCount = 0;

    private int $penerimaanCount = 0;

    private int $detailCount = 0;

    public function setConnection(string $connection): static
    {
        $this->connection = $connection;

        return $this;
    }

    public function setSourcePath(?string $path): static
    {
        $this->sourcePath = $path;

        return $this;
    }

    public function setSekolahId(?string $sekolahId): static
    {
        $this->sekolahId = $sekolahId;

        return $this;
    }

    public function run(): void
    {
        $this->registerConnection();

        $db = DB::connection($this->connection);

        if (! $this->tableExists($db, 'kode_rekening')) {
            $this->command?->warn('Tabel sumber Distribusi BHP tidak ditemukan; impor dilewati.');

            return;
        }

        DB::transaction(function () use ($db) {
            $this->importKodeRekening($db);
            $this->importPenyedia($db);
            $this->importBarang($db);
            $this->importPenerimaan($db);
        });

        $this->command?->info(sprintf(
            'Impor BHP selesai: %d kode rekening, %d penyedia, %d barang, %d harga, %d penerimaan, %d detail.',
            $this->kodeRekeningCount,
            $this->penyediaCount,
            $this->barangCount,
            $this->hargaCount,
            $this->penerimaanCount,
            $this->detailCount,
        ));
    }

    private function registerConnection(): void
    {
        if (config("database.connections.{$this->connection}")) {
            return;
        }

        config()->set("database.connections.{$this->connection}", [
            'driver' => 'sqlite',
            'database' => $this->sourcePath ?? base_path('../sistem-manajemen-aset/prisma/dev.db.backup.db'),
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
    }

    private function tableExists($db, string $table): bool
    {
        try {
            return Schema::connection($this->connection)->hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }

    private function hasColumn(string $table, string $column): bool
    {
        try {
            return Schema::connection($this->connection)->hasColumn($table, $column);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Query tabel sumber, melewati baris soft-deleted hanya bila kolom
     * deleted_at tersedia (tidak semua tabel Prisma memilikinya).
     */
    private function sourceTable($db, string $table)
    {
        $query = $db->table($table);

        if ($this->hasColumn($table, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query;
    }

    private function importKodeRekening($db): void
    {
        $this->sourceTable($db, 'kode_rekening')
            ->orderBy('kode')
            ->chunk(100, function ($rows) {
                foreach ($rows as $row) {
                    SumberDana::dengan($row->sumber_dana ?: SumberDana::BOS, function () use ($row) {
                        KodeRekening::updateOrCreate(
                            ['kode' => $row->kode],
                            [
                                'nama' => $row->nama,
                                'kode_barang' => $row->kode_barang,
                                'uraian_kode_barang' => $row->uraian_kode_barang,
                            ],
                        );
                    });
                    $this->kodeRekeningCount++;
                }
            });
    }

    private function importPenyedia($db): void
    {
        $this->sourceTable($db, 'penyedia')
            ->orderBy('nama')
            ->chunk(100, function ($rows) {
                foreach ($rows as $row) {
                    Penyedia::updateOrCreate(
                        ['nama' => $row->nama],
                        [],
                    );
                    $this->penyediaCount++;
                }
            });
    }

    private function importBarang($db): void
    {
        // Kode rekening kini per sumber dana; bangun peta per dana agar barang
        // tidak pernah menunjuk kode rekening sumber dana lain.
        $rekeningMap = [];
        foreach (SumberDana::SEMUA as $dana) {
            $rekeningMap[$dana] = SumberDana::dengan($dana, fn () => KodeRekening::pluck('id', 'kode')->all());
        }

        $this->sourceTable($db, 'barang')
            ->orderBy('id')
            ->chunk(100, function ($rows) use ($db, $rekeningMap) {
                foreach ($rows as $row) {
                    $dana = SumberDana::normalisasi($row->sumber_dana ?? SumberDana::BOS);

                    SumberDana::dengan($dana, function () use ($db, $row, $rekeningMap, $dana) {
                        $kodeRekeningId = null;
                        if ($row->kode_rekening_id) {
                            $kode = $db->table('kode_rekening')->where('id', $row->kode_rekening_id)->value('kode');
                            $kodeRekeningId = $kode ? ($rekeningMap[$dana][$kode] ?? null) : null;
                        }

                        $barang = Barang::updateOrCreate(
                            ['nama_barang' => $row->nama_barang],
                            [
                                'kode_barang' => $row->kode_barang,
                                'spesifikasi_barang_nusp' => $row->spesifikasi_barang_nusp,
                                'satuan' => $row->satuan,
                                'kode_rekening_id' => $kodeRekeningId,
                            ],
                        );
                        $this->barangCount++;

                        $this->importHarga($db, $row->id, $barang);
                    });
                }
            });
    }

    private function importHarga($db, string $sourceBarangId, Barang $barang): void
    {
        $harga = $this->sourceTable($db, 'harga_barang')
            ->where('barang_id', $sourceBarangId)
            ->orderByDesc('created_at')
            ->first();

        if (! $harga) {
            return;
        }

        HargaBarang::updateOrCreate(
            ['barang_id' => $barang->id, 'harga_satuan' => (int) $harga->harga_satuan],
            ['created_by' => null],
        );
        $this->hargaCount++;
    }

    private function importPenerimaan($db): void
    {
        // Tidak ada pool pusat: setiap penerimaan harus terikat sekolah.
        $sekolahId = $this->sekolahId ?? Sekolah::query()->orderBy('nama_sekolah')->value('id');

        if (! $sekolahId) {
            $this->command?->warn('Tidak ada sekolah tujuan; impor penerimaan BHP dilewati (stok tanpa pemilik tidak valid).');

            return;
        }

        // Peta barang per sumber dana (tabel master barang sudah terpisah).
        $petaNama = [];
        $petaKode = [];
        foreach (SumberDana::SEMUA as $dana) {
            SumberDana::dengan($dana, function () use (&$petaNama, &$petaKode, $dana) {
                $petaNama[$dana] = Barang::get()->mapWithKeys(fn (Barang $b) => [$b->nama_barang => $b->id])->all();
                $petaKode[$dana] = Barang::whereNotNull('kode_barang')->get()->mapWithKeys(fn (Barang $b) => [$b->kode_barang => $b->id])->all();
            });
        }
        $penyediaMap = Penyedia::pluck('id', 'nama')->all();

        $this->sourceTable($db, 'buku_penerimaan_barang')
            ->orderBy('id')
            ->chunk(100, function ($rows) use ($db, $petaNama, $petaKode, $penyediaMap, $sekolahId) {
                foreach ($rows as $row) {
                    $dana = SumberDana::normalisasi($row->sumber_dana ?? SumberDana::BOS);

                    SumberDana::dengan($dana, function () use ($db, $row, $dana, $petaNama, $petaKode, $penyediaMap, $sekolahId) {
                        $penyediaId = null;
                        if ($row->penyedia_id) {
                            $namaPenyedia = $db->table('penyedia')->where('id', $row->penyedia_id)->value('nama');
                            $penyediaId = $namaPenyedia ? ($penyediaMap[$namaPenyedia] ?? null) : null;
                        }

                        $penerimaan = Penerimaan::updateOrCreate(
                            ['source_id' => $row->id],
                            [
                                'sekolah_id' => $sekolahId,
                                'no_bast' => $row->no_bast,
                                'no_nota_invoice' => $row->no_nota_invoice,
                                'tanggal_bast' => $this->toDate($row->tanggal_bast),
                                'no_kwitansi' => $row->no_kwitansi,
                                'penyedia_id' => $penyediaId,
                                'tanggal_titimangsa' => $this->toDate($row->tanggal_titimangsa),
                                'is_saldo_awal' => (bool) $row->is_saldo_awal,
                                'periode_tahun' => $row->periode_tahun !== null ? (int) $row->periode_tahun : null,
                            ],
                        );
                        $this->penerimaanCount++;

                        $this->importDetail($db, $row->id, $penerimaan, $petaNama[$dana], $petaKode[$dana]);
                    });
                }
            });
    }

    /**
     * @param  array<string,string>  $barangMap  "nama|dana" => id
     * @param  array<string,string>  $barangByKode  "kode|dana" => id
     */
    private function importDetail($db, string $sourcePenerimaanId, Penerimaan $penerimaan, array $barangMap, array $barangByKode): void
    {
        $details = $db->table('buku_penerimaan_barang_detail')
            ->where('buku_penerimaan_barang_id', $sourcePenerimaanId)
            ->where('cart_status', 'saved')
            ->whereNotNull('barang_id')
            ->get();

        foreach ($details as $row) {
            // Resolusi barang sumber -> barang domain, dibatasi sumber dana agar
            // BOS dan BOPD tidak tertukar, lalu fallback ke kode barang.
            $sumberBarang = $db->table('barang')->where('id', $row->barang_id)->first();
            if (! $sumberBarang) {
                continue;
            }

            // Peta sudah spesifik satu sumber dana (tabel master terpisah).
            $barangId = $barangMap[$sumberBarang->nama_barang] ?? null;

            if (! $barangId && $sumberBarang->kode_barang) {
                $barangId = $barangByKode[$sumberBarang->kode_barang] ?? null;
            }

            if (! $barangId) {
                continue;
            }

            $jumlah = (int) $row->spesifikasi_barang_jumlah;
            if ($jumlah <= 0) {
                continue;
            }

            PenerimaanDetail::updateOrCreate(
                [
                    'bhp_penerimaan_id' => $penerimaan->id,
                    'barang_id' => $barangId,
                    'jumlah' => $jumlah,
                ],
                [
                    'harga_satuan' => $row->harga_satuan !== null ? (int) $row->harga_satuan : null,
                    'nilai_total' => $row->nilai_total !== null ? (int) $row->nilai_total : null,
                    'merek_type' => $row->merek_type,
                    'keterangan' => $row->keterangan,
                ],
            );
            $this->detailCount++;
        }
    }

    private function toDate(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        return substr(str_replace(['T', ' '], ' ', $value), 0, 10);
    }
}
