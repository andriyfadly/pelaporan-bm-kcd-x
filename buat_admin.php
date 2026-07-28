<?php
// Panggil koneksi database
include 'koneksi.php';

$username = 'admin';
$password_plain = '123';
$password_hash = password_hash($password_plain, PASSWORD_DEFAULT);
$nama_sekolah = 'Dinas Pusat';
$id_sekolah = 0; // Pakai 0 agar aman dari aturan NOT NULL database

// Cek dulu apakah username admin sudah ada di database
$cek = mysqli_query($conn, "SELECT id FROM users WHERE username = '$username'");

if (mysqli_num_rows($cek) > 0) {
    // Kalau ternyata udah ada (tapi error), kita timpa/reset passwordnya
    $update = mysqli_query($conn, "UPDATE users SET password = '$password_hash', id_sekolah = '$id_sekolah', nama_sekolah = '$nama_sekolah' WHERE username = '$username'");
    
    if ($update) {
        echo "<h1>BERHASIL!</h1>";
        echo "<p>Akun admin sudah ada, dan password berhasil di-reset menjadi: <b>123</b></p>";
    } else {
        echo "Gagal update akun: " . mysqli_error($conn);
    }
} else {
    // Kalau belum ada sama sekali, kita buat baru
    $insert = mysqli_query($conn, "INSERT INTO users (username, password, id_sekolah, nama_sekolah) VALUES ('$username', '$password_hash', '$id_sekolah', '$nama_sekolah')");
    
    if ($insert) {
        echo "<h1>BERHASIL!</h1>";
        echo "<p>Akun admin baru berhasil dibuat dengan password: <b>123</b></p>";
    } else {
        echo "Gagal membuat akun admin: " . mysqli_error($conn);
    }
}

echo "<br><a href='login.php'>Klik di sini untuk kembali ke halaman Login</a>";
?>