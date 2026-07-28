<?php
// DIAG SEMENTARA - hapus setelah selesai
header('Content-Type: text/plain; charset=utf-8');
echo "=== PHP INFO RELEVAN ===\n";
echo "php: " . PHP_VERSION . "\n";
echo "default_charset: " . ini_get('default_charset') . "\n";
echo "mbstring.internal_encoding: " . ini_get('mbstring.internal_encoding') . "\n";
echo "mbstring.func_overload: " . ini_get('mbstring.func_overload') . "\n";
echo "error_log: " . ini_get('error_log') . "\n";
echo "display_errors: " . ini_get('display_errors') . "\n";
echo "APP_DEBUG env: " . getenv('APP_DEBUG') . "\n";

echo "\n=== OPcache ===\n";
echo "opcache.enable: " . ini_get('opcache.enable') . "\n";
echo "opcache.validate_timestamps: " . ini_get('opcache.validate_timestamps') . "\n";
echo "opcache.revalidate_freq: " . ini_get('opcache.revalidate_freq') . "\n";

$file = __DIR__ . '/input_acuan.php';
echo "input_acuan timestamp: " . filemtime($file) . " (" . date('Y-m-d H:i:s', filemtime($file)) . ")\n";
echo "cellToUtf8 in disk file: " . (strpos(file_get_contents($file), 'function cellToUtf8') !== false ? "YES" : "NO") . "\n";

if (function_exists('opcache_get_status')) {
    $st = opcache_get_status(false);
    if ($st && isset($st['scripts'])) {
        $found = null;
        foreach ($st['scripts'] as $s) {
            if (strpos($s['full_path'], 'input_acuan.php') !== false) { $found = $s; break; }
        }
        if ($found) {
            echo "input_acuan CACHED in opcache: YES\n";
            echo "  cached timestamp: " . $found['timestamp'] . " (" . date('Y-m-d H:i:s', $found['timestamp']) . ")\n";
            echo "  has cellToUtf8 in cached: " . (strpos($found['content'] ?? '', 'cellToUtf8') !== false ? "YES" : "NO (STALE!)") . "\n";
        } else {
            echo "input_acuan CACHED in opcache: NO (not cached)\n";
        }
    } else { echo "opcache_get_status no scripts\n"; }
} else { echo "opcache_get_status N/A (opcache tidak loaded via FPM?)\n"; }

echo "\n=== TEST cellToUtf8 pada NBSP ===\n";
require __DIR__ . '/env.php';
// definisi cellToUtf8 ada di input_acuan, replicate di sini
function _diag_cellToUtf8($value) {
    $value = (string)($value ?? '');
    if ($value === '' || preg_match('//u', $value)) return $value;
    $converted = @mb_convert_encoding($value, 'UTF-8', 'CP1252');
    return preg_replace('//u', '', $converted);
}
$test = "AB\xA0CD";
$out = _diag_cellToUtf8($test);
echo "input hex: " . bin2hex($test) . " validUTF8=" . (preg_match('//u',$test)?"Y":"N") . "\n";
echo "output hex: " . bin2hex($out) . " validUTF8=" . (preg_match('//u',$out)?"Y":"N") . "\n";

echo "\n=== error_log test ===\n";
$r = error_log("DIAG TEST: jika baris ini muncul di log, error_log path benar");
echo "error_log returned: " . var_export($r, true) . "\n";
echo "Cek log di path error_log di atas untuk baris 'DIAG TEST'.\n";

echo "\n=== hapus file ini setelah selesai ===\n";
