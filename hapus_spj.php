<?php
session_start();
include "koneksi.php";

header('Content-Type: application/json');

if (!isset($_POST['no_spk'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No SPK tidak ditemukan.'
    ]);
    exit;
}

$no_spk = mysqli_real_escape_string($conn, $_POST['no_spk']);
$id_sekolah = $_SESSION['id_sekolah'];

$query = mysqli_query($conn, "
    DELETE FROM realisasi_barang_sekolah
    WHERE no_spk='$no_spk'
    AND id_sekolah='$id_sekolah'
");

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