<?php
// === KEAMANAN LAPIS BAJA: PENGATURAN SESI KETAT ===
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Strict');

    // Otomatis aktifkan cookie secure jika HTTPS terdeteksi
    $isHttps = (
        (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === '1')) ||
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
    );
    if ($isHttps) {
        ini_set('session.cookie_secure', 1);
    }

    session_start();
}

// Session Fixation dicegah saat login (session_regenerate_id di login.php).
// Regenerate per-load DILARANG: use_strict_mode=1 + AJAX concurrent (cek_progres_unduh.php poll,
// proses_unduh_bm.php) bikin race -> cookie lama jadi stale -> PHP issue session kosong baru ->
// Set-Cookie AJAX nge-timpa cookie asli -> bounce balik ke login. Baca root cause login-bounce sebelum menambah regenerate.

// === KEAMANAN LAPIS BAJA: HTTP SECURITY HEADERS ===
header("X-Frame-Options: DENY"); // Mencegah Clickjacking
header("X-XSS-Protection: 1; mode=block"); // Filter XSS browser
header("X-Content-Type-Options: nosniff"); // Mencegah MIME-sniffing
header("Referrer-Policy: strict-origin-when-cross-origin"); // Mencegah kebocoran URL referer
header("Permissions-Policy: geolocation=(), microphone=(), camera=()"); // Batasi API browser
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload"); // Paksa SSL/TLS
header("X-Permitted-Cross-Domain-Policies: none");

// Content Security Policy (CSP) - Mengamankan inline script & CDN resmi yang digunakan
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://static.cloudflareinsights.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; font-src 'self' https://cdn.jsdelivr.net; img-src 'self' data:; connect-src 'self' https://cdn.jsdelivr.net https://static.cloudflareinsights.com; frame-ancestors 'none';");

// Proteksi file halaman utama jika belum login
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    http_response_code(403);
    die("Akses ditolak. Silakan login terlebih dahulu.");
}

// === PROTEKSI HAK AKSES KHUSUS ADMIN ===
$role_user = isset($_SESSION['role']) ? $_SESSION['role'] : '';
if ($role_user !== 'admin') {
    header("Location: index.php");
    exit;
}

// === KEAMANAN LAPIS BAJA: GENERATE CSRF TOKEN KRIPTOGRAFIIS ===
if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || strlen($_SESSION['csrf_token']) !== 64) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Helper Sanitasi Output XSS Standard
function e($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

@include "koneksi.php";

// Fetch data tahun secara aman dari database
$qTahunOtomatis = false;
if (isset($conn) && $conn instanceof mysqli) {
    try {
        $qTahunOtomatis = mysqli_query($conn, "SELECT DISTINCT YEAR(`ba_tgl`) as thn FROM `realisasi_barang_sekolah` WHERE `ba_tgl` IS NOT NULL ORDER BY thn DESC");
    } catch (Throwable $t) {
        error_log("Database Query Error: " . $t->getMessage());
        $qTahunOtomatis = false;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="logolog.jpeg">
    <title>Cetak Rekap Belanja Modal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        :root {
            --excel-blue-border: #cbd5e1;
            --excel-green-main: #107c41;
            --excel-green-hover: #0f6f3a;
        }

        .excel-theme-card {
            background: #ffffff;
            border: 1px solid var(--excel-blue-border);
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            font-family: 'Segoe UI', -apple-system, sans-serif;
        }
        
        .excel-theme-header {
            background: linear-gradient(135deg, #1e293b, #334155);
            color: #ffffff;
            padding: 24px;
            border-bottom: 3px solid var(--excel-green-main);
        }
        
        .excel-title-main {
            font-size: 16px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-label-excel {
            font-size: 12px;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
        }

        .form-select-excel {
            width: 100% !important;
            border: 1px solid var(--excel-blue-border) !important;
            border-radius: 8px !important;
            padding: 12px 16px !important;
            background-color: #f8fafc !important;
            color: #1e293b !important;
            font-size: 14px !important;
        }

        .btn-excel-download {
            background-color: var(--excel-green-main) !important;
            border-color: var(--excel-green-main) !important;
            color: #ffffff !important;
            padding: 12px 30px !important;
            font-weight: 600 !important;
            border-radius: 8px !important;
            transition: all 0.2s ease-in-out !important;
        }

        .btn-excel-download:hover {
            background-color: var(--excel-green-hover) !important;
            transform: translateY(-1px);
        }

        /* OVERLAY TIRAI HITAM MODAL KUSTOM */
        .loading-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 99999;
        }

        .loading-box {
            background: #ffffff;
            padding: 40px;
            border-radius: 16px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            max-width: 420px;
            width: 90%;
        }

        .spinner-circle {
            position: relative;
            width: 130px; height: 130px;
            margin: 0 auto 24px;
        }

        .spinner-svg {
            width: 130px; height: 130px;
            transform: rotate(-90deg);
        }

        .spinner-svg circle {
            fill: none;
            stroke-width: 6;
        }

        .circle-bg { stroke: #e2e8f0; }
        
        .circle-progress {
            stroke: var(--excel-green-main);
            stroke-linecap: round;
            stroke-dasharray: 283;
            stroke-dashoffset: 283;
            transition: stroke-dashoffset 0.25s ease;
        }

        .percent-text {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            font-size: 15px;
            font-weight: 700;
            color: #1e293b;
            text-align: center;
            line-height: 1.2;
        }

        .percent-text span {
            display: block;
            font-size: 11px;
            color: #64748b;
            font-weight: 500;
            margin-top: 2px;
        }
    </style>
</head>
<body class="bg-light">

<div id="excelLoadingOverlay" class="loading-overlay">
    <div class="loading-box">
        <div class="spinner-circle">
            <svg class="spinner-svg">
                <circle class="circle-bg" cx="65" cy="65" r="45"></circle>
                <circle id="progressCircle" class="circle-progress" cx="65" cy="65" r="45"></circle>
            </svg>
            <div id="percentNumber" class="percent-text">0% <span id="barisNumber">0 Baris</span></div>
        </div>
        <h5 id="loaderTitle" style="font-weight:700; color:#1e293b; margin-top: 0; margin-bottom: 8px;">Sedang Memproses Data...</h5>
        <div id="loaderContentBody">
            <p style="font-size:13px; color:#64748b; line-height: 1.6; margin: 0;">Server sedang membaca basis data dan menyusun baris laporan belanja modal Anda ke format Excel (.xlsx).</p>
        </div>
    </div>
</div>

<div class="container-fluid px-4 my-4">
    <div class="card excel-theme-card">
        <div class="excel-theme-header">
            <div class="excel-title-main"><i class="bi bi-file-earmark-excel me-2"></i> Ekspor Laporan Belanja Modal</div>
        </div>

        <div class="card-body p-4 p-md-5 bg-white">
            <form id="formCetakExcel" onsubmit="return false;">
                <!-- KEAMANAN LAPIS BAJA: INPUT HIDDEN CSRF TOKEN -->
                <input type="hidden" id="csrf_token" name="csrf_token" value="<?= e($csrf_token); ?>">
                
                <div class="row g-4">
                    <div class="col-12 col-md-6">
                        <label class="form-label-excel">Pilih Bulan Realisasi <span class="text-danger">*</span></label>
                        <select name="bulan" id="pilih_bulan" class="form-select form-select-excel" required>
                            <option value="">-- Pilih Bulan --</option>
                            <option value="1">Januari</option><option value="2">Februari</option>
                            <option value="3">Maret</option><option value="4">April</option>
                            <option value="5">Mei</option><option value="6">Juni</option>
                            <option value="7">Juli</option><option value="8">Agustus</option>
                            <option value="9">September</option><option value="10">Oktober</option>
                            <option value="11">November</option><option value="12">Desember</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label-excel">Pilih Tahun Anggaran <span class="text-danger">*</span></label>
                        <select name="tahun" id="pilih_tahun" class="form-select form-select-excel" required>
                            <option value="">-- Pilih Tahun --</option>
                            <?php
                            if ($qTahunOtomatis && mysqli_num_rows($qTahunOtomatis) > 0) {
                                while ($t = mysqli_fetch_assoc($qTahunOtomatis)) {
                                    $tahun_aman = e($t['thn']);
                                    echo "<option value='{$tahun_aman}'>{$tahun_aman}</option>";
                                }
                            } else {
                                $tahun_sekarang = e(date('Y'));
                                echo "<option value='{$tahun_sekarang}'>{$tahun_sekarang}</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="col-md-12 text-end mt-5 pt-4 border-top">
                        <button type="button" onclick="jalankanProsesUnduh()" class="btn btn-excel-download shadow-sm">
                            <i class="bi bi-download me-2"></i>Unduh Berkas Rekap (.xlsx)
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Helper JS untuk Mencegah DOM-Based XSS saat merender teks ke HTML
    function escapeHTML(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/[&<>"']/g, function(match) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[match];
        });
    }

    function jalankanProsesUnduh() {
        const overlay = document.getElementById('excelLoadingOverlay');
        const percentNumber = document.getElementById('percentNumber');
        const progressCircle = document.getElementById('progressCircle');
        
        const loaderTitle = document.getElementById('loaderTitle');
        const loaderContentBody = document.getElementById('loaderContentBody');
        const spinnerCircleBox = overlay.querySelector('.spinner-circle');

        const bulanSelect = document.getElementById('pilih_bulan');
        const tahunSelect = document.getElementById('pilih_tahun');
        const csrfToken = document.getElementById('csrf_token').value;

        if (bulanSelect.value === "" || tahunSelect.value === "") {
            alert("Silakan pilih Bulan dan Tahun terlebih dahulu!");
            return;
        }

        // Validasi tipe angka ketat di Sisi Klien
        const bulanVal = parseInt(bulanSelect.value, 10);
        const tahunVal = parseInt(tahunSelect.value, 10);
        if (isNaN(bulanVal) || bulanVal < 1 || bulanVal > 12 || isNaN(tahunVal) || tahunVal < 2000 || tahunVal > 2100) {
            alert("Pilihan bulan atau tahun tidak valid!");
            return;
        }

        // Sanitasi parameter di frontend sebelum dikirim
        const bulan = encodeURIComponent(bulanSelect.value);
        const tahun = encodeURIComponent(tahunSelect.value);
        const progressRequest = (body = null) => fetch('cek_progres_unduh.php', {
            method: 'POST',
            headers: {'X-CSRF-Token': csrfToken},
            body
        });
        const namaBulan = bulanSelect.options[bulanSelect.selectedIndex].text;

        // --- LANGKAH 1: PRE-FLIGHT CHECK (Cek Ketersediaan Data dengan CSRF) ---
        progressRequest(new URLSearchParams({init: '1', bulan, tahun}))
        .then(res => {
            if (!res.ok) { throw new Error("BYPASS_TO_PROCESS"); }
            return res.json();
        })
        .then(initialData => {
            let totalBaris = parseInt(initialData.total_rows, 10) || 0;
            
            // JIKA DATABASE KOSONG -> TAMPILKAN MODAL NOTIFIKASI
            if (totalBaris === 0) {
                spinnerCircleBox.style.display = 'none'; 
                loaderTitle.innerText = "DATA TIDAK DITEMUKAN";
                loaderTitle.style.color = "#dc3545"; 
                loaderContentBody.innerHTML = `
                    <p style="font-size:14px; color:#475569; line-height: 1.6; margin-bottom: 20px;">
                        Maaf, pada periode bulan <strong>${escapeHTML(namaBulan)} ${escapeHTML(decodeURIComponent(tahun))}</strong> tidak ditemukan adanya catatan data realisasi belanja modal.
                    </p>
                    <button type="button" class="btn btn-secondary btn-sm w-100" style="border-radius:6px; font-weight:600; padding:8px;" onclick="document.getElementById('excelLoadingOverlay').style.display='none'">
                        Tutup Notifikasi
                    </button>
                `;
                overlay.style.display = 'flex'; 
                return; 
            }

            // JIKA DATA ADA -> JALANKAN PROSES DOWNLOAD
            mulaiOlahDanHitungPersen(bulan, tahun, namaBulan, totalBaris);
        })
        .catch(err => {
            console.warn("Gagal pre-check, dipaksa bypass ke pengolahan:", err);
            mulaiOlahDanHitungPersen(bulan, tahun, namaBulan, 100);
        });
    }

    // --- LANGKAH 2: ANIMASI PERSENTASE & FETCH GENERATE EXCEL ---
    function mulaiOlahDanHitungPersen(bulan, tahun, namaBulan, estimatedTotalRows) {
        const overlay = document.getElementById('excelLoadingOverlay');
        const percentNumber = document.getElementById('percentNumber');
        const progressCircle = document.getElementById('progressCircle');
        const loaderTitle = document.getElementById('loaderTitle');
        const loaderContentBody = document.getElementById('loaderContentBody');
        const spinnerCircleBox = overlay.querySelector('.spinner-circle');

        spinnerCircleBox.style.display = 'block';
        percentNumber.innerHTML = '0% <span id="barisNumber">0 Baris</span>';
        loaderTitle.innerText = "Sedang Memproses Data...";
        loaderTitle.style.color = "#1e293b";
        loaderContentBody.innerHTML = '<p style="font-size:13px; color:#64748b; line-height: 1.6; margin: 0;">Server sedang membaca basis data dan menyusun baris laporan belanja modal Anda ke format Excel (.xlsx).</p>';
        
        overlay.style.display = 'flex'; 

        const circleRadius = 45;
        const circumference = 2 * Math.PI * circleRadius;
        progressCircle.style.strokeDashoffset = circumference;

        // Polling Progress Baris
        let pollingInterval = setInterval(function() {
            progressRequest()
            .then(response => response.json())
            .then(data => {
                let countData = parseInt(data.progress, 10) || 0;
                let percentCalculated = Math.round((countData / estimatedTotalRows) * 100);
                
                if (percentCalculated > 99) percentCalculated = 99;
                if (percentCalculated < 0) percentCalculated = 0;

                const offset = circumference - (percentCalculated / 100) * circumference;
                progressCircle.style.strokeDashoffset = offset;
                percentNumber.innerHTML = percentCalculated + '% <span id="barisNumber">' + countData + ' Baris</span>';
            })
            .catch(err => console.error(err));
        }, 250);

        // Buat FormData membawa input hidden 'csrf_token'
        const formEl = document.getElementById('formCetakExcel');
        const formData = new FormData(formEl);

        fetch('proses_unduh_bm.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            const contentType = response.headers.get("content-type");
            if (contentType && contentType.indexOf("application/json") !== -1) {
                return response.json().then(jsonResult => {
                    if (jsonResult.status === 'empty') {
                        throw new Error("EMPTY_DATA_FROM_PROCESS");
                    }
                    throw new Error("SERVER_ERROR");
                });
            }
            if (!response.ok) throw new Error("SERVER_ERROR");
            return response.blob();
        })
        .then(blob => {
            clearInterval(pollingInterval);
            progressCircle.style.strokeDashoffset = 0;
            percentNumber.innerHTML = '100% <span id="barisNumber">Selesai!</span>';

            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.style.display = 'none';
            a.href = url;
            a.download = "Daftar_Pengadaan_Belanja_Modal_Bulan_" + decodeURIComponent(bulan) + "_" + decodeURIComponent(tahun) + ".xlsx";
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);

            setTimeout(function() { overlay.style.display = 'none'; }, 1000);
        })
        .catch(error => {
            clearInterval(pollingInterval);
            if (error.message === "EMPTY_DATA_FROM_PROCESS") {
                spinnerCircleBox.style.display = 'none'; 
                loaderTitle.innerText = "DATA TIDAK DITEMUKAN";
                loaderTitle.style.color = "#dc3545"; 
                loaderContentBody.innerHTML = `
                    <p style="font-size:14px; color:#475569; line-height: 1.6; margin-bottom: 20px;">
                        Maaf, pada periode bulan <strong>${escapeHTML(namaBulan)} ${escapeHTML(decodeURIComponent(tahun))}</strong> tidak ditemukan adanya catatan data realisasi belanja modal.
                    </p>
                    <button type="button" class="btn btn-secondary btn-sm w-100" style="border-radius:6px; font-weight:600; padding:8px;" onclick="document.getElementById('excelLoadingOverlay').style.display='none'">
                        Tutup Notifikasi
                    </button>
                `;
            } else {
                overlay.style.display = 'none';
                alert("Terjadi masalah teknis pada server saat memproses file Excel.");
            }
            console.error(error);
        });
    }
</script>

</body>
</html>
