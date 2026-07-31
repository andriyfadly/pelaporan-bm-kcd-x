<?php

it('does not ship a destructive Laravel schema dump for legacy production databases', function (): void {
    expect(database_path('schema/mysql-schema.sql'))->not->toBeFile();
});
