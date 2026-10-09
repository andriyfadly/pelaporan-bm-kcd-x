<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\KodeBarangController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\PasswordExpiredController;
use App\Http\Controllers\DistribusiBhp\BarangController as BhpBarangController;
use App\Http\Controllers\DistribusiBhp\DashboardController as BhpDashboardController;
use App\Http\Controllers\DistribusiBhp\DivisiController as BhpDivisiController;
use App\Http\Controllers\DistribusiBhp\DokumenController as BhpDokumenController;
use App\Http\Controllers\DistribusiBhp\KodeRekeningController as BhpKodeRekeningController;
use App\Http\Controllers\DistribusiBhp\LaporanController as BhpLaporanController;
use App\Http\Controllers\DistribusiBhp\PenerimaanController as BhpPenerimaanController;
use App\Http\Controllers\DistribusiBhp\PengajuanController as BhpPengajuanController;
use App\Http\Controllers\DistribusiBhp\PenyediaController as BhpPenyediaController;
use App\Http\Controllers\DistribusiBhp\ReviewPengajuanController as BhpReviewPengajuanController;
use App\Http\Controllers\DistribusiBhp\SaldoAwalController as BhpSaldoAwalController;
use App\Http\Controllers\DistribusiBhp\SettingsController as BhpSettingsController;
use App\Http\Controllers\Master\PejabatSekolahController as MasterPejabatSekolahController;
use App\Http\Controllers\Master\ProfilSekolahController as MasterProfilSekolahController;
use App\Http\Controllers\PelaporanBm\AcuanController;
use App\Http\Controllers\PelaporanBm\CetakController;
use App\Http\Controllers\PelaporanBm\DashboardController;
use App\Http\Controllers\PelaporanBm\InputRealisasiController;
use App\Http\Controllers\PelaporanBm\KunciLaporanController;
use App\Http\Controllers\PelaporanBm\RealisasiController;
use App\Http\Controllers\PelaporanBm\RekapanController;
use App\Http\Controllers\PelaporanBm\SpjController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/home', function () {
    return redirect()->route('dashboard');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/ubah-password', [PasswordExpiredController::class, 'edit'])->name('password.change');
    Route::post('/ubah-password', [PasswordExpiredController::class, 'update'])->name('password.update');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('pelaporan-bm')->name('pelaporan-bm.')->group(function () {
        Route::get('/spj/pilih-bulan', [SpjController::class, 'pilihBulan'])->name('spj.pilih-bulan');
        Route::get('/spj', [SpjController::class, 'index'])->name('spj.index');
        Route::get('/spj/create', [SpjController::class, 'create'])->name('spj.create');
        Route::get('/spj/edit-spk/{no_spk}', [SpjController::class, 'editSpk'])->name('spj.edit-spk')->where('no_spk', '.*');
        Route::post('/spj/store-spk', [SpjController::class, 'storeSpk'])->name('spj.store-spk');
        Route::post('/spj/import', [SpjController::class, 'import'])->middleware('throttle:10,1')->name('spj.import');
        Route::post('/spj', [SpjController::class, 'store'])->name('spj.store');
        Route::put('/spj/{spj}', [SpjController::class, 'update'])->name('spj.update');
        Route::delete('/spj/{spj}', [SpjController::class, 'destroy'])->name('spj.destroy');
        Route::delete('/spj/spk/{no_spk}', [SpjController::class, 'destroySpk'])->name('spj.destroy-spk')->where('no_spk', '.*');
        Route::get('/cari-barang', [SpjController::class, 'cariBarang'])->middleware('throttle:120,1')->name('cari-barang');
        Route::get('/unduh', [SpjController::class, 'unduh'])->middleware('throttle:30,1')->name('unduh');

        Route::get('/input-realisasi/pilih-bulan', [InputRealisasiController::class, 'pilihBulan'])->name('input-realisasi.pilih-bulan');
        Route::get('/input-realisasi', [InputRealisasiController::class, 'index'])->name('input-realisasi.index');
        Route::get('/input-realisasi/tambah', [InputRealisasiController::class, 'tambah'])->name('input-realisasi.tambah');
        Route::post('/input-realisasi/simpan', [InputRealisasiController::class, 'simpan'])->name('input-realisasi.simpan');
        Route::get('/input-realisasi/edit', [InputRealisasiController::class, 'edit'])->name('input-realisasi.edit');
        Route::post('/input-realisasi/update', [InputRealisasiController::class, 'update'])->name('input-realisasi.update');
        Route::post('/input-realisasi/kirim-laporan', [InputRealisasiController::class, 'kirimLaporan'])->name('input-realisasi.kirim-laporan');

        Route::get('/realisasi', [RealisasiController::class, 'index'])->name('realisasi.index');
        Route::get('/realisasi/unduh', [RealisasiController::class, 'unduh'])->middleware('throttle:30,1')->name('realisasi.unduh');
        Route::get('/cetak', [CetakController::class, 'show'])->name('cetak');
        Route::post('/cetak/check', [CetakController::class, 'check'])->middleware('throttle:60,1')->name('cetak.check');
        Route::get('/cetak/unduh', [CetakController::class, 'unduh'])->middleware('throttle:30,1')->name('cetak.unduh');
        Route::post('/kunci-laporan/toggle', [KunciLaporanController::class, 'toggle'])
            ->middleware('permission:kunci-laporan-bm')
            ->name('kunci-laporan.toggle');
        Route::post('/kunci-laporan/status', [KunciLaporanController::class, 'updateStatus'])
            ->middleware('permission:verifikasi-laporan-bm')
            ->name('kunci-laporan.status');

        Route::get('/acuan', [AcuanController::class, 'index'])->name('acuan.index');
        Route::post('/acuan', [AcuanController::class, 'store'])->name('acuan.store');
        Route::post('/acuan/import', [AcuanController::class, 'import'])->middleware('throttle:10,1')->name('acuan.import');
        Route::post('/acuan/destroy-all', [AcuanController::class, 'destroyAll'])->middleware('throttle:10,1')->name('acuan.destroy-all');
        Route::delete('/acuan/{acuan}', [AcuanController::class, 'destroy'])->name('acuan.destroy');

        Route::get('/rekapan', [RekapanController::class, 'index'])->name('rekapan.index');
    });

    // Helper untuk mendaftarkan rute BHP per sumber dana
    $bhpRoutes = function () {
        Route::get('/pengajuan', [BhpPengajuanController::class, 'index'])
            ->middleware('permission:lihat-distribusi-bhp')
            ->name('pengajuan.index');
        Route::post('/pengajuan', [BhpPengajuanController::class, 'store'])
            ->middleware('permission:kelola-pengajuan-bhp')
            ->name('pengajuan.store');
        Route::put('/pengajuan/{pengajuan}', [BhpPengajuanController::class, 'update'])
            ->middleware('permission:kelola-pengajuan-bhp')
            ->name('pengajuan.update');
        Route::delete('/pengajuan/{pengajuan}', [BhpPengajuanController::class, 'destroy'])
            ->middleware('permission:kelola-pengajuan-bhp')
            ->name('pengajuan.destroy');
        Route::post('/pengajuan/{pengajuan}/submit', [BhpPengajuanController::class, 'submit'])
            ->middleware('permission:kelola-pengajuan-bhp')
            ->name('pengajuan.submit');

        Route::get('/review-pengajuan', [BhpReviewPengajuanController::class, 'index'])
            ->middleware('permission:review-pengajuan-bhp')
            ->name('review-pengajuan.index');
        Route::post('/review-pengajuan/{pengajuan}/approve', [BhpReviewPengajuanController::class, 'approve'])
            ->middleware('permission:approve-pengajuan-bhp')
            ->name('review-pengajuan.approve');
        Route::post('/review-pengajuan/{pengajuan}/reject', [BhpReviewPengajuanController::class, 'reject'])
            ->middleware('permission:approve-pengajuan-bhp')
            ->name('review-pengajuan.reject');

        Route::get('/master/kode-rekening', [BhpKodeRekeningController::class, 'index'])
            ->middleware('permission:lihat-distribusi-bhp')->name('kode-rekening.index');
        Route::post('/master/kode-rekening', [BhpKodeRekeningController::class, 'store'])
            ->middleware('permission:kelola-master-bhp')->name('kode-rekening.store');
        Route::post('/master/kode-rekening/batch-assign', [BhpKodeRekeningController::class, 'batchAssign'])
            ->middleware('permission:lihat-distribusi-bhp')->name('kode-rekening.batch-assign');

        Route::get('/master/barang', [BhpBarangController::class, 'index'])
            ->middleware('permission:lihat-distribusi-bhp')->name('barang.index');
        Route::post('/master/barang', [BhpBarangController::class, 'store'])
            ->middleware('permission:kelola-master-bhp')->name('barang.store');
        Route::post('/master/barang/{barang}/harga', [BhpBarangController::class, 'storeHarga'])
            ->middleware('permission:kelola-master-bhp')->name('barang.harga.store');

        Route::get('/master/penyedia', [BhpPenyediaController::class, 'index'])
            ->middleware('permission:lihat-distribusi-bhp')->name('penyedia.index');
        Route::post('/master/penyedia', [BhpPenyediaController::class, 'store'])
            ->middleware('permission:kelola-master-bhp')->name('penyedia.store');

        Route::middleware('permission:kelola-penerimaan-bhp')->group(function () {
            Route::get('/penerimaan', [BhpPenerimaanController::class, 'index'])->name('penerimaan.index');
            Route::post('/penerimaan', [BhpPenerimaanController::class, 'store'])->name('penerimaan.store');
            Route::delete('/penerimaan/{penerimaan}', [BhpPenerimaanController::class, 'destroy'])->name('penerimaan.destroy');

            Route::get('/saldo-awal', [BhpSaldoAwalController::class, 'index'])->name('saldo-awal.index');
            Route::post('/saldo-awal', [BhpSaldoAwalController::class, 'store'])->name('saldo-awal.store');
            Route::delete('/saldo-awal/{detail}', [BhpSaldoAwalController::class, 'destroy'])->name('saldo-awal.destroy');
            Route::post('/saldo-awal/import', [BhpSaldoAwalController::class, 'import'])
                ->middleware('throttle:10,1')->name('saldo-awal.import');
            Route::get('/saldo-awal/template', [BhpSaldoAwalController::class, 'template'])
                ->middleware('throttle:30,1')->name('saldo-awal.template');
        });

        Route::get('/pengajuan/{pengajuan}/dokumen', [BhpDokumenController::class, 'show'])
            ->middleware('permission:lihat-distribusi-bhp')->name('dokumen.show');
        Route::get('/pengajuan/{pengajuan}/dokumen/{dokumen}/pdf', [BhpDokumenController::class, 'pdf'])
            ->middleware('permission:cetak-dokumen-bhp')->name('dokumen.pdf');
        Route::post('/pengajuan/{pengajuan}/dokumen/batch', [BhpDokumenController::class, 'batch'])
            ->middleware('permission:cetak-dokumen-bhp')->name('dokumen.batch');
        Route::post('/pengajuan/{pengajuan}/dokumen/tambahan', [BhpDokumenController::class, 'tambahan'])
            ->middleware('permission:cetak-dokumen-bhp')->name('dokumen.tambahan');

        Route::get('/laporan', [BhpLaporanController::class, 'index'])
            ->middleware('permission:lihat-distribusi-bhp')->name('laporan.index');
        Route::get('/laporan/kartu-persediaan', [BhpLaporanController::class, 'kartuPersediaan'])
            ->middleware('permission:lihat-distribusi-bhp')->name('laporan.kartu-persediaan');
        Route::get('/laporan/export', [BhpLaporanController::class, 'export'])
            ->middleware('permission:cetak-dokumen-bhp')->name('laporan.export');

        Route::middleware('permission:lihat-distribusi-bhp')->group(function () {
            Route::get('/laporan/bast-internal', [BhpLaporanController::class, 'bastInternal'])->name('laporan.bast-internal');
            Route::get('/laporan/berita-acara-pemeriksaan', [BhpLaporanController::class, 'bap'])->name('laporan.bap');
            Route::get('/laporan/buku-pengeluaran', [BhpLaporanController::class, 'bukuPengeluaranPage'])->name('laporan.buku-pengeluaran');
            Route::get('/laporan/buku-penyaluran', [BhpLaporanController::class, 'bukuPenyaluran'])->name('laporan.buku-penyaluran');
            Route::get('/laporan/stok-opname', [BhpLaporanController::class, 'stokOpname'])->name('laporan.stok-opname');
            Route::get('/laporan/surat-permintaan-barang', [BhpLaporanController::class, 'suratPermintaan'])->name('laporan.surat-permintaan');
            Route::get('/laporan/surat-perintah-penyaluran', [BhpLaporanController::class, 'suratPenyaluran'])->name('laporan.surat-penyaluran');
            Route::get('/laporan/berita-acara-serah-terima', [BhpLaporanController::class, 'bastSerahTerima'])->name('laporan.bast-serah-terima');
        });

        Route::get('/penerimaan/lunas-bayar', [BhpPenerimaanController::class, 'lunasBayar'])
            ->middleware('permission:kelola-penerimaan-bhp')->name('penerimaan.lunas-bayar');
    };

    // Domain Distribusi BHP — terpisah dari Pelaporan BM.
    Route::prefix('distribusi-bhp')->name('distribusi-bhp.')->group(function () use ($bhpRoutes) {
        // Flat routes (tanpa sumber dana)
        Route::get('/dashboard', [BhpDashboardController::class, 'index'])
            ->middleware('permission:lihat-distribusi-bhp')
            ->name('dashboard');

        Route::get('/settings', [BhpSettingsController::class, 'edit'])
            ->middleware('permission:kelola-master-bhp')->name('settings.edit');
        Route::put('/settings', [BhpSettingsController::class, 'update'])
            ->middleware('permission:kelola-master-bhp')->name('settings.update');

        Route::get('/divisi', [BhpDivisiController::class, 'index'])
            ->middleware('permission:kelola-divisi-bhp')->name('divisi.index');
        Route::post('/divisi', [BhpDivisiController::class, 'store'])
            ->middleware('permission:kelola-divisi-bhp')->name('divisi.store');
        Route::put('/divisi/operator/{operator}/password', [BhpDivisiController::class, 'resetPassword'])
            ->middleware('permission:kelola-divisi-bhp')->name('divisi.operator.password');
        Route::put('/divisi/operator/{operator}/toggle', [BhpDivisiController::class, 'toggleActive'])
            ->middleware('permission:kelola-divisi-bhp')->name('divisi.operator.toggle');

        // Per-sumber-dana routes — middleware baca sumber dana dari prefix
        Route::prefix('bos')->name('bos.')->middleware('sumber-dana-bhp')->group($bhpRoutes);
        Route::prefix('bopd')->name('bopd.')->middleware('sumber-dana-bhp')->group($bhpRoutes);

        // Backward compat: tanpa prefix sumber dana, default ke BOS
        Route::middleware('sumber-dana-bhp')->group($bhpRoutes);
    });

    // Master data generic lintas domain — di luar prefix distribusi-bhp.
    Route::prefix('master-data')->name('master-data.')->middleware('permission:kelola-master-data-sekolah')->group(function () {
        Route::get('/pejabat', [MasterPejabatSekolahController::class, 'index'])->name('pejabat.index');
        Route::post('/pejabat', [MasterPejabatSekolahController::class, 'store'])->name('pejabat.store');
        Route::put('/pejabat/{pejabat}', [MasterPejabatSekolahController::class, 'update'])->name('pejabat.update');
        Route::put('/pejabat/{pejabat}/akhiri', [MasterPejabatSekolahController::class, 'akhiri'])->name('pejabat.akhiri');

        Route::get('/profil-sekolah', [MasterProfilSekolahController::class, 'edit'])->name('profil-sekolah.edit');
        Route::put('/profil-sekolah', [MasterProfilSekolahController::class, 'update'])->name('profil-sekolah.update');
    });

    Route::prefix('admin')->name('admin.')->middleware('role:super_admin|admin_kcd')->group(function () {
        Route::get('/user', [UserController::class, 'index'])->middleware('permission:kelola-user')->name('user.index');
        Route::post('/user', [UserController::class, 'store'])->middleware('permission:kelola-user')->name('user.store');
        Route::put('/user/{user}', [UserController::class, 'update'])->middleware('permission:kelola-user')->name('user.update');
        Route::delete('/user/{user}', [UserController::class, 'destroy'])->middleware('permission:kelola-user')->name('user.destroy');

        Route::get('/log-aktivitas', [ActivityLogController::class, 'index'])
            ->middleware('permission:lihat-log-aktivitas')
            ->name('log-aktivitas.index');

        Route::get('/kode-barang', [KodeBarangController::class, 'index'])->name('kode-barang.index');
        Route::post('/kode-barang', [KodeBarangController::class, 'store'])->name('kode-barang.store');
        Route::post('/kode-barang/import', [KodeBarangController::class, 'import'])->middleware('throttle:10,1')->name('kode-barang.import');
        Route::delete('/kode-barang/{kodeBarang}', [KodeBarangController::class, 'destroy'])->name('kode-barang.destroy');
    });
});
