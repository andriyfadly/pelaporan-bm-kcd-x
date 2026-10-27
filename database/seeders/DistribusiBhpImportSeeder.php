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
 * Tabel domain terpisah per sumber dana (bhp_bos_/bhp_bopd_), jadi setiap
 * baris sumber diarahkan ke tabel sesuai kolom sumber_dana-nya via
 * {@see SumberDana::dengan()}. Penyedia tetap satu tabel bersama.
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

    private int $kodeRekeningCount = 0;

    private int $barangCount = 0;

    private int $hargaCount = 0;

    private int $penyediaCount = 0;

    private int $penerimaanCount = 0;

    private int $detailCount = 0;

    /**
     * Sekolah tujuan penerimaan hasil impor. Sumber legacy tidak menyimpan
     * sekolah, jadi impor mengasumsikan satu sekolah (data sekolah tunggal).
     */
    private ?string $sekolahId = null;

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

    public function run(): void
    {
        $this->registerConnection();

        $db = DB::connection($this->connection);

        if (! $this->tableExists($db, 'kode_rekening')) {
            $this->command?->warn('Tabel sumber Distribusi BHP tidak ditemukan; impor dilewati.');

            return;
        }

        DB::transaction(function () use ($db) {
            $this->sekolahId = Sekolah::query()->value('id');
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

    private function normalisasi(?string $sumberDana): string
    {
        return SumberDana::normalisasi($sumberDana ?: SumberDana::BOS);
    }

    private function importKodeRekening($db): void
    {
        $this->sourceTable($db, 'kode_rekening')
            ->orderBy('kode')
            ->chunk(100, function ($rows) {
                foreach ($rows as $row) {
                    SumberDana::dengan($this->normalisasi($row->sumber_dana ?? null), function () use ($row) {
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
        // Peta kode rekening per sumber dana: "dana|kode" => id.
        $rekeningMap = [];
        foreach (SumberDana::SEMUA as $dana) {
            SumberDana::dengan($dana, function () use (&$rekeningMap, $dana) {
                foreach (KodeRekening::pluck('id', 'kode')->all() as $kode => $id) {
                    $rekeningMap[$dana.'|'.$kode] = $id;
                }
            });
        }

        $this->sourceTable($db, 'barang')
            ->orderBy('id')
            ->chunk(100, function ($rows) use ($db, $rekeningMap) {
                foreach ($rows as $row) {
                    $dana = $this->normalisasi($row->sumber_dana ?? null);

                    $kodeRekeningId = null;
                    if ($row->kode_rekening_id) {
                        $kode = $db->table('kode_rekening')->where('id', $row->kode_rekening_id)->value('kode');
                        $kodeRekeningId = $kode ? ($rekeningMap[$dana.'|'.$kode] ?? null) : null;
                    }

                    SumberDana::dengan($dana, function () use ($row, $kodeRekeningId) {
                        $barang = Barang::updateOrCreate(
                            ['nama_barang' => $row->nama_barang],
                            [
                                'kode_barang' => $row->kode_barang,
                                'spesifikasi_barang_nusp' => $row->spesifikasi_barang_nusp,
                                'satuan' => $row->satuan,
                                'kode_rekening_id' => $kodeRekeningId,
                            ],
                        );
                        $this->importHarga($barang->id, $row->id);
                    });
                    $this->barangCount++;
                }
            });
    }

    private function importHarga(string $barangId, string $sourceBarangId): void
    {
        $harga = $this->sourceTable(DB::connection($this->connection), 'harga_barang')
            ->where('barang_id', $sourceBarangId)
            ->orderByDesc('created_at')
            ->first();

        if (! $harga) {
            return;
        }

        HargaBarang::updateOrCreate(
            ['barang_id' => $barangId, 'harga_satuan' => (int) $harga->harga_satuan],
            ['created_by' => null],
        );
        $this->hargaCount++;
    }

    private function importPenerimaan($db): void
    {
        // Peta barang per sumber dana: "dana|nama" & "dana|kode" => id.
        $barangByNama = [];
        $barangByKode = [];
        foreach (SumberDana::SEMUA as $dana) {
            SumberDana::dengan($dana, function () use (&$barangByNama, &$barangByKode, $dana) {
                foreach (Barang::get() as $b) {
                    $barangByNama[$dana.'|'.$b->nama_barang] = $b->id;
                    if ($b->kode_barang) {
                        $barangByKode[$dana.'|'.$b->kode_barang] = $b->id;
                    }
                }
            });
        }

        $penyediaMap = Penyedia::pluck('id', 'nama')->all();

        $this->sourceTable($db, 'buku_penerimaan_barang')
            ->orderBy('id')
            ->chunk(100, function ($rows) use ($db, $barangByNama, $barangByKode, $penyediaMap) {
                foreach ($rows as $row) {
                    $dana = $this->normalisasi($row->sumber_dana ?? null);

                    $penyediaId = null;
                    if ($row->penyedia_id) {
                        $namaPenyedia = $db->table('penyedia')->where('id', $row->penyedia_id)->value('nama');
                        $penyediaId = $namaPenyedia ? ($penyediaMap[$namaPenyedia] ?? null) : null;
                    }

                    $penerimaan = null;
                    SumberDana::dengan($dana, function () use ($db, $row, $dana, $penyediaId, $barangByNama, $barangByKode, &$penerimaan) {
                        $penerimaan = Penerimaan::updateOrCreate(
                            ['source_id' => $row->id],
                            [
                                'sekolah_id' => $this->sekolahId ?? null,
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
                        $this->importDetail($db, $row->id, $penerimaan, $dana, $barangByNama, $barangByKode);
                    });
                    $this->penerimaanCount++;
                }
            });
    }

    /**
     * @param  array<string,string>  $barangByNama  "dana|nama" => id
     * @param  array<string,string>  $barangByKode  "dana|kode" => id
     */
    private function importDetail($db, string $sourcePenerimaanId, Penerimaan $penerimaan, string $dana, array $barangByNama, array $barangByKode): void
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

            $barangId = $barangByNama[$dana.'|'.$sumberBarang->nama_barang] ?? null;

            if (! $barangId && $sumberBarang->kode_barang) {
                $barangId = $barangByKode[$dana.'|'.$sumberBarang->kode_barang] ?? null;
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
