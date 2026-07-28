<?php
/**
 * HALAMAN PEMILIHAN BULAN - KONTEN INTERNAL AJAX
 * (Dipanggil asinkronus di dalam index_admin.php)
 */

// === KEAMANAN TAMBAHAN 1: Proteksi Session & Cookie Flags ===
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === '1')) {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
}

// === KEAMANAN TAMBAHAN 2: Validasi Sesi & Hak Akses (Role Check) ===
if (!isset($_SESSION['login']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    exit('<div class="alert alert-danger text-center m-4" style="border-radius:12px;"><i class="bi bi-shield-lock-fill me-2"></i><strong>Akses Ditolak:</strong> Sesi tidak valid atau Anda tidak memiliki hak akses admin.</div>');
}

// === KEAMANAN TAMBAHAN 3: Proteksi Header HTTP Lengkap ===
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("X-Permitted-Cross-Domain-Policies: none");

// === KEAMANAN TAMBAHAN 4: Generasi CSRF Token ===
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8');
?>
<div class="container-fluid py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card p-4 border-0 shadow-sm" style="border-radius: 20px; background: #ffffff;">
                <div class="text-center mb-4">
                    <div class="p-3 bg-light text-primary d-inline-block rounded-circle mb-2" style="font-size: 24px; width: 60px; height: 60px; display: inline-flex; align-items: center; justify-content: center;">
                        <i class="bi bi-calendar-check-fill"></i>
                    </div>
                    <h5 class="fw-bold text-dark m-0">Sistem Kendali Realisasi</h5>
                    <p class="text-secondary small">Silakan tentukan target Bulan Acuan terlebih dahulu.</p>
                </div>
                
                <form id="formPilihBulanAdmin" autocomplete="off">
                    <!-- Token Proteksi CSRF -->
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted mb-2">PILIH BULAN ACUAN REALISASI</label>
                        <select name="bulan_target" id="bulan_target_select" class="form-select" style="border-radius: 10px; padding: 12px; font-weight: 600;" required>
                            <option value="" disabled selected>-- Pilih Bulan Kerja --</option>
                            <option value="1">Januari</option>
                            <option value="2">Februari</option>
                            <option value="3">Maret</option>
                            <option value="4">April</option>
                            <option value="5">Mei</option>
                            <option value="6">Juni</option>
                            <option value="7">Juli</option>
                            <option value="8">Agustus</option>
                            <option value="9">September</option>
                            <option value="10">Oktober</option>
                            <option value="11">November</option>
                            <option value="12">Desember</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 fw-bold py-2" style="border-radius:12px;">
                        Buka Kendali <i class="bi bi-arrow-right ms-2"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    // Menangani event submit form secara lokal namun terintegrasi dengan index_admin.php
    const formAdmin = document.getElementById('formPilihBulanAdmin');
    if (!formAdmin) return;

    formAdmin.addEventListener('submit', function(e) {
        e.preventDefault(); // Cegah reload halaman penuh (memicu layar putih polos)
        
        const selectBulan = document.getElementById('bulan_target_select');
        if (!selectBulan) return;

        // Ambil value dari form
        let rawValue = selectBulan.value;
        
        // === KEAMANAN TAMBAHAN: Validasi Strict Data Type & Strict Integer Bounds ===
        // Pastikan input benar-benar angka bulat (integer) 1-12. Cegah manipulasi DOM/Inspect Element.
        let bulanDipilih = parseInt(rawValue, 10);
        
        if (isNaN(bulanDipilih) || !Number.isInteger(bulanDipilih) || bulanDipilih < 1 || bulanDipilih > 12) {
            alert("🔒 Peringatan Keamanan: Terdeteksi manipulasi data bulan!");
            return false;
        }
        
        if (bulanDipilih) {
            // Encode URI untuk memastikan query string aman dari XSS/Injeksi
            const safeBulan = encodeURIComponent(bulanDipilih);
            const urlTarget = 'rekapan_admin.php?bulan_target=' + safeBulan;
            const pageTitle = 'Rekapan Satuan Pendidikan';
            
            // Panggil fungsi loadPage global yang sudah kita buat menempel di objek window pada index_admin.php
            if (typeof window.loadPage === 'function') {
                window.loadPage(urlTarget, pageTitle);
            } else if (typeof loadPage === 'function') {
                loadPage(urlTarget, pageTitle);
            } else {
                // Fallback cadangan jika sistem SPA mendadak tidak terbaca
                console.error("Fungsi loadPage tidak ditemukan pada struktur utama index_admin.php");
                // Pengamanan tambahan encode URI di fallback
                window.location.href = 'index_admin.php?p=' + encodeURIComponent(urlTarget);
            }
        }
    });
})();
</script>