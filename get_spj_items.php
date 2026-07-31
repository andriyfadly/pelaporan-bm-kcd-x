<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set header response berupa JSON
header('Content-Type: application/json');

// Pastikan user sudah login
if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'user') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Sesi tidak valid.'
    ]);
    exit;
}

include "koneksi.php";

// Ambil parameter data
$id_sekolah = $_SESSION['id_sekolah'] ?? '';
$no_spk     = trim($_GET['no_spk'] ?? '');

if (empty($no_spk)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Nomor SPK tidak boleh kosong!'
    ]);
    exit;
}

// Ambil SEMUA barang yang nomor SPK-nya sama dalam sekolah tersebut
$query = "SELECT * FROM `master_barang_sekolah`
          WHERE `no_spk` = ? AND `id_sekolah` = ?
          ORDER BY `id` ASC";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ss", $no_spk, $id_sekolah);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$result) {
    error_log('Gagal mengambil item SPJ: ' . mysqli_error($conn));
    echo json_encode([
        'status' => 'error',
        'message' => 'Gagal mengambil data. Silakan coba lagi.'
    ]);
    exit;
}

$data_barang = [];
while ($row = mysqli_fetch_assoc($result)) {
    $data_barang[] = $row;
}

// Kirimkan balik ke JavaScript berupa array data lengkap
echo json_encode([
    'status' => 'success',
    'data' => $data_barang
]);
exit;
?>
