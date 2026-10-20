<?php

namespace Tests\Feature;

use Spatie\Backup\Config\Config as BackupConfig;
use Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification;
use Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification;
use Tests\TestCase;

/**
 * EventHandler::determineNotification() mencari notifikasi lewat
 * class_basename($event).'Notification' pada backup.notifications.notifications.
 * Kunci yang hilang membuat lookup null lalu melempar NotificationCouldNotBeSent,
 * sehingga backup yang SUDAH sukses dilaporkan gagal. Untuk membisukan
 * notifikasi, channel-nya dikosongkan — kuncinya tidak boleh dihapus.
 */
class BackupNotificationConfigTest extends TestCase
{
    /** @return array<int, class-string> */
    private function requiredKeys(): array
    {
        return [
            BackupHasFailedNotification::class,
            BackupWasSuccessfulNotification::class,
            CleanupHasFailedNotification::class,
            CleanupWasSuccessfulNotification::class,
            HealthyBackupWasFoundNotification::class,
            UnhealthyBackupWasFoundNotification::class,
        ];
    }

    public function test_semua_kunci_notifikasi_wajib_ada_di_config(): void
    {
        $configured = array_keys(config('backup.notifications.notifications'));

        foreach ($this->requiredKeys() as $class) {
            $this->assertContains(
                $class,
                $configured,
                "Kunci {$class} hilang dari backup.notifications.notifications; menghapusnya membuat backup sukses dilaporkan gagal."
            );
        }
    }

    public function test_kegagalan_tetap_dikirim_ke_mail(): void
    {
        $this->assertSame(['mail'], config('backup.notifications.notifications.'.BackupHasFailedNotification::class));
        $this->assertSame(['mail'], config('backup.notifications.notifications.'.CleanupHasFailedNotification::class));
        $this->assertSame(['mail'], config('backup.notifications.notifications.'.UnhealthyBackupWasFoundNotification::class));
    }

    public function test_notifikasi_sukses_dibisukan_lewat_channel_kosong(): void
    {
        $this->assertSame([], config('backup.notifications.notifications.'.BackupWasSuccessfulNotification::class));
        $this->assertSame([], config('backup.notifications.notifications.'.CleanupWasSuccessfulNotification::class));
        $this->assertSame([], config('backup.notifications.notifications.'.HealthyBackupWasFoundNotification::class));
    }

    public function test_config_backup_valid_dibaca_oleh_paket(): void
    {
        // Melempar InvalidConfig bila struktur rusak; ini yang dipakai
        // BackupJob saat runtime, jadi kegagalan di sini = backup gagal.
        $config = BackupConfig::fromArray(config('backup'));

        $this->assertNotEmpty($config->backup->destination->disks);
        $this->assertContains(config('database.default'), $config->backup->source->databases);
    }

    public function test_backup_tidak_menyertakan_vendor_atau_git(): void
    {
        $exclude = config('backup.backup.source.files.exclude');

        $this->assertContains(base_path('vendor'), $exclude);
        $this->assertContains(base_path('node_modules'), $exclude);
        $this->assertContains(base_path('.git'), $exclude);
    }

    public function test_backup_tidak_menyertakan_kredensial_env(): void
    {
        $exclude = config('backup.backup.source.files.exclude');

        $this->assertContains(
            base_path('.env'),
            $exclude,
            'Zip backup tidak boleh memuat .env: isinya APP_KEY, password DB, dan secret Turnstile.'
        );
    }
}
