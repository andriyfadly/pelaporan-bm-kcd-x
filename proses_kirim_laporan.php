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
    
    // Ambil dan bersihkan data inputan
    $id_sekolah = isset($_POST['id_sekolah']) ? mysqli_real_escape_string($conn, $_POST['id_sekolah']) : '';
    $bulan_realisasi = isset($_POST['bulan_realisasi']) ? (int)$_POST['bulan_realisasi'] : 0;

    // Validasi data kosong atau tidak valid
    if (empty($id_sekolah) || $bulan_realisasi <= 0 || $bulan_realisasi > 12) {
        echo "<script>alert('Gagal! Parameter ID Sekolah atau Bulan tidak valid.'); window.history.back();</script>";
        exit;
    }

    // 4. Query Simpan ke Database (Gunakan ON DUPLICATE KEY agar tidak double data)
    $query_insert = "INSERT INTO `laporan_realisasi` (`id_sekolah`, `bulan`, `status`, `tanggal_kirim`) 
                     VALUES ('$id_sekolah', $bulan_realisasi, 'Menunggu Approval', NOW())
                     ON DUPLICATE KEY UPDATE `status` = 'Menunggu Approval', `tanggal_kirim` = NOW()";
    
    if (mysqli_query($conn, $query_insert)) {
        // Berhasil: Alihkan kembali ke halaman sebelumnya (refresher otomatis)
        echo "<script>
                alert('Laporan berhasil dikirim! Menunggu persetujuan admin.');
                window.location.href = document.referrer;
              </script>";
        exit;
    } else {
        // Gagal: Tampilkan error SQL-nya untuk debug
        $error_db = mysqli_real_escape_string($conn, mysqli_error($conn));
        echo "<script>alert('Gagal menyimpan ke database! Error: $error_db'); window.history.back();</script>";
        exit;
    }

} else {
    // Jika file ini diakses langsung tanpa submit form
    header("Location: login.php");
    exit;
}
?>