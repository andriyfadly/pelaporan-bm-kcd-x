<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Backup bulanan (spatie/laravel-backup)
|--------------------------------------------------------------------------
|
| Jalan tiap AKHIR BULAN pukul 23:00, setelah jam pakai aplikasi (09:00-23:00).
| Proses ini men-dump database lalu meng-zip file aplikasi, jadi tidak
| dijadwalkan di tengah jam kerja.
|
| Jadwal disusun sebagai dailyAt + filter isLastOfMonth(), bukan
| ->lastDayOfMonth(). Helper itu menyusun ekspresi cron dari tanggal akhir
| bulan SAAT jadwal didaftarkan (mis. "0 23 31 * *"), sehingga bulan 30 hari
| dilewati. Filter isLastOfMonth() dievaluasi pada hari-H sehingga selalu
| cocok dengan akhir bulan berapa pun panjangnya.
|
*/
Schedule::command('backup:run')
    ->dailyAt('23:00')
    ->when(fn () => now()->isLastOfMonth())
    ->withoutOverlapping(120)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/backup.log'));

// Bersihkan backup lama (strategi retensi di config/backup.php) sesudah backup.
Schedule::command('backup:clean')
    ->dailyAt('23:30')
    ->when(fn () => now()->isLastOfMonth())
    ->withoutOverlapping(120)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/backup.log'));

// Pantau kesehatan backup: umur backup > 40 hari atau ukuran melebihi batas.
Schedule::command('backup:monitor')
    ->dailyAt('04:00')
    ->appendOutputTo(storage_path('logs/backup.log'));
