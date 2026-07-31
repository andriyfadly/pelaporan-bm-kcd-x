<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Include koneksi database
include "koneksi.php";

// 2. Proteksi Akses: Pastikan user sudah login
if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'user') {
    echo "<script>alert('Akses ditolak! Silakan login kembali.'); window.location.href='login.php';</script>";
    exit;
}

// 3. Pastikan data dikirim via method POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (empty($csrf_token) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf_token)) {
        http_response_code(403);
        echo "<script>alert('Token keamanan tidak valid.'); window.history.back();</script>";
        exit;
    }
    
    // Ambil dan bersihkan data inputan
    $id_sekolah = (string)($_SESSION['id_sekolah'] ?? '');
    $bulan_realisasi = isset($_POST['bulan_realisasi']) ? (int)$_POST['bulan_realisasi'] : 0;

    // Validasi data kosong atau tidak valid
    if (empty($id_sekolah) || $bulan_realisasi <= 0 || $bulan_realisasi > 12) {
        echo "<script>alert('Gagal! Parameter ID Sekolah atau Bulan tidak valid.'); window.history.back();</script>";
        exit;
    }

    // 4. Query Simpan ke Database (Gunakan ON DUPLICATE KEY agar tidak double data)
    $query_insert = "INSERT INTO `laporan_realisasi` (`id_sekolah`, `bulan`, `status`, `tanggal_kirim`)
                     VALUES (?, ?, 'Menunggu Approval', NOW())
                     ON DUPLICATE KEY UPDATE `status` = 'Menunggu Approval', `tanggal_kirim` = NOW()";
    $stmt_insert = mysqli_prepare($conn, $query_insert);
    mysqli_stmt_bind_param($stmt_insert, "si", $id_sekolah, $bulan_realisasi);

    if (mysqli_stmt_execute($stmt_insert)) {
        // Berhasil: Alihkan kembali ke halaman sebelumnya (refresher otomatis)
        echo "<script>
                alert('Laporan berhasil dikirim! Menunggu persetujuan admin.');
                window.location.href = document.referrer;
              </script>";
        exit;
    } else {
        error_log('Gagal mengirim laporan: ' . mysqli_error($conn));
        echo "<script>alert('Gagal menyimpan laporan. Silakan coba lagi.'); window.history.back();</script>";
        exit;
    }

} else {
    // Jika file ini diakses langsung tanpa submit form
    header("Location: login.php");
    exit;
}
?>
