<?php
// === KEAMANAN LAPIS BAJA: SEMBUNYIKAN DETAIL ERROR DARI LAYAR ===
// Memaksa mysqli untuk melemparkan Exception daripada memunculkan Warning di layar
mysqli_report(MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR);

// Konfigurasi Database
$host = "localhost";
$user = "root";
$pass = ""; // PERINGATAN: Di server asli/hosting, JANGAN PERNAH biarkan password kosong!
$db   = "belanja_modal";

try {
    // Menjalankan Koneksi
    $conn = mysqli_connect($host, $user, $pass, $db);

    // === KEAMANAN LAPIS BAJA: PROTEKSI ENCODING ===
    // Wajib set utf8mb4 untuk mencegah SQL Injection via karakter aneh (multibyte)
    if (!mysqli_set_charset($conn, "utf8mb4")) {
        throw new Exception("Gagal mengatur charset utf8mb4");
    }

} catch (mysqli_sql_exception $e) {
    // === KEAMANAN LAPIS BAJA: PENANGANAN ERROR RAHASIA ===
    // 1. Catat error asli HANYA di log server (hacker tidak bisa lihat)
    error_log("Koneksi Database Gagal: " . $e->getMessage());
    
    // 2. Tampilkan pesan generik/umum ke layar (hacker tidak dapat petunjuk apa-apa)
    die("Pemberitahuan: Terjadi gangguan komunikasi dengan server data. Silakan coba beberapa saat lagi.");
    
} catch (Exception $e) {
    // Tangkap error umum lainnya tanpa membocorkan info
    error_log("Error Sistem: " . $e->getMessage());
    die("Pemberitahuan: Sistem sedang dalam perbaikan.");
}
?>