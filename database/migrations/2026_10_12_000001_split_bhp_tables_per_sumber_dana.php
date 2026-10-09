<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pisah tabel Distribusi BHP per sumber dana (BOS / BOPD).
 *
 * Tabel transaksi & master barang menjadi `bhp_bos_*` dan `bhp_bopd_*`; sumber
 * dana implisit dari nama tabel sehingga kolom `sumber_dana` DIHAPUS. Hanya
 * `bhp_penyedia` dan `bhp_divisi` yang tetap satu tabel bersama lintas sumber
 * dana (dikelola admin, tidak terikat dana).
 *
 * Data lama tidak dipindahkan (sesuai keputusan: mulai bersih) — tabel lama
 * yang dipecah di-drop.
 */
return new class extends Migration
{
    /** Suffix tabel yang dipecah per sumber dana. */
    private array $suffix = [
        'kode_rekening',
        'barang',
        'harga_barang',
        'penerimaan',
        'penerimaan_detail',
        'pengajuan',
        'pengajuan_detail',
        'nomor_sequence',
        'dokumen',
        'batch_dokumen',
        'settings',
    ];

    /** Tabel lama yang dipecah (di-drop setelah tabel baru dibuat). */
    private array $tabelLama = [
        'bhp_kode_rekening',
        'bhp_barang',
        'bhp_harga_barang',
        'bhp_penerimaan_detail',
        'bhp_penerimaan',
        'bhp_pengajuan_detail',
        'bhp_pengajuan',
        'bhp_nomor_sequence',
        'bhp_dokumen',
        'bhp_batch_dokumen',
        'bhp_settings',
    ];

    public function up(): void
    {
        foreach (['bos', 'bopd'] as $dana) {
            $this->buatKodeRekening($dana);
            $this->buatBarang($dana);
            $this->buatHargaBarang($dana);
            $this->buatPenerimaan($dana);
            $this->buatPenerimaanDetail($dana);
            $this->buatPengajuan($dana);
            $this->buatPengajuanDetail($dana);
            $this->buatNomorSequence($dana);
            $this->buatDokumen($dana);
            $this->buatBatchDokumen($dana);
        }

        $this->buatSettings();

        // Bersihkan tabel lama yang kini dipecah (CASCADE untuk tangani FK lintas tabel).
        foreach ($this->tabelLama as $tabel) {
            if (Schema::hasTable($tabel)) {
                Schema::dropIfExists($tabel);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bhp_bos_settings');
        Schema::dropIfExists('bhp_bopd_settings');

        foreach (['bos', 'bopd'] as $dana) {
            foreach ([
                'batch_dokumen',
                'dokumen',
                'nomor_sequence',
                'pengajuan_detail',
                'pengajuan',
                'penerimaan_detail',
                'penerimaan',
                'harga_barang',
                'barang',
                'kode_rekening',
            ] as $suffix) {
                Schema::dropIfExists("bhp_{$dana}_{$suffix}");
            }
        }
    }

    private function buatKodeRekening(string $dana): void
    {
        Schema::create("bhp_{$dana}_kode_rekening", function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode', 100);
            $table->string('nama', 255);
            $table->string('kode_barang', 100)->nullable();
            $table->string('uraian_kode_barang', 255)->nullable();
            $table->timestamps();

            $table->unique('kode');
        });
    }

    private function buatBarang(string $dana): void
    {
        Schema::create("bhp_{$dana}_barang", function (Blueprint $table) use ($dana) {
            $table->uuid('id')->primary();
            $table->string('kode_barang', 100)->nullable();
            $table->string('nama_barang', 255);
            $table->string('spesifikasi_barang_nusp', 255)->nullable();
            $table->string('satuan', 50)->nullable();
            $table->foreignUuid('kode_rekening_id')->nullable()->index();
            $table->timestamps();

            $table->foreign('kode_rekening_id')
                ->references('id')->on("bhp_{$dana}_kode_rekening")
                ->nullOnDelete();
        });
    }

    private function buatHargaBarang(string $dana): void
    {
        Schema::create("bhp_{$dana}_harga_barang", function (Blueprint $table) use ($dana) {
            $table->uuid('id')->primary();
            $table->foreignUuid('barang_id')->index();
            $table->unsignedBigInteger('harga_satuan');
            $table->foreignUuid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('barang_id')
                ->references('id')->on("bhp_{$dana}_barang")
                ->cascadeOnDelete();
        });
    }

    private function buatPenerimaan(string $dana): void
    {
        Schema::create("bhp_{$dana}_penerimaan", function (Blueprint $table) use ($dana) {
            $table->uuid('id')->primary();
            $table->string('source_id', 100)->nullable();
            $table->foreignUuid('sekolah_id')->index();
            $table->string('no_nota_invoice', 150)->nullable();
            $table->string('no_bast', 150)->nullable();
            $table->date('tanggal_bast')->nullable();
            $table->string('no_kwitansi', 150)->nullable();
            $table->foreignUuid('penyedia_id')->nullable()->index();
            $table->date('tanggal_titimangsa')->nullable();
            $table->boolean('is_saldo_awal')->default(false);
            $table->integer('periode_tahun')->nullable();
            $table->timestamps();

            $table->unique(['source_id']);
            // Partial unique saldo awal (tanpa sumber_dana — sudah implisit dari
            // nama tabel). Dijaga aplikasi + index ini sebagai jaring race.
            $table->index(['sekolah_id', 'periode_tahun'], "bhp_{$dana}_penerimaan_saldo_awal_idx");
            $table->foreign('sekolah_id')
                ->references('id')->on('master_data_sekolah')
                ->restrictOnDelete();
            $table->foreign('penyedia_id')
                ->references('id')->on('bhp_penyedia')
                ->nullOnDelete();
        });

        // Satu header saldo awal per (sekolah, periode) untuk tabel ini.
        DB::statement(
            "CREATE UNIQUE INDEX bhp_{$dana}_penerimaan_saldo_awal_unique
             ON bhp_{$dana}_penerimaan (sekolah_id, periode_tahun)
             WHERE is_saldo_awal = true"
        );
    }

    private function buatPenerimaanDetail(string $dana): void
    {
        Schema::create("bhp_{$dana}_penerimaan_detail", function (Blueprint $table) use ($dana) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bhp_penerimaan_id')->index();
            $table->foreignUuid('barang_id')->nullable()->index();
            $table->foreignUuid('harga_barang_id')->nullable();
            $table->integer('jumlah')->default(0);
            $table->unsignedBigInteger('harga_satuan')->nullable();
            $table->unsignedBigInteger('nilai_total')->nullable();
            $table->string('merek_type', 255)->nullable();
            $table->string('keterangan', 255)->nullable();
            $table->timestamps();

            $table->foreign('bhp_penerimaan_id')
                ->references('id')->on("bhp_{$dana}_penerimaan")
                ->cascadeOnDelete();
            $table->foreign('barang_id')
                ->references('id')->on("bhp_{$dana}_barang")
                ->restrictOnDelete();
        });
    }

    private function buatPengajuan(string $dana): void
    {
        Schema::create("bhp_{$dana}_pengajuan", function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sekolah_id')->index();
            $table->foreignUuid('divisi_id')->nullable()->index();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->string('nomor_pengajuan', 150)->nullable();
            $table->date('tanggal')->nullable();
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected'])->default('draft')->index();
            $table->text('review_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignUuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('sekolah_id')
                ->references('id')->on('master_data_sekolah')
                ->restrictOnDelete();
            $table->foreign('divisi_id')
                ->references('id')->on('bhp_divisi')
                ->restrictOnDelete();
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->nullOnDelete();
        });
    }

    private function buatPengajuanDetail(string $dana): void
    {
        Schema::create("bhp_{$dana}_pengajuan_detail", function (Blueprint $table) use ($dana) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bhp_pengajuan_id')->index();
            $table->foreignUuid('barang_id')->nullable()->index();
            $table->integer('jumlah_diminta');
            $table->integer('jumlah_disetujui')->nullable();
            $table->unsignedBigInteger('harga_satuan')->nullable();
            $table->string('keperluan', 255)->nullable();
            $table->string('keterangan', 255)->nullable();
            $table->string('status_reservasi', 30)->default('tidak_direservasi');
            $table->integer('jumlah_direservasi')->default(0);
            $table->timestamps();

            $table->foreign('bhp_pengajuan_id')
                ->references('id')->on("bhp_{$dana}_pengajuan")
                ->cascadeOnDelete();
            $table->foreign('barang_id')
                ->references('id')->on("bhp_{$dana}_barang")
                ->restrictOnDelete();
        });
    }

    private function buatNomorSequence(string $dana): void
    {
        Schema::create("bhp_{$dana}_nomor_sequence", function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('jenis', 50);
            $table->integer('tahun');
            $table->integer('bulan');
            $table->unsignedInteger('counter')->default(0);
            $table->timestamps();

            $table->unique(['jenis', 'tahun', 'bulan']);
        });
    }

    private function buatDokumen(string $dana): void
    {
        Schema::create("bhp_{$dana}_dokumen", function (Blueprint $table) use ($dana) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bhp_pengajuan_id')->index();
            $table->string('jenis', 50);
            $table->string('nomor', 150);
            $table->date('tanggal_titimangsa')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['bhp_pengajuan_id', 'jenis']);
            $table->foreign('bhp_pengajuan_id')
                ->references('id')->on("bhp_{$dana}_pengajuan")
                ->cascadeOnDelete();
        });
    }

    private function buatBatchDokumen(string $dana): void
    {
        Schema::create("bhp_{$dana}_batch_dokumen", function (Blueprint $table) use ($dana) {
            $table->uuid('id')->primary();
            $table->string('jenis', 50);
            $table->foreignUuid('bhp_pengajuan_id')->nullable()->index();
            $table->string('status', 30)->default('pending');
            $table->integer('total')->default(0);
            $table->integer('berhasil')->default(0);
            $table->integer('gagal')->default(0);
            $table->json('files')->nullable();
            $table->text('error_message')->nullable();
            $table->string('path')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->timestamps();

            $table->foreign('bhp_pengajuan_id')
                ->references('id')->on("bhp_{$dana}_pengajuan")
                ->nullOnDelete();
        });
    }

    private function buatSettings(): void
    {
        foreach (['bos', 'bopd'] as $dana) {
            $tabel = "bhp_{$dana}_settings";
            if (Schema::hasTable($tabel)) {
                continue;
            }

            Schema::create($tabel, function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->boolean('is_singleton')->default(true);
                $table->string('format_nomor', 200)
                    ->default('{urut:3}/BHP/{kode}/{bulan_romawi}/{tahun}');
                $table->string('paper_size', 10)->default('a4');
                $table->string('orientation', 20)->default('portrait');
                $table->timestamps();

                $table->unique('is_singleton');
            });
        }
    }
};
