<?php

declare(strict_types=1);

const DEFAULT_DATABASES = [
    'main' => 'kcd_x_belanja_modal',
    'master' => 'kcd_x_master',
    'inventory' => 'kcd_x_inventaris_sekolah',
];

$options = getopt('', ['database:', 'json', 'help']);
if (isset($options['help'])) {
    echo "Read-only MySQL schema audit\n";
    echo "Usage: php scripts/audit-database.php [--database=name] [--json]\n";
    exit(0);
}

require __DIR__ . '/../env.php';

$databaseNames = isset($options['database'])
    ? [(string) $options['database']]
    : array_values(array_unique([
        getenv('DB_MAIN') ?: DEFAULT_DATABASES['main'],
        getenv('MASTER_DB_DATABASE') ?: DEFAULT_DATABASES['master'],
        getenv('DB_INV') ?: DEFAULT_DATABASES['inventory'],
    ]));

foreach ($databaseNames as $database) {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $database)) {
        fwrite(STDERR, "Invalid database name.\n");
        exit(2);
    }
}

try {
    $pdo = new PDO(
        sprintf(
            'mysql:host=%s;port=%s;charset=utf8mb4',
            getenv('DB_HOST') ?: '127.0.0.1',
            getenv('DB_PORT') ?: '3306',
        ),
        getenv('DB_USER') ?: 'root',
        getenv('DB_PASS') ?: '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ],
    );
} catch (Throwable $exception) {
    fwrite(STDERR, "Database connection failed. Check configured credentials.\n");
    exit(2);
}

$report = [
    'generated_at' => gmdate(DATE_ATOM),
    'server_version' => $pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
    'databases' => [],
];

foreach ($databaseNames as $database) {
    $exists = fetchValue(
        $pdo,
        'SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?',
        [$database],
    ) > 0;

    if (!$exists) {
        $report['databases'][$database] = ['exists' => false];
        continue;
    }

    $tables = fetchAll(
        $pdo,
        'SELECT TABLE_NAME, TABLE_TYPE, ENGINE, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH, TABLE_COLLATION
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = ?
         ORDER BY TABLE_NAME',
        [$database],
    );

    foreach ($tables as &$table) {
        $table['exact_rows'] = $table['TABLE_TYPE'] === 'BASE TABLE'
            ? (int) $pdo->query(sprintf('SELECT COUNT(*) FROM `%s`.`%s`', $database, $table['TABLE_NAME']))->fetchColumn()
            : null;
    }
    unset($table);

    $report['databases'][$database] = [
        'exists' => true,
        'tables' => $tables,
        'columns' => fetchAll(
            $pdo,
            'SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, COLUMN_KEY
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ?
             ORDER BY TABLE_NAME, ORDINAL_POSITION',
            [$database],
        ),
        'indexes' => fetchAll(
            $pdo,
            'SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ?
             ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX',
            [$database],
        ),
        'foreign_keys' => fetchAll(
            $pdo,
            'SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_SCHEMA,
                    REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME IS NOT NULL
             ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION',
            [$database],
        ),
        'triggers' => fetchAll(
            $pdo,
            'SELECT TRIGGER_NAME, EVENT_MANIPULATION, EVENT_OBJECT_TABLE, ACTION_TIMING
             FROM information_schema.TRIGGERS
             WHERE TRIGGER_SCHEMA = ?
             ORDER BY TRIGGER_NAME',
            [$database],
        ),
    ];
}

if (isset($options['json'])) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
    exit(0);
}

echo "MySQL ", $report['server_version'], "\n";
foreach ($report['databases'] as $database => $details) {
    if (!$details['exists']) {
        echo $database, ": missing\n";
        continue;
    }

    echo $database, ': ', count($details['tables']), " tables\n";
    foreach ($details['tables'] as $table) {
        printf("  %-40s %d rows\n", $table['TABLE_NAME'], $table['exact_rows'] ?? 0);
    }
}

function fetchAll(PDO $pdo, string $sql, array $parameters): array
{
    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);

    return $statement->fetchAll();
}

function fetchValue(PDO $pdo, string $sql, array $parameters): int
{
    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);

    return (int) $statement->fetchColumn();
}
