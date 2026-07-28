<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "koneksi.php";

// Set header JSON di paling atas agar jika ada error tetap terdeteksi sebagai JSON
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header('Content-Type: application/json');

// 1. Deteksi jika javascript meminta inisialisasi total baris di awal klik
if (isset($_GET['init']) && isset($_GET['bulan']) && isset($_GET['tahun'])) {
    $b = (int)$_GET['bulan'];
    $t = (int)$_GET['tahun'];
    
    // Perbaikan Query: Tambahkan validasi tanggal agar SQL tidak crash/error jika ada tanggal '0000-00-00' atau NULL
    $queryStr = "SELECT COUNT(*) as total FROM `realisasi_barang_sekolah` 
                 WHERE `bulan_realisasi` = $b 
                 AND `ba_tgl` IS NOT NULL 
                 AND `ba_tgl` != '0000-00-00' 
                 AND YEAR(`ba_tgl`) = $t";
                 
    $qTotal = mysqli_query($conn, $queryStr);
    
    if ($qTotal) {
        $resTotal = mysqli_fetch_assoc($qTotal);
        $totalRows = (int)$resTotal['total'];
    } else {
        $totalRows = 0; // Jika query gagal, paksa set 0 agar javascript menampilkan modal "Data Tidak Ditemukan"
    }
    
    echo json_encode(['total_rows' => $totalRows]);
    exit;
}

// 2. Proses pembacaan session baris reguler real-time (saat progress berputar)
$progress = isset($_SESSION['progress_download']) ? (int)$_SESSION['progress_download'] : 0;
session_write_close(); 

echo json_encode(['progress' => $progress]);
exit;