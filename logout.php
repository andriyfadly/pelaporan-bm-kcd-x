<?php
// 1. Aktifkan session
session_start();

// 2. Hapus semua variabel session yang tersimpan
$_SESSION = [];

// 3. Hancurkan session cookie di browser jika ada
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Hancurkan session utama di server
session_destroy();

// 5. Tendang keluar total ke halaman login.php dengan reload bersih
header("Location: login.php");
exit;
?>