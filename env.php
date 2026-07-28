<?php
// Parser .env manual (no library/no composer). Load sekali, inject ke getenv()/$_ENV.
// Database NAME tetap hardcode per koneksi; hanya host/user/pass dari env.
if (isset($GLOBALS['__ENV_LOADED'])) { return; }
$GLOBALS['__ENV_LOADED'] = true;

// === Error reporting via APP_DEBUG (default: sembunyikan dari layar, tetap ke log) ===
// Tampil di layar hanya saat APP_DEBUG=true (dev). Production: display off, log tetap kaya.
if (getenv('APP_DEBUG') === 'true') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
}
ini_set('log_errors', '1'); // selalu catat ke server log

$envFile = __DIR__ . '/.env';
if (!is_readable($envFile)) { return; } // fallback getenv bawaan / default lokal

foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') { continue; }
    if (!str_contains($line, '=')) { continue; }
    [$k, $v] = explode('=', $line, 2);
    $k = trim($k);
    $v = trim($v);
    // strip quote tunggal/ganda
    if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && $v[strlen($v) - 1] === $v[0]) {
        $v = substr($v, 1, -1);
    }
    // jangan override env yang sudah diset oleh proses/system
    if (getenv($k) === false) {
        putenv($k . '=' . $v);
        $_ENV[$k] = $v;
    }
}
