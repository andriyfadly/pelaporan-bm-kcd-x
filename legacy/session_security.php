<?php

if (PHP_SAPI === 'cli' || session_status() !== PHP_SESSION_NONE) {
    return;
}

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');

$isHttps = ($_SERVER['HTTPS'] ?? '') === 'on'
    || ($_SERVER['HTTPS'] ?? '') === '1'
    || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

ini_set('session.cookie_secure', $isHttps ? '1' : '0');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
