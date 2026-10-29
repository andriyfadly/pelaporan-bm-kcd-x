<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Nomor register diulang dari 001 setiap tahun perolehan, seperti aplikasi
     * acuan (urutkanUlangRegister me-reset counter saat tahun berganti).
     *
     * Konsekuensinya register tidak lagi unik per (sekolah, kode barang) saja,
     * jadi unique index unit ikut menyertakan tahun perolehan.
     */
    public function up(): void
    {
        // Nilai lanjutan diturunkan dari unit yang sudah ada supaya nomor yang
        // sudah terbit tidak dipakai ulang setelah migrasi.
        $lanjutan = [];

        foreach (DB::table('inventaris_unit')
            ->select('sekolah_id', 'kode_barang', 'tahun_perolehan', 'nomor_register')
            ->get() as $unit) {
            $tahun = (int) ($unit->tahun_perolehan ?? 0);
            $akhir = str_contains((string) $unit->nomor_register, '-')
                ? (int) explode('-', (string) $unit->nomor_register)[1]
                : (int) $unit->nomor_register;

            $kunci = $unit->sekolah_id.'|'.$unit->kode_barang.'|'.$tahun;
            $lanjutan[$kunci] = max($lanjutan[$kunci] ?? 1, $akhir + 1);
        }

        // Sequence lama tanpa tahun: pertahankan angkanya pada tahunnya sendiri
        // bila unitnya ada, kalau tidak simpan sebagai tahun 0.
        foreach (DB::table('inventaris_register_sequences')->get() as $sequence) {
            $tahun = DB::table('inventaris_unit')
                ->where('sekolah_id', $sequence->sekolah_id)
                ->where('kode_barang', $sequence->kode_barang)
                ->max('tahun_perolehan');

            $kunci = $sequence->sekolah_id.'|'.$sequence->kode_barang.'|'.(int) ($tahun ?? 0);
            $lanjutan[$kunci] = max($lanjutan[$kunci] ?? 1, (int) $sequence->next_number);
        }

        Schema::table('inventaris_register_sequences', function (Blueprint $table) {
            $table->dropUnique(['sekolah_id', 'kode_barang']);
        });

        Schema::table('inventaris_register_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('tahun')->default(0);
        });

        DB::table('inventaris_register_sequences')->delete();

        foreach ($lanjutan as $kunci => $next) {
            [$sekolahId, $kode, $tahun] = explode('|', $kunci);

            DB::table('inventaris_register_sequences')->insert([
                'id' => (string) Str::uuid(),
                'sekolah_id' => $sekolahId,
                'kode_barang' => $kode,
                'tahun' => (int) $tahun,
                'next_number' => $next,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('inventaris_register_sequences', function (Blueprint $table) {
            $table->unique(['sekolah_id', 'kode_barang', 'tahun']);
        });

        Schema::table('inventaris_unit', function (Blueprint $table) {
            $table->dropUnique(['sekolah_id', 'kode_barang', 'nomor_register']);
        });

        Schema::table('inventaris_unit', function (Blueprint $table) {
            $table->unique(['sekolah_id', 'kode_barang', 'tahun_perolehan', 'nomor_register']);
        });
    }

    public function down(): void
    {
        Schema::table('inventaris_unit', function (Blueprint $table) {
            $table->dropUnique(['sekolah_id', 'kode_barang', 'tahun_perolehan', 'nomor_register']);
        });

        Schema::table('inventaris_unit', function (Blueprint $table) {
            $table->unique(['sekolah_id', 'kode_barang', 'nomor_register']);
        });

        Schema::table('inventaris_register_sequences', function (Blueprint $table) {
            $table->dropUnique(['sekolah_id', 'kode_barang', 'tahun']);
            $table->dropColumn('tahun');
        });

        Schema::table('inventaris_register_sequences', function (Blueprint $table) {
            $table->unique(['sekolah_id', 'kode_barang']);
        });
    }
};
