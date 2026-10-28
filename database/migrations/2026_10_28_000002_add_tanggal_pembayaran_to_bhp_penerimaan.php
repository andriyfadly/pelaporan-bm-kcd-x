<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tanggal pembayaran penerimaan.
 *
 * `no_kwitansi` kosong berarti barang sudah diterima tetapi transaksi keuangan
 * belum terjadi — baris itu bertanda "Lunas Bayar" di Buku Penerimaan dan
 * muncul di halaman Lunas Bayar. `tanggal_pembayaran` dibutuhkan untuk tahu
 * apakah pembayaran jatuh pada bulan laporan yang sedang dicetak.
 *
 * Data lama: baris yang sudah berkwitansi dianggap dibayar pada tanggal
 * titimangsa/BAST-nya, supaya PDF lama tidak tiba-tiba penuh "Lunas Bayar".
 */
return new class extends Migration
{
    private const TABEL = ['bhp_bos_penerimaan', 'bhp_bopd_penerimaan'];

    public function up(): void
    {
        foreach (self::TABEL as $tabel) {
            if (! Schema::hasTable($tabel)) {
                continue;
            }

            Schema::table($tabel, function (Blueprint $table): void {
                $table->date('tanggal_pembayaran')->nullable()->after('no_kwitansi');
            });

            DB::table($tabel)
                ->whereNotNull('no_kwitansi')
                ->where('no_kwitansi', '!=', '')
                ->whereNull('tanggal_pembayaran')
                ->update([
                    'tanggal_pembayaran' => DB::raw('COALESCE(tanggal_bast, tanggal_titimangsa, created_at)'),
                ]);
        }
    }

    public function down(): void
    {
        foreach (self::TABEL as $tabel) {
            if (Schema::hasTable($tabel)) {
                Schema::table($tabel, fn (Blueprint $table) => $table->dropColumn('tanggal_pembayaran'));
            }
        }
    }
};
