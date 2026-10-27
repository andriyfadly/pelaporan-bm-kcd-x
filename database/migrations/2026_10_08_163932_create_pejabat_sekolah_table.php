<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master pejabat sekolah dengan riwayat masa jabatan (generic, lintas
     * domain). Satu sekolah + jabatan hanya boleh punya satu pejabat aktif
     * (partial unique index). Snapshot pejabat tersimpan di payload dokumen,
     * sehingga riwayat tidak boleh dihapus permanen.
     */
    public function up(): void
    {
        Schema::create('pejabat_sekolah', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sekolah_id')->index();
            $table->enum('jabatan', ['kepala_sekolah', 'wakasek_sarpras', 'pembantu_pengurus_barang'])->index();
            $table->enum('jenis_jabatan', ['definitif', 'plt', 'plh'])->default('definitif');
            $table->string('nama', 255);
            $table->string('pangkat_golongan', 50)->nullable();
            $table->string('nip', 30)->nullable();
            $table->date('mulai_menjabat');
            $table->date('selesai_menjabat')->nullable();
            $table->foreignUuid('created_by')->nullable();
            $table->foreignUuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['sekolah_id', 'jabatan', 'mulai_menjabat']);
        });

        DB::statement('CREATE UNIQUE INDEX pejabat_sekolah_satu_aktif ON pejabat_sekolah (sekolah_id, jabatan) WHERE selesai_menjabat IS NULL AND deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS pejabat_sekolah_satu_aktif');
        Schema::dropIfExists('pejabat_sekolah');
    }
};
