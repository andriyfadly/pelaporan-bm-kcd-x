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
use App\Http\Controllers\Master\JabatanController as MasterJabatanController;
use App\Http\Controllers\Master\PenugasanPegawaiController as MasterPenugasanPegawaiController;
use App\Http\Controllers\Master\ProfilSekolahController as MasterProfilSekolahController;
use App\Http\Controllers\PelaporanBm\AcuanController;
use App\Http\Controllers\PelaporanBm\CetakController;
use App\Http\Controllers\PelaporanBm\DashboardController;
use App\Http\Controllers\PelaporanBm\InputRealisasiController;
use App\Http\Controllers\PelaporanBm\KunciLaporanController;
use App\Http\Controllers\PelaporanBm\RealisasiController;
use App\Http\Controllers\PelaporanBm\RekapanController;
use App\Http\Controllers\PelaporanBm\SpjController;
use App\Http\Controllers\PencatatanBm\BeritaAcaraController as PencatatanBeritaAcaraController;
use App\Http\Controllers\PencatatanBm\DashboardController as PencatatanDashboardController;
use App\Http\Controllers\PencatatanBm\DokumenInventarisController as PencatatanDokumenController;
use App\Http\Controllers\PencatatanBm\InventarisController as PencatatanInventarisController;
use App\Http\Controllers\PencatatanBm\MutasiController as PencatatanMutasiController;
use App\Http\Controllers\PencatatanBm\PegawaiController as PencatatanPegawaiController;
use App\Http\Controllers\PencatatanBm\PengembalianController as PencatatanPengembalianController;
use App\Http\Controllers\PencatatanBm\RuangController as PencatatanRuangController;
use App\Http\Controllers\PencatatanBm\SyncController as PencatatanSyncController;
use App\Http\Controllers\PencatatanBm\UnitInventarisController as PencatatanUnitController;
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

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:lihat-laporan-bm')
        ->name('dashboard');

    Route::prefix('pelaporan-bm')->name('pelaporan-bm.')->middleware('permission:lihat-laporan-bm')->group(function () {
        Route::get('/spj/pilih-bulan', [SpjController::class, 'pilihBulan'])->name('spj.pilih-bulan');
        Route::get('/spj', [SpjController::class, 'index'])->name('spj.index');
        Route::get('/spj/create', [SpjController::class, 'create'])->middleware('permission:input-spj-bm')->name('spj.create');
        Route::get('/spj/edit-spk/{no_spk}', [SpjController::class, 'editSpk'])->middleware('permission:edit-spj-bm')->name('spj.edit-spk')->where('no_spk', '.*');
        Route::post('/spj/store-spk', [SpjController::class, 'storeSpk'])->middleware('permission:input-spj-bm|edit-spj-bm')->name('spj.store-spk');
        Route::post('/spj/import', [SpjController::class, 'import'])->middleware(['permission:input-spj-bm', 'throttle:10,1'])->name('spj.import');
        Route::post('/spj', [SpjController::class, 'store'])->middleware('permission:input-spj-bm')->name('spj.store');
        Route::put('/spj/{spj}', [SpjController::class, 'update'])->middleware('permission:edit-spj-bm')->name('spj.update');
        Route::delete('/spj/{spj}', [SpjController::class, 'destroy'])->middleware('permission:hapus-spj-bm')->name('spj.destroy');
        Route::delete('/spj/spk/{no_spk}', [SpjController::class, 'destroySpk'])->middleware('permission:hapus-spj-bm')->name('spj.destroy-spk')->where('no_spk', '.*');
        Route::get('/cari-barang', [SpjController::class, 'cariBarang'])->middleware('throttle:120,1')->name('cari-barang');
        Route::get('/unduh', [SpjController::class, 'unduh'])->middleware(['permission:unduh-rekap-bm', 'throttle:30,1'])->name('unduh');

        Route::get('/input-realisasi/pilih-bulan', [InputRealisasiController::class, 'pilihBulan'])->name('input-realisasi.pilih-bulan');
        Route::get('/input-realisasi', [InputRealisasiController::class, 'index'])->name('input-realisasi.index');
        Route::get('/input-realisasi/tambah', [InputRealisasiController::class, 'tambah'])->middleware('permission:input-spj-bm')->name('input-realisasi.tambah');
        Route::post('/input-realisasi/simpan', [InputRealisasiController::class, 'simpan'])->middleware('permission:input-spj-bm')->name('input-realisasi.simpan');
        Route::get('/input-realisasi/edit', [InputRealisasiController::class, 'edit'])->middleware('permission:edit-spj-bm')->name('input-realisasi.edit');
        Route::post('/input-realisasi/update', [InputRealisasiController::class, 'update'])->middleware('permission:edit-spj-bm')->name('input-realisasi.update');
        Route::post('/input-realisasi/kirim-laporan', [InputRealisasiController::class, 'kirimLaporan'])->middleware('permission:input-spj-bm')->name('input-realisasi.kirim-laporan');

        Route::get('/realisasi', [RealisasiController::class, 'index'])->name('realisasi.index');
        Route::get('/realisasi/unduh', [RealisasiController::class, 'unduh'])->middleware(['permission:unduh-rekap-bm', 'throttle:30,1'])->name('realisasi.unduh');
        Route::get('/cetak', [CetakController::class, 'show'])->middleware('permission:cetak-laporan-bm')->name('cetak');
        Route::post('/cetak/check', [CetakController::class, 'check'])->middleware(['permission:cetak-laporan-bm', 'throttle:60,1'])->name('cetak.check');
        Route::get('/cetak/unduh', [CetakController::class, 'unduh'])->middleware(['permission:cetak-laporan-bm', 'throttle:30,1'])->name('cetak.unduh');
        Route::post('/kunci-laporan/toggle', [KunciLaporanController::class, 'toggle'])
            ->middleware('permission:kunci-laporan-bm')
            ->name('kunci-laporan.toggle');
        Route::post('/kunci-laporan/status', [KunciLaporanController::class, 'updateStatus'])
            ->middleware('permission:verifikasi-laporan-bm')
            ->name('kunci-laporan.status');

        Route::get('/acuan', [AcuanController::class, 'index'])->name('acuan.index');
        Route::post('/acuan', [AcuanController::class, 'store'])->middleware('permission:input-spj-bm')->name('acuan.store');
        Route::post('/acuan/import', [AcuanController::class, 'import'])->middleware(['permission:input-spj-bm', 'throttle:10,1'])->name('acuan.import');
        Route::post('/acuan/destroy-all', [AcuanController::class, 'destroyAll'])->middleware(['permission:hapus-spj-bm', 'throttle:10,1'])->name('acuan.destroy-all');
        Route::delete('/acuan/{acuan}', [AcuanController::class, 'destroy'])->middleware('permission:hapus-spj-bm')->name('acuan.destroy');

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
            ->middleware('permission:assign-kode-rekening-bhp')->name('kode-rekening.batch-assign');

        Route::get('/master/barang', [BhpBarangController::class, 'index'])
            ->middleware('permission:lihat-distribusi-bhp')->name('barang.index');
        Route::get('/master/barang/{barang}/kelola-harga', [BhpBarangController::class, 'hargaIndex'])
            ->middleware('permission:lihat-distribusi-bhp')->name('barang.harga.index');
        Route::get('/master/barang/template', [BhpBarangController::class, 'template'])
            ->middleware('permission:kelola-barang-bhp')->name('barang.template');
        Route::middleware('permission:kelola-barang-bhp')->group(function () {
            Route::post('/master/barang', [BhpBarangController::class, 'store'])->name('barang.store');
            Route::post('/master/barang/import', [BhpBarangController::class, 'import'])
                ->middleware('throttle:10,1')->name('barang.import');
            Route::put('/master/barang/{barang}', [BhpBarangController::class, 'update'])->name('barang.update');
            Route::delete('/master/barang/{barang}', [BhpBarangController::class, 'destroy'])->name('barang.destroy');
            Route::post('/master/barang/{barang}/harga', [BhpBarangController::class, 'storeHarga'])->name('barang.harga.store');
            Route::put('/master/harga-barang/{harga}', [BhpBarangController::class, 'updateHarga'])->name('barang.harga.update');
            Route::delete('/master/harga-barang/{harga}', [BhpBarangController::class, 'destroyHarga'])->name('barang.harga.destroy');
        });

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
        Route::put('/divisi/{divisi}', [BhpDivisiController::class, 'update'])
            ->middleware('permission:kelola-divisi-bhp')->name('divisi.update');
        Route::delete('/divisi/{divisi}', [BhpDivisiController::class, 'destroy'])
            ->middleware('permission:kelola-divisi-bhp')->name('divisi.destroy');
        Route::put('/divisi/operator/{operator}/password', [BhpDivisiController::class, 'resetPassword'])
            ->middleware('permission:kelola-divisi-bhp')->name('divisi.operator.password');
        Route::put('/divisi/operator/{operator}/toggle', [BhpDivisiController::class, 'toggleActive'])
            ->middleware('permission:kelola-divisi-bhp')->name('divisi.operator.toggle');

        Route::get('/penyedia', [BhpPenyediaController::class, 'index'])
            ->middleware('permission:lihat-distribusi-bhp')->name('penyedia.index');
        Route::post('/penyedia', [BhpPenyediaController::class, 'store'])
            ->middleware('permission:kelola-penyedia-bhp')->name('penyedia.store');
        Route::put('/penyedia/{penyedia}', [BhpPenyediaController::class, 'update'])
            ->middleware('permission:kelola-penyedia-bhp')->name('penyedia.update');
        Route::delete('/penyedia/{penyedia}', [BhpPenyediaController::class, 'destroy'])
            ->middleware('permission:kelola-penyedia-bhp')->name('penyedia.destroy');

        // Per-sumber-dana routes — middleware baca sumber dana dari prefix
        Route::prefix('bos')->name('bos.')->middleware('sumber-dana-bhp')->group($bhpRoutes);
        Route::prefix('bopd')->name('bopd.')->middleware('sumber-dana-bhp')->group($bhpRoutes);

        // Backward compat: tanpa prefix sumber dana, default ke BOS
        Route::middleware('sumber-dana-bhp')->group($bhpRoutes);
    });

    // Master data generic lintas domain — di luar prefix distribusi-bhp.
    Route::prefix('master-data')->name('master-data.')->middleware('permission:kelola-master-data-sekolah')->group(function () {
        Route::get('/profil-sekolah', [MasterProfilSekolahController::class, 'edit'])->name('profil-sekolah.edit');
        Route::put('/profil-sekolah', [MasterProfilSekolahController::class, 'update'])->name('profil-sekolah.update');
    });

    // Master aset milik sekolah — dipakai sebagai penempatan/penanggung jawab
    // unit inventaris, jabatan, dan riwayat penugasan pegawai. Gate-nya
    // permission granular (operator dan bendahara sekolah), bukan
    // kelola-master-data-sekolah.
    Route::prefix('master-data')->name('master-data.')->group(function () {
        Route::prefix('ruang')->name('ruang.')->middleware('permission:kelola-ruang-bm')->group(function () {
            Route::get('/', [PencatatanRuangController::class, 'index'])->name('index');
            Route::post('/', [PencatatanRuangController::class, 'store'])->name('store');
            Route::put('/{ruang}', [PencatatanRuangController::class, 'update'])->name('update');
            Route::delete('/{ruang}', [PencatatanRuangController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('pegawai')->name('pegawai.')->middleware('permission:kelola-pegawai-bm')->group(function () {
            Route::get('/', [PencatatanPegawaiController::class, 'index'])->name('index');
            Route::post('/', [PencatatanPegawaiController::class, 'store'])->name('store');
            Route::put('/{pegawai}', [PencatatanPegawaiController::class, 'update'])->name('update');
            Route::delete('/{pegawai}', [PencatatanPegawaiController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('jabatan')->name('jabatan.')->middleware('permission:kelola-jabatan-bm')->group(function () {
            Route::get('/', [MasterJabatanController::class, 'index'])->name('index');
            Route::post('/', [MasterJabatanController::class, 'store'])->name('store');
            Route::put('/{jabatan}', [MasterJabatanController::class, 'update'])->name('update');
            Route::delete('/{jabatan}', [MasterJabatanController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('penugasan')->name('penugasan.')->middleware('permission:kelola-penugasan-pegawai-bm')->group(function () {
            Route::get('/', [MasterPenugasanPegawaiController::class, 'index'])->name('index');
            Route::post('/', [MasterPenugasanPegawaiController::class, 'store'])->name('store');
            Route::put('/{penugasan}', [MasterPenugasanPegawaiController::class, 'update'])->name('update');
            Route::put('/{penugasan}/akhiri', [MasterPenugasanPegawaiController::class, 'akhiri'])->name('akhiri');
            Route::put('/{penugasan}/mutasi', [MasterPenugasanPegawaiController::class, 'mutasi'])->name('mutasi');
        });
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

    // Domain Pencatatan BM — inventaris aset dari realisasi yang disetujui.
    Route::prefix('pencatatan-bm')->name('pencatatan-bm.')->group(function () {
        Route::get('/', [PencatatanDashboardController::class, 'index'])
            ->middleware('permission:lihat-pencatatan-bm')->name('dashboard');

        Route::middleware('permission:lihat-pencatatan-bm')->group(function () {
            Route::get('/inventaris', [PencatatanInventarisController::class, 'index'])->name('inventaris.index');
            Route::get('/inventaris/{unit}', [PencatatanInventarisController::class, 'show'])->name('inventaris.show');
            Route::put('/inventaris/{unit}/kondisi', [PencatatanInventarisController::class, 'updateKondisi'])
                ->middleware('permission:kelola-inventaris-bm')->name('inventaris.kondisi');
            Route::get('/inventaris-export', [PencatatanInventarisController::class, 'export'])
                ->middleware(['permission:cetak-inventaris-bm', 'throttle:30,1'])->name('inventaris.export');

            Route::get('/mutasi', [PencatatanMutasiController::class, 'index'])->name('mutasi.index');
            Route::get('/pengembalian', [PencatatanPengembalianController::class, 'index'])->name('pengembalian.index');
        });

        Route::post('/mutasi/{unit}', [PencatatanMutasiController::class, 'store'])
            ->middleware('permission:mutasi-inventaris-bm')->name('mutasi.store');
        Route::post('/pengembalian/{unit}', [PencatatanPengembalianController::class, 'store'])
            ->middleware('permission:proses-pengembalian-bm')->name('pengembalian.store');

        Route::middleware('permission:cetak-inventaris-bm')->group(function () {
            Route::prefix('dokumen')->name('dokumen.')->group(function () {
                Route::get('/', [PencatatanDokumenController::class, 'index'])->name('index');
                Route::get('/kir', [PencatatanDokumenController::class, 'kir'])->name('kir');
                Route::get('/buku-inventaris', [PencatatanDokumenController::class, 'bukuInventaris'])->name('buku-inventaris');
                Route::get('/sensus', [PencatatanDokumenController::class, 'sensus'])->name('sensus');
                Route::post('/bast/{unit}', [PencatatanDokumenController::class, 'bast'])->name('bast');
                Route::post('/fakta-integritas/{pegawai}', [PencatatanDokumenController::class, 'faktaIntegritas'])->name('fakta-integritas');
                Route::post('/surat-pengembalian/{unit}', [PencatatanDokumenController::class, 'suratPengembalian'])->name('surat-pengembalian');
                Route::get('/{dokumen}/pdf', [PencatatanDokumenController::class, 'pdf'])->name('pdf');
            });

            Route::prefix('berita-acara')->name('berita-acara.')->group(function () {
                Route::get('/', [PencatatanBeritaAcaraController::class, 'index'])->name('index');
                Route::put('/{dokumen}', [PencatatanBeritaAcaraController::class, 'update'])->name('update');
                Route::delete('/{dokumen}', [PencatatanBeritaAcaraController::class, 'destroy'])->name('destroy');
            });

            Route::prefix('label')->name('label.')->group(function () {
                Route::get('/{unit}/qr', [PencatatanUnitController::class, 'qr'])->name('qr');
                Route::get('/{unit}/pdf', [PencatatanUnitController::class, 'label'])->name('pdf');
            });
        });

        Route::post('/inventaris/{unit}/foto', [PencatatanUnitController::class, 'storeFoto'])
            ->middleware('permission:kelola-inventaris-bm')->name('inventaris.foto');
        Route::get('/foto/{foto}', [PencatatanUnitController::class, 'foto'])->name('foto.show');

        Route::middleware('permission:sinkronisasi-inventaris-bm')->group(function () {
            Route::get('/sinkronisasi', [PencatatanSyncController::class, 'index'])->name('sinkronisasi.index');
            Route::post('/sinkronisasi', [PencatatanSyncController::class, 'store'])
                ->middleware('throttle:10,1')->name('sinkronisasi.store');
        });
    });
});
