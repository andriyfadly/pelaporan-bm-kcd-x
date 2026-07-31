<?php

declare(strict_types=1);

function expectAudit(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$script = __DIR__ . '/../scripts/audit-database.php';
expectAudit(is_file($script), 'database audit script must exist');

$source = file_get_contents($script);
expectAudit(str_contains($source, 'information_schema'), 'audit must inspect information_schema');
expectAudit(str_contains($source, '--database='), 'audit must accept an explicit database');
expectAudit(str_contains($source, '--json'), 'audit must support machine-readable JSON');
expectAudit(!preg_match('/SELECT\s+\*/i', $source), 'audit must not dump row-level data');
expectAudit(!str_contains($source, 'DB_PASS='), 'audit must not print database password');

exec('php ' . escapeshellarg($script) . ' --help 2>&1', $output, $exitCode);
expectAudit($exitCode === 0, '--help must exit successfully');
expectAudit(str_contains(implode("\n", $output), 'Read-only'), 'help must state read-only behavior');

echo "database audit checks passed\n";
