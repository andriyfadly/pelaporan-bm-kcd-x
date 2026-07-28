<?php
require __DIR__ . '/env.php';
echo 'HOST=[' . getenv('DB_HOST') . "]\n";
echo 'USER=[' . getenv('DB_USER') . "]\n";
echo 'PASS=[' . getenv('DB_PASS') . "]\n";
echo 'PASS_LEN=' . strlen(getenv('DB_PASS')) . "\n";
