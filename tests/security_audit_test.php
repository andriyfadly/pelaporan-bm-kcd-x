<?php

function expect(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$login = file_get_contents(__DIR__ . '/../login.php');
$progress = file_get_contents(__DIR__ . '/../cek_progres_unduh.php');
$printPage = file_get_contents(__DIR__ . '/../cetak_bm.php');
$index = file_get_contents(__DIR__ . '/../index.php');
$lockedEndpoints = [
    'simpan_realisasi.php',
    'simpan_katalog_barang.php',
    'proses_edit_spj.php',
    'hapus_spj.php',
];

expect(!str_contains($login, "dev_sec_token"), 'login throttling must not trust a client cookie');
expect(str_contains($login, 'record_failed_login_attempt($username'), 'login failures must be recorded');
expect(str_contains($login, "'account'"), 'login throttling must keep an account-wide bucket');
expect(str_contains($login, "'ip'"), 'login throttling must keep an IP-wide bucket');
expect(str_contains($login, 'window_started_at'), 'login throttling buckets must expire stale attempts');
expect(str_contains($progress, "REQUEST_METHOD'] !== 'POST'"), 'progress endpoint must require POST');
expect(!str_contains($progress, '$_GET[\'csrf_token\']'), 'progress endpoint must not accept CSRF token in URL');
expect(!str_contains($printPage, 'csrf_token=${token}'), 'download polling must not put CSRF token in URL');
expect(str_contains($index, "script-src-elem 'self' 'nonce-\$csp_nonce'"), 'CSP must require nonce on inline script elements');
expect(str_contains($index, "'nonce-"), 'CSP must authorize scripts with nonce');

$userIni = file_get_contents(__DIR__ . '/../.user.ini');
expect(str_contains($userIni, 'auto_prepend_file = session_security.php'), 'PHP must auto-prepend session security');
expect(str_contains($userIni, 'session.use_strict_mode = 1'), 'strict session mode must be global');
expect(!str_contains(file_get_contents(__DIR__ . '/../session_security.php'), 'session_start();'), 'auto-prepend must not start sessions before endpoint configuration');
expect(str_contains($index, 'newScript.nonce = SCRIPT_NONCE'), 'dynamic scripts must carry CSP nonce');

foreach ($lockedEndpoints as $endpoint) {
    $source = file_get_contents(__DIR__ . '/../' . $endpoint);
    expect(str_contains($source, 'assert_report_unlocked('), "{$endpoint} must enforce report lock server-side");
}

echo "security audit checks passed\n";
