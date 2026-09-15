<?php

use App\Http\Controllers\Admin\KodeBarangController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\PasswordExpiredController;
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
        Route::post('/spj', [SpjController::class, 'store'])->name('spj.store');
        Route::put('/spj/{spj}', [SpjController::class, 'update'])->name('spj.update');
        Route::post('/spj/{spj}/toggle-realisasi', [SpjController::class, 'toggleRealisasi'])->name('spj.toggle-realisasi');
        Route::delete('/spj/{spj}', [SpjController::class, 'destroy'])->name('spj.destroy');
        Route::delete('/spj/spk/{no_spk}', [SpjController::class, 'destroySpk'])->name('spj.destroy-spk')->where('no_spk', '.*');
        Route::get('/cari-barang', [SpjController::class, 'cariBarang'])->name('cari-barang');
        Route::post('/kirim-laporan', [SpjController::class, 'kirimLaporan'])->name('kirim-laporan');
        Route::get('/unduh', [SpjController::class, 'unduh'])->name('unduh');

        Route::get('/input-realisasi/pilih-bulan', [InputRealisasiController::class, 'pilihBulan'])->name('input-realisasi.pilih-bulan');
        Route::get('/input-realisasi', [InputRealisasiController::class, 'index'])->name('input-realisasi.index');
        Route::get('/input-realisasi/tambah', [InputRealisasiController::class, 'tambah'])->name('input-realisasi.tambah');
        Route::post('/input-realisasi/simpan', [InputRealisasiController::class, 'simpan'])->name('input-realisasi.simpan');
        Route::get('/input-realisasi/edit', [InputRealisasiController::class, 'edit'])->name('input-realisasi.edit');
        Route::post('/input-realisasi/update', [InputRealisasiController::class, 'update'])->name('input-realisasi.update');
        Route::post('/input-realisasi/kirim-laporan', [InputRealisasiController::class, 'kirimLaporan'])->name('input-realisasi.kirim-laporan');

        Route::get('/realisasi', [RealisasiController::class, 'index'])->name('realisasi.index');
        Route::get('/realisasi/unduh', [RealisasiController::class, 'unduh'])->name('realisasi.unduh');
        Route::get('/cetak', [CetakController::class, 'show'])->name('cetak');
        Route::post('/cetak/check', [CetakController::class, 'check'])->name('cetak.check');
        Route::get('/cetak/unduh', [CetakController::class, 'unduh'])->name('cetak.unduh');
        Route::post('/kunci-laporan/toggle', [KunciLaporanController::class, 'toggle'])->name('kunci-laporan.toggle');
        Route::post('/kunci-laporan/status', [KunciLaporanController::class, 'updateStatus'])->name('kunci-laporan.status');

        Route::get('/acuan', [AcuanController::class, 'index'])->name('acuan.index');
        Route::post('/acuan', [AcuanController::class, 'store'])->name('acuan.store');
        Route::post('/acuan/import', [AcuanController::class, 'import'])->name('acuan.import');
        Route::post('/acuan/destroy-all', [AcuanController::class, 'destroyAll'])->name('acuan.destroy-all');
        Route::delete('/acuan/{acuan}', [AcuanController::class, 'destroy'])->name('acuan.destroy');

        Route::get('/rekapan', [RekapanController::class, 'index'])->name('rekapan.index');
    });

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/user', [UserController::class, 'index'])->name('user.index');
        Route::post('/user', [UserController::class, 'store'])->name('user.store');
        Route::put('/user/{user}', [UserController::class, 'update'])->name('user.update');
        Route::delete('/user/{user}', [UserController::class, 'destroy'])->name('user.destroy');

        Route::get('/kode-barang', [KodeBarangController::class, 'index'])->name('kode-barang.index');
        Route::post('/kode-barang', [KodeBarangController::class, 'store'])->name('kode-barang.store');
        Route::post('/kode-barang/import', [KodeBarangController::class, 'import'])->name('kode-barang.import');
        Route::delete('/kode-barang/{kodeBarang}', [KodeBarangController::class, 'destroy'])->name('kode-barang.destroy');
    });
});
