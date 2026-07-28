<?php
// KEAMANAN 1: Pengaturan Sesi & Proteksi Cookie
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Strict');
    if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === '1')) {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

// KEAMANAN 2: Proteksi Security Headers (Anti-Clickjacking, Sniffing, & XSS Protection)
if (!headers_sent()) {
    header("X-Frame-Options: SAMEORIGIN");
    header("X-Content-Type-Options: nosniff");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
}

// KEAMANAN 3: Validasi Sesi Ketat & Anti Session Hijacking (Integrasi dengan login.php)
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';

// Cek autentikasi utama dan role pengguna
if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'user') {
    session_unset();
    session_destroy();
    if (!headers_sent()) {
        header("Location: login.php");
    } else {
        echo "<script>window.location.replace('login.php');</script>";
    }
    exit; // Wajib: Menghentikan eksekusi script agar HTML di bawahnya tidak bocor
}

// Verifikasi integritas peramban/browser (Kunci sesi ke User-Agent)
if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== md5($user_agent)) {
    session_unset();
    session_destroy();
    if (!headers_sent()) {
        header("Location: login.php");
    } else {
        echo "<script>window.location.replace('login.php');</script>";
    }
    exit;
}

$nama_bulan = [
    1 => "Januari", 2 => "Februari", 3 => "Maret", 4 => "April", 
    5 => "Mei", 6 => "Juni", 7 => "Juli", 8 => "Agustus", 
    9 => "September", 10 => "Oktober", 11 => "November", 12 => "Desember"
];

// KEAMANAN 4: Type Casting & Range Bounds Validation (Memaksa rentang nilai valid 1 - 12)
$bulan_sekarang = isset($_SESSION['bulan_aktif_spj']) ? (int)$_SESSION['bulan_aktif_spj'] : (int)date('n');
if ($bulan_sekarang < 1 || $bulan_sekarang > 12) {
    $bulan_sekarang = (int)date('n'); // Fallback otomatis ke bulan berjalan jika data sesi dimanipulasi
}
?>

<div class="d-flex align-items-center justify-content-center" style="min-height: calc(100vh - 200px);">
    <div class="col-12 col-sm-10 col-md-5 col-lg-4">
        <div class="card card-box p-4 shadow-sm" style="border-radius: 16px; background: #fff;">
            <h6 class="fw-bold text-dark mb-1">
                <i class="bi bi-calendar3 text-primary me-2"></i>Periode Anggaran
            </h6>
            <p class="text-secondary mb-4" style="font-size: 13px; line-height: 1.4;">
                Silakan tentukan periode pengerjaan SPJ data barang terlebih dahulu untuk membuka lembar katalog utama.
            </p>
            
            <form action="index.php" method="GET">
                <input type="hidden" name="p" value="data_barang.php">
                
                <div class="mb-3">
                    <label class="form-label fw-bold text-secondary text-uppercase mb-1" style="font-size: 11px; letter-spacing: 0.5px;">Pilih Bulan</label>
                    <select name="bulan_realisasi" class="form-select" style="border-radius: 10px; font-size: 14px; padding: 10px 15px;" required>
                        <?php foreach ($nama_bulan as $num => $name): ?>
                            <option value="<?= htmlspecialchars((string)$num, ENT_QUOTES, 'UTF-8'); ?>" <?= ($num === $bulan_sekarang) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2" style="border-radius: 10px; padding: 10px; font-size: 14px; font-weight: 600;">
                    <span>Buka Manajemen Barang</span>
                    <i class="bi bi-arrow-right-short fs-5"></i>
                </button>
            </form>
        </div>
    </div>
</div>