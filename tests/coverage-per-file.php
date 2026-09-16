<?php

// ponytail: gate per-file untuk composer test-coverage. phpunit
// --min hanya mengecek total, jadi script ini mem-parse Clover XML
// dan gagal (exit 1) bila ada SATU PUN file < 80% line coverage.

declare(strict_types=1);

$clover = $argv[1] ?? 'storage/coverage/clover.xml';
$min = (float) ($argv[2] ?? 80);

if (! file_exists($clover)) {
    fwrite(STDERR, "Clover XML tidak ditemukan: {$clover}\n");

    exit(1);
}

$xml = simplexml_load_file($clover);

if ($xml === false) {
    fwrite(STDERR, "Gagal mem-parse Clover XML: {$clover}\n");

    exit(1);
}

$buruk = [];

foreach ($xml->xpath('//file') as $file) {
    $metrics = $file->metrics;
    $statements = (int) $metrics['statements'];
    $covered = (int) $metrics['coveredstatements'];

    if ($statements === 0) {
        continue;
    }

    $pct = 100 * $covered / $statements;

    if ($pct < $min) {
        $buruk[(string) $file['name']] = round($pct, 1);
    }
}

if ($buruk !== []) {
    fwrite(STDERR, "Line coverage di bawah {$min}%:\n");

    foreach ($buruk as $file => $pct) {
        fwrite(STDERR, "  - {$file}: {$pct}%\n");
    }

    exit(1);
}

fwrite(STDOUT, 'Semua file >= '.$min."% line coverage.\n");
