<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Backup dijadwalkan tiap akhir bulan lewat dailyAt + filter isLastOfMonth().
 * Kombinasi ini mudah salah: ->lastDayOfMonth() menyusun cron dari tanggal
 * akhir bulan saat jadwal didaftarkan (statis), dan filter when() tidak
 * dievaluasi oleh isDue() — hanya oleh schedule:run lewat filtersPass().
 */
class BackupScheduleTest extends TestCase
{
    /** @return array{0: Event, 1: Event} */
    private function events(): array
    {
        $schedule = $this->app->make(Schedule::class);
        $events = collect($schedule->events())
            ->filter(fn ($event) => str_contains((string) $event->command, 'backup:run')
                || str_contains((string) $event->command, 'backup:clean'))
            ->values();

        $this->assertCount(2, $events, 'backup:run dan backup:clean harus terjadwal.');

        return [$events[0], $events[1]];
    }

    private function runsAt(string $when): bool
    {
        Carbon::setTestNow(Carbon::parse($when));

        [$run] = $this->events();

        return $run->isDue($this->app) && $run->filtersPass($this->app);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_backup_jalan_di_akhir_bulan_untuk_bulan_31_30_dan_februari(): void
    {
        $this->assertTrue($this->runsAt('2026-10-31 23:00:00'), 'Oktober (31 hari)');
        $this->assertTrue($this->runsAt('2026-11-30 23:00:00'), 'November (30 hari)');
        $this->assertTrue($this->runsAt('2027-02-28 23:00:00'), 'Februari non-kabisat');
        $this->assertTrue($this->runsAt('2028-02-29 23:00:00'), 'Februari kabisat');
        $this->assertTrue($this->runsAt('2026-12-31 23:00:00'), 'akhir tahun');
    }

    public function test_backup_tidak_jalan_di_luar_akhir_bulan(): void
    {
        $this->assertFalse($this->runsAt('2026-10-30 23:00:00'), 'sehari sebelum akhir bulan 31 hari');
        $this->assertFalse($this->runsAt('2026-11-29 23:00:00'), 'sehari sebelum akhir bulan 30 hari');
        $this->assertFalse($this->runsAt('2027-02-27 23:00:00'), 'sehari sebelum akhir Februari');
        $this->assertFalse($this->runsAt('2026-12-30 23:00:00'), 'bukan akhir tahun');
        $this->assertFalse($this->runsAt('2026-10-31 22:59:00'), 'akhir bulan tapi belum pukul 23:00');
    }

    public function test_cleanup_berjalan_setelah_backup(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-31 23:30:00'));

        [, $clean] = $this->events();

        $this->assertTrue($clean->isDue($this->app) && $clean->filtersPass($this->app));
    }
}
