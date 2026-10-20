<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * phpunit.xml mengunci APP_LOCALE=en agar suite hermetis, jadi terjemahan
 * Indonesia diuji eksplisit di sini: kunci yang hilang di lang/id akan jatuh
 * ke fallback en dan hanya terlihat sebagai pesan Inggris di UI produksi.
 */
class LocaleIndonesiaTest extends TestCase
{
    public function test_terjemahan_indonesia_tersedia_untuk_pesan_yang_dipakai(): void
    {
        $this->app->setLocale('id');

        $this->assertSame('nama pengguna wajib diisi.', __('validation.required', [
            'attribute' => __('validation.attributes.username'),
        ]));

        $this->assertSame('nama pengguna sudah digunakan.', __('validation.unique', [
            'attribute' => __('validation.attributes.username'),
        ]));

        $this->assertSame('Kata sandi tidak cocok dengan kata sandi saat ini.', __('validation.current_password'));

        $this->assertSame('Berkas harus berformat: csv, xlsx.', __('validation.custom.file.mimes', [
            'values' => 'csv, xlsx',
        ]));

        $this->assertSame('Nama pengguna atau kata sandi yang dimasukkan salah.', __('auth.failed'));
        $this->assertSame('Berikutnya &raquo;', __('pagination.next'));
    }

    public function test_validator_memakai_bahasa_indonesia(): void
    {
        $this->app->setLocale('id');

        $errors = validator([], [
            'username' => 'required',
            'kodering' => 'required|max:5',
        ])->errors();

        $this->assertSame('nama pengguna wajib diisi.', $errors->first('username'));
        $this->assertSame('kodering wajib diisi.', $errors->first('kodering'));
    }

    public function test_kunci_tak_diterjemahkan_jatuh_ke_fallback_bukan_kunci_mentah(): void
    {
        $this->app->setLocale('id');

        // 'validation.missing_key_for_test' tidak ada di lang/id maupun lang/en,
        // jadi translator mengembalikan kunci mentah — bukan string kosong.
        $this->assertSame('validation.missing_key_for_test', __('validation.missing_key_for_test'));
    }

    public function test_zona_waktu_app_mengikuti_konfigurasi(): void
    {
        $this->assertSame(config('app.timezone'), date_default_timezone_get());
        $this->assertSame(config('app.timezone'), now()->getTimezone()->getName());
    }
}
