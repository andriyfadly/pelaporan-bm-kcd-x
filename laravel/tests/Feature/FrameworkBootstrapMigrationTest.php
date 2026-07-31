<?php

it('has Laravel cache bootstrap migration before permission migration', function (): void {
    $migrations = glob(database_path('migrations/*_*.php'));

    expect($migrations)->toContain(database_path('migrations/2026_07_30_100000_create_cache_table.php'));
});
