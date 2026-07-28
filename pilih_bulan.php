<?php
// KEAMANAN 1: Proteksi Session Cookie & Security Headers
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Strict');
    if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === '1')) {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

if (!headers_sent()) {
    header("X-Frame-Options: SAMEORIGIN");
    header("X-Content-Type-Options: nosniff");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
}

// KEAMANAN 2: Proteksi Session Hijacking & Hak Akses
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';

if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit;
}

if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== md5($user_agent)) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}

include "koneksi.php";

$id_sekolah = isset($_SESSION['id_sekolah']) ? trim($_SESSION['id_sekolah']) : '';

// ================= DATA SEKOLAH ACTIVE & NAMA SEKOLAH UNTUK HEADER KANAN =================
$satuan_pendidikan_tampil = "Sekolah Tidak Diketahui";
$id_sekolah_tampil = $id_sekolah;

// [KEAMANAN BAJA]: 1. Prepared Statement untuk mencari id_sekolah di tabel users
$stmt_sekolah = mysqli_prepare($conn, "SELECT id_sekolah FROM users WHERE id_sekolah = ? LIMIT 1");
if ($stmt_sekolah) {
    mysqli_stmt_bind_param($stmt_sekolah, "s", $id_sekolah);
    mysqli_stmt_execute($stmt_sekolah);
    $qSekolah = mysqli_stmt_get_result($stmt_sekolah);

    if ($qSekolah && $dataSekolah = mysqli_fetch_assoc($qSekolah)) {
        $id_sekolah_tampil = $dataSekolah['id_sekolah'] ?? $id_sekolah;
    }
    mysqli_stmt_close($stmt_sekolah);
}

// [KEAMANAN BAJA]: 2. Prepared Statement untuk mencari data di tabel kode_sekolah
$query_master_sekolah = "SELECT * FROM `kode_sekolah` WHERE `id_sekolah` = ? OR `id` = ? LIMIT 1";
$stmt_master = mysqli_prepare($conn, $query_master_sekolah);
if ($stmt_master) {
    mysqli_stmt_bind_param($stmt_master, "ss", $id_sekolah_tampil, $id_sekolah_tampil);
    mysqli_stmt_execute($stmt_master);
    $result_master = mysqli_stmt_get_result($stmt_master);

    if ($result_master && mysqli_num_rows($result_master) > 0) {
        $row_master = mysqli_fetch_assoc($result_master);
        $satuan_pendidikan_tampil = $row_master['nama_sekolah'] ?? ($row_master['satuan_pendidikan'] ?? 'Sekolah Tidak Diketahui');
    } else {
        // [KEAMANAN BAJA]: 3. Prepared Statement untuk fallback pencarian di tabel data_barang_acuan
        $query_sekolah_alt = "SELECT `satuan_pendidikan` FROM `data_barang_acuan` WHERE `id_sekolah` = ? LIMIT 1";
        $stmt_alt = mysqli_prepare($conn, $query_sekolah_alt);
        if ($stmt_alt) {
            mysqli_stmt_bind_param($stmt_alt, "s", $id_sekolah_tampil);
            mysqli_stmt_execute($stmt_alt);
            $result_sekolah_alt = mysqli_stmt_get_result($stmt_alt);
            
            if ($result_sekolah_alt && mysqli_num_rows($result_sekolah_alt) > 0) {
                $row_sekolah_alt = mysqli_fetch_assoc($result_sekolah_alt);
                $satuan_pendidikan_tampil = $row_sekolah_alt['satuan_pendidikan'];
            }
            mysqli_stmt_close($stmt_alt);
        }
    }
    mysqli_stmt_close($stmt_master);
}

// [KEAMANAN BAJA]: 4. Proteksi XSS memastikan nama sekolah yang ditampung aman jika di-echo di tempat lain
$satuan_pendidikan_tampil = htmlspecialchars($satuan_pendidikan_tampil, ENT_QUOTES, 'UTF-8');
$id_sekolah_tampil = htmlspecialchars($id_sekolah_tampil, ENT_QUOTES, 'UTF-8');

// Daftar bulan untuk droplist
$daftar_bulan = [
    1 => "Januari", 2 => "Februari", 3 => "Maret", 4 => "April",
    5 => "Mei", 6 => "Juni", 7 => "Juli", 8 => "Agustus",
    9 => "September", 10 => "Oktober", 11 => "November", 12 => "Desember"
];

// Default bulan berjalan saat ini (angka 1-12) dari server
$bulan_sekarang = (int)date('n');
?>

<div class="d-flex align-items-center justify-content-center" style="min-height: calc(100vh - 200px);">
    <div class="col-12 col-sm-10 col-md-5 col-lg-4">
        <div class="card card-box p-4 shadow-sm" style="border-radius: 16px;">
            <h6 class="fw-bold text-dark mb-1">
                <i class="bi bi-calendar3 text-primary me-2"></i>Periode Anggaran
            </h6>
            <p class="text-secondary mb-4" style="font-size: 13px; line-height: 1.4;">
                Silakan tentukan bulan perolehan realisasi belanja modal sekolah Anda.
            </p>
            
            <form id="formPilihBulan" onsubmit="handlePulan(event)">
                <div class="mb-3">
                    <label for="bulan_realisasi" class="form-label fw-bold text-secondary text-uppercase mb-1" style="font-size: 11px; letter-spacing: 0.5px;">Pilih Bulan</label>
                    <select class="form-select" name="bulan_realisasi" id="bulan_realisasi" style="border-radius: 10px; font-size: 14px; padding: 10px 15px;" required>
                        <option value="" disabled>-- Pilih Bulan Realisasi --</option>
                        <?php foreach ($daftar_bulan as $angka => $nama): ?>
                            <option value="<?= (int)$angka; ?>"><?= htmlspecialchars($nama, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2" style="border-radius: 10px; padding: 10px; font-size: 14px; font-weight: 600;">
                    <span>Buka Acuan</span>
                    <i class="bi bi-arrow-right-short fs-5"></i>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    // Jalankan kode ini langsung saat elemen dirender oleh aplikasi
    (function() {
        const selectBulan = document.getElementById('bulan_realisasi');
        if (!selectBulan) return;

        // 1. Cek apakah ada riwayat bulan yang tersimpan di browser
        const bulanTersimpan = localStorage.getItem('pilihan_bulan_user');
        const valInt = parseInt(bulanTersimpan, 10);
        
        // Validasi ketat agar nilai tersimpan strictly berupa angka 1-12
        if (!isNaN(valInt) && valInt >= 1 && valInt <= 12) {
            selectBulan.value = valInt.toString();
        } else {
            // Jika baru pertama kali buka (belum ada history), arahkan ke bulan sekarang dari PHP
            selectBulan.value = "<?= (int)$bulan_sekarang; ?>";
        }
    })();

    function handlePulan(event) {
        event.preventDefault();
        const selectBulan = document.getElementById('bulan_realisasi');
        if (!selectBulan) return;

        const rawBulan = selectBulan.value;
        const bulanInt = parseInt(rawBulan, 10);

        // Validasi angka bulan valid (1-12) sebelum memproses URL
        if(!isNaN(bulanInt) && bulanInt >= 1 && bulanInt <= 12) {
            const bulanStr = bulanInt.toString();

            // FITUR UTAMA: Simpan bulan yang dipilih ke dalam browser sebelum pindah halaman
            localStorage.setItem('pilihan_bulan_user', bulanStr);

            const targetUrl = 'input_realisasi.php?bulan_realisasi=' + encodeURIComponent(bulanStr);
            if (typeof loadPage === "function") {
                loadPage(targetUrl, 'Input Realisasi');
            } else {
                window.location.href = 'index.php?p=' + targetUrl;
            }
        }
    }
</script>