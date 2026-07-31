<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "koneksi.php";

// Set header JSON di paling atas agar jika ada error tetap terdeteksi sebagai JSON
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header('Content-Type: application/json; charset=utf-8');

// 1. Validasi Method Request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metode request tidak valid.']);
    exit;
}

// 2. Validasi Sesi Login (Disamakan dengan proses_unduh_bm.php)
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Akses ditolak. Silakan login terlebih dahulu.']);
    exit;
}

// 3. Validasi CSRF Token (Mendukung Header X-CSRF-TOKEN & POST Body)
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
if (empty($csrf_token) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf_token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Token keamanan (CSRF) tidak valid.']);
    exit;
}

// ==============================================================================
// MODUL A: INISIALISASI TOTAL BARIS DATA (Saat Awal Klik Download)
// ==============================================================================
if (isset($_POST['init'], $_POST['bulan'], $_POST['tahun'])) {
    $b = filter_input(INPUT_POST, 'bulan', FILTER_VALIDATE_INT);
    $t = filter_input(INPUT_POST, 'tahun', FILTER_VALIDATE_INT);
    
    if (!$b || !$t) {
        echo json_encode(['total_rows' => 0, 'error' => 'Parameter bulan/tahun tidak valid.']);
        exit;
    }

    // Query menghitung total data yang akan diproses
    $queryStr = "SELECT COUNT(*) as total FROM `realisasi_barang_sekolah`
                 WHERE `bulan_realisasi` = ?
                 AND `ba_tgl` IS NOT NULL 
                 AND YEAR(`ba_tgl`) = ?";

    $stmtProg = mysqli_prepare($conn, $queryStr);
    $totalRows = 0;

    if ($stmtProg) {
        mysqli_stmt_bind_param($stmtProg, "ii", $b, $t);
        mysqli_stmt_execute($stmtProg);
        $resProg = mysqli_stmt_get_result($stmtProg);
        
        if ($resProg && $resTotal = mysqli_fetch_assoc($resProg)) {
            $totalRows = (int)$resTotal['total'];
        }
        mysqli_stmt_close($stmtProg);
    }
    
    // Lepas kunci session agar request lain tidak terblokir
    session_write_close();

    echo json_encode(['total_rows' => $totalRows]);
    exit;
}

// ==============================================================================
// MODUL B: REAL-TIME POLLING PROGRESS DOWNLOAD
// ==============================================================================
$progress = isset($_SESSION['progress_download']) ? (int)$_SESSION['progress_download'] : 0;

// Lepas kunci session agar polling berikutnya/proses download berjalan lancar
session_write_close(); 

echo json_encode(['progress' => $progress]);
exit;