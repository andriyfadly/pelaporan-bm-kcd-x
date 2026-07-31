<?php

use App\Http\Controllers\AcuanController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\KodeBarangSearchController;
use App\Http\Controllers\MasterBarangController;
use App\Http\Controllers\ReportWorkflowController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/', HomeController::class)->name('home');
    Route::get('/dashboard', DashboardController::class)->middleware('role:user')->name('dashboard');
    Route::get('/acuan', AcuanController::class)->middleware('role:user')->name('acuan.index');
    Route::get('/inventory/kode-barang', KodeBarangSearchController::class)->middleware('role:user')->name('inventory.kode-barang.search');
    Route::post('/master-barang', [MasterBarangController::class, 'store'])->middleware('role:user')->name('master-barang.store');
    Route::put('/master-barang/batch', [MasterBarangController::class, 'batch'])->middleware('role:user')->name('master-barang.batch');
    Route::post('/realisasi', [ReportWorkflowController::class, 'storeRealisasi'])->middleware('role:user')->name('realisasi.store');
    Route::post('/laporan/{month}/submit', [ReportWorkflowController::class, 'submit'])->middleware('role:user')->name('laporan.submit');
    Route::get('/admin/dashboard', AdminDashboardController::class)->middleware('role:admin')->name('admin.dashboard');
    Route::post('/admin/laporan/{schoolId}/{month}/approve', [ReportWorkflowController::class, 'approve'])->middleware('role:admin')->name('admin.laporan.approve');
    Route::post('/admin/laporan/{schoolId}/{month}/reject', [ReportWorkflowController::class, 'reject'])->middleware('role:admin')->name('admin.laporan.reject');
});
