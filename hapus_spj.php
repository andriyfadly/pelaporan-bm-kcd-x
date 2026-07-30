<?php
session_start();
include "koneksi.php";

header('Content-Type: application/json');

if (!isset($_SESSION['login']) || ($_SESSION['role'] ?? '') !== 'user') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Sesi tidak valid.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Metode request tidak valid.']);
    exit;
}

$csrf_token = $_POST['csrf_token'] ?? '';
if (empty($csrf_token) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf_token)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Token keamanan tidak valid.']);
    exit;
}

if (!isset($_POST['no_spk'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No SPK tidak ditemukan.'
    ]);
    exit;
}

$no_spk = trim($_POST['no_spk']);
$id_sekolah = $_SESSION['id_sekolah'];

$stmt = mysqli_prepare($conn, "
    DELETE FROM realisasi_barang_sekolah
    WHERE no_spk = ? AND id_sekolah = ?
");
mysqli_stmt_bind_param($stmt, "ss", $no_spk, $id_sekolah);
$query = mysqli_stmt_execute($stmt);

if ($query) {
    echo json_encode([
        'status' => 'success',
        'message' => 'SPJ berhasil dihapus.'
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'SPJ gagal dihapus.'
    ]);
}
?>
