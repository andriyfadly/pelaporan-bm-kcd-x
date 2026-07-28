<?php
// KEAMANAN 1: Matikan pelacak error di layar, tapi tetap catat ke log server
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// KEAMANAN 2: Proteksi Session & Security Headers
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');

if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === '1')) {
    ini_set('session.cookie_secure', 1); 
}

header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// KEAMANAN 3: Validasi Sesi Login Kredensial
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    header("Location: login.php");
    exit;
}

// KEAMANAN 4: Proteksi Anti Session Hijacking
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
if (!isset($_SESSION['user_agent'])) {
    $_SESSION['user_agent'] = md5($user_agent);
} elseif ($_SESSION['user_agent'] !== md5($user_agent)) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}

// KEAMANAN 5: Session Fixation sudah dicegah saat login (session_regenerate_id di login.php).
// Regenerate ID per-load DILARANG di sini: use_strict_mode=1 + AJAX concurrent bikin cookie
// lama jadi stale -> PHP issue session kosong baru -> Set-Cookie nya nge-timpa cookie asli
// saat AJAX response datang belakangan -> bounce balik ke login (race condition).
// Lihat catatan root cause login-bounce sebelum menambah lagi regenerate di sini.

// KEAMANAN 6: Proteksi Hak Akses Khusus Admin (Redirect ke index.php jika bukan admin)
$role_user = $_SESSION['role'] ?? '';
if ($role_user !== 'admin') {
    header("Location: index.php");
    exit;
}

if (file_exists("koneksi.php")) {
    include "koneksi.php";
} else {
    die("Error: File 'koneksi.php' tidak ditemukan.");
}

$id_sekolah = $_SESSION['id_sekolah'] ?? '';

// ================= DATA SEKOLAH USER LOGGED IN =================
$dataSekolah = null;
$qSekolah_sql = "SELECT id FROM users WHERE id_sekolah = ? LIMIT 1";

if ($stmt = mysqli_prepare($conn, $qSekolah_sql)) {
    mysqli_stmt_bind_param($stmt, "s", $id_sekolah);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res && $row = mysqli_fetch_assoc($res)) {
        $dataSekolah = $row;
    }
    mysqli_stmt_close($stmt);
}

$id_sekolah_tampil = $dataSekolah['id'] ?? $id_sekolah;
$id_sekolah_aman = htmlspecialchars($id_sekolah_tampil, ENT_QUOTES, 'UTF-8');

// ================= LOGIKA DASHBOARD MONITORING DENGAN ACUAN BULAN =================
$filter_bulan = filter_input(INPUT_GET, 'bulan', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 12]
]) ?: (int)date('n');

$filter_tahun = filter_input(INPUT_GET, 'tahun', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 2000, 'max_range' => 2100]
]) ?: (int)date('Y');

$nama_bulan = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

// 1. Ambil Sekolah yang PUNYA ACUAN di bulan & tahun terpilih (Tabel Target)
$list_sekolah_acuan = [];
$q_acuan = "SELECT DISTINCT CAST(a.id_sekolah AS CHAR) AS id_sch, k.nama_sekolah 
            FROM data_barang_acuan a
            JOIN kode_sekolah k ON k.id = a.id_sekolah
            WHERE a.bulan = ?
              AND (YEAR(a.created_at) = ? OR (a.tanggal IS NOT NULL AND YEAR(a.tanggal) = ?))
            ORDER BY k.nama_sekolah ASC";

if ($stmt_acuan = mysqli_prepare($conn, $q_acuan)) {
    mysqli_stmt_bind_param($stmt_acuan, "iii", $filter_bulan, $filter_tahun, $filter_tahun);
    mysqli_stmt_execute($stmt_acuan);
    $res_acuan = mysqli_stmt_get_result($stmt_acuan);
    while ($r = mysqli_fetch_assoc($res_acuan)) {
        $list_sekolah_acuan[(string)$r['id_sch']] = $r['nama_sekolah'];
    }
    mysqli_stmt_close($stmt_acuan);
}

// 2. Ambil Sekolah yang SUDAH REALISASI di bulan & tahun terpilih
$sekolah_selesai_ids = [];
$q_realisasi_selesai = "SELECT DISTINCT CAST(r.id_sekolah AS CHAR) AS id_sch 
                        FROM realisasi_barang_sekolah r 
                        WHERE r.bulan_realisasi = ? 
                          AND (YEAR(r.ba_tgl) = ? OR YEAR(r.created_at) = ?)
                          AND r.is_realisasi = 1";

if ($stmt_real = mysqli_prepare($conn, $q_realisasi_selesai)) {
    mysqli_stmt_bind_param($stmt_real, "iii", $filter_bulan, $filter_tahun, $filter_tahun);
    mysqli_stmt_execute($stmt_real);
    $res_real = mysqli_stmt_get_result($stmt_real);
    while ($r = mysqli_fetch_assoc($res_real)) {
        $sekolah_selesai_ids[] = (string)$r['id_sch'];
    }
    mysqli_stmt_close($stmt_real);
}

// 3. Evaluasi Sekolah Berdasarkan Daftar Acuan Bulan Terpilih
$list_selesai = [];
$list_belum = [];

foreach ($list_sekolah_acuan as $id_sch => $nama_sch) {
    if (in_array((string)$id_sch, $sekolah_selesai_ids, true)) {
        $list_selesai[] = ['id' => $id_sch, 'nama' => $nama_sch];
    } else {
        $list_belum[] = ['id' => $id_sch, 'nama' => $nama_sch];
    }
}

$total_sekolah = count($list_sekolah_acuan); // Total Target Sekolah di bulan ini
$total_selesai = count($list_selesai);
$total_belum = count($list_belum);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventaris Barang | Dashboard</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #2563eb;
            --secondary: #64748b;
            --dark: #0f172a;
            --sidebar-width: 280px;
            --sidebar-collapsed: 80px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --bg-body: #f8fafc;
            --bg-card: #ffffff;
            --border-color: #e2e8f0;
            --text-main: #0f172a;
        }

        * { font-family: 'Plus Jakarta Sans', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }
        body { background: var(--bg-body); color: var(--text-main); overflow-x: hidden; }

        .sidebar .brand { 
            padding: 10px 5px; /* Kurangi padding atas-bawah agar space gambar lebih luas */
            display: flex; 
            align-items: center; 
            justify-content: center;
            text-align: center;
            min-height: 120px;   /* Sesuaikan tinggi area brand jika perlu */
        }

        .brand-img {
            max-width: 100%;
            height: auto;
            max-height: 200px;   /* UBAH INI: Dari 75px dinaikkan ke 130px (atau sesuai selera) */
            object-fit: contain;
            transition: var(--transition);
        }
        body.collapsed .brand-img {
            max-height: 45px;
        }

        .sidebar {
            width: var(--sidebar-width); height: 100vh; position: fixed; top: 0; left: 0;
            background: var(--bg-card); border-right: 1px solid var(--border-color); z-index: 1000;
            transition: var(--transition); display: flex; flex-direction: column;
        }
        body.collapsed .sidebar { width: var(--sidebar-collapsed); }

        .nav-wrapper { padding: 10px 15px; flex-grow: 1; overflow-y: auto; overflow-x: hidden; }
        .menu-label { font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; padding: 15px 15px 8px 15px; letter-spacing: 0.5px; }
        body.collapsed .menu-label { opacity: 0; }

        .menu-btn {
            width: 100%; border: none; background: transparent; padding: 12px 15px; border-radius: 14px;
            display: flex; align-items: center; gap: 12px; color: var(--secondary); font-weight: 600;
            font-size: 14px; margin-bottom: 5px; transition: var(--transition); text-decoration: none; white-space: nowrap; cursor: pointer;
        }
        .menu-btn i { font-size: 18px; min-width: 24px; }
        .menu-btn:hover { background: rgba(37, 99, 235, 0.04); color: var(--primary); }
        .menu-btn.active { background: #eff6ff; color: var(--primary); border-left: 4px solid var(--primary); border-radius: 0 14px 14px 0; }

        .submenu-list { padding-left: 20px; border-left: 1px solid var(--border-color); margin-left: 25px; margin-bottom: 10px; }
        .submenu-btn { padding: 8px 10px; font-size: 13.5px; color: var(--secondary); text-decoration: none; display: block; border-radius: 8px; cursor: pointer; transition: 0.2s; font-weight: 500; }
        .submenu-btn:hover { color: var(--primary); padding-left: 15px; background: rgba(37, 99, 235, 0.02); }
        .submenu-btn.active { color: var(--primary); font-weight: 700; }

        .header {
            position: fixed; top: 0; left: var(--sidebar-width); right: 0; height: 80px;
            background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(12px); border-bottom: 1px solid var(--border-color);
            display: flex; align-items: center; justify-content: space-between; padding: 0 40px;
            z-index: 900; transition: var(--transition);
        }
        body.collapsed .header { left: var(--sidebar-collapsed); }

        .content { margin-left: var(--sidebar-width); padding: 110px 40px 40px; transition: var(--transition); min-height: 100vh; }
        body.collapsed .content { margin-left: var(--sidebar-collapsed); }

        #loader { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 4px; background: transparent; z-index: 2000; }
        #loader div { width: 0; height: 100%; background: var(--primary); transition: 0.3s; box-shadow: 0 0 10px var(--primary); }

        .card-box { border: none; border-radius: 20px; background: var(--bg-card); box-shadow: 0 10px 25px rgba(148, 163, 184, 0.05); padding: 25px; transition: 0.3s; border: 1px solid rgba(241, 245, 249, 0.8); height: 100%; }
        .stat-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; margin-bottom: 15px; }
        
        .bg-total { background: #1e293b; color: #ffffff; }
        .bg-green { background: #ecfdf5; color: #10b981; }
        .bg-red { background: #fef2f2; color: #ef4444; }

        .stat-number-val { font-size: 38px; font-weight: 700; line-height: 1; margin-top: 5px; }

        .table-custom { border-collapse: separate; border-spacing: 0 8px; }
        .table-custom tbody tr { background: #ffffff; box-shadow: 0 2px 5px rgba(0,0,0,0.02); transition: 0.2s; }
        .table-custom tbody tr:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .table-custom td { padding: 12px 15px; vertical-align: middle; border: none; }
        .table-custom td:first-child { border-top-left-radius: 10px; border-bottom-left-radius: 10px; }
        .table-custom td:last-child { border-top-right-radius: 10px; border-bottom-right-radius: 10px; }

        @media (max-width: 992px) {
            .sidebar { left: -100%; }
            body.mobile-show .sidebar { left: 0; width: var(--sidebar-width); }
            .header, .content { left: 0 !important; margin-left: 0 !important; }
        }
    </style>
</head>
<body>

<div id="loader"><div></div></div>

<div class="sidebar" id="sidebar">
    <div class="brand">
        <img src="diptanew.jpeg" alt="SI DIPTA Beu!" class="brand-img img-fluid">
    </div>

    <div class="nav-wrapper">
        <div class="menu-label">Main Menu</div>
        
        <a href="index_admin.php" class="menu-btn ajax-link active" data-page="dashboard" data-title="Dashboard">
            <i class="bi bi-grid-1x2-fill"></i>
            <span>Dashboard</span>
        </a>
        
        <a href="#master" class="menu-btn" data-bs-toggle="collapse" id="btn-master">
            <i class="bi bi-database-fill"></i>
            <span>Master Data</span>
            <i class="bi bi-chevron-down ms-auto fs-6"></i>
        </a>
        <div class="collapse submenu-list" id="master">
            <a href="kode_barang.php" class="submenu-btn ajax-link" data-page="kode_barang.php" data-title="Kode Barang">Kode Barang</a>
            <a href="input_acuan.php" class="submenu-btn ajax-link" data-page="input_acuan.php" data-title="Input Acuan">Input Acuan</a>
            <a href="pilih_bulan_rekapan.php" class="submenu-btn ajax-link" data-page="pilih_bulan_rekapan.php" data-title="Rekapan Satuan Pendidikan">Data Kendali Realisasi</a>
        </div>

        <div class="menu-label">Reports & Tools</div>
        <a href="#penyaluran" class="menu-btn" data-bs-toggle="collapse" id="btn-penyaluran">
            <i class="bi bi-file-earmark-bar-graph-fill"></i>
            <span>Laporan</span>
            <i class="bi bi-chevron-down ms-auto fs-6"></i>
        </a>
        <div class="collapse submenu-list" id="penyaluran">
            <a href="cetak_bm.php" class="submenu-btn ajax-link" data-page="cetak_bm.php" data-title="Cetak Laporan">Cetak Laporan</a>
        </div>
    </div>

    <div class="p-3 border-top mt-auto">
        <button class="menu-btn text-danger border-0 w-100 bg-transparent" data-bs-toggle="modal" data-bs-target="#logoutModal">
            <i class="bi bi-box-arrow-left"></i>
            <span>Logout</span>
        </button>
    </div>
</div>

<div class="header">
    <div class="d-flex align-items-center gap-3">
        <div class="toggle-btn" id="sidebarToggle" style="cursor:pointer; padding:10px; background:#f1f5f9; border-radius:10px;">
            <i class="bi bi-text-indent-left fs-5"></i>
        </div>
        <div>
            <h6 class="mb-0 fw-bold" id="page-title">Dashboard</h6>
            <small class="text-secondary" style="font-weight: 600; text-transform: uppercase; font-size: 12px;"><?= $id_sekolah_aman; ?></small>
        </div>
    </div>
    <div class="d-flex align-items-center gap-3">
        <div id="time" class="fw-bold d-none d-lg-block me-3" style="font-size: 14px; color: #1e293b;"></div>
        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 40px; height: 40px;">
            <i class="bi bi-person-fill fs-5"></i>
        </div>
    </div>
</div>

<div class="content" id="main-content">
    <div id="ajax-container">
        <div id="dashboard-content">
            
            <!-- FILTER PERIODE BULAN DAN TAHUN -->
            <div class="card card-box mb-4">
                <form method="GET" action="index_admin.php" class="row g-3 align-items-center">
                    <div class="col-md-5">
                        <label class="form-label fw-bold text-secondary mb-1" style="font-size: 13px;"><i class="bi bi-calendar-event me-1"></i>Pilih Bulan Monitoring</label>
                        <select name="bulan" class="form-select fw-semibold" style="border-radius: 12px;">
                            <?php foreach ($nama_bulan as $num => $nama): ?>
                                <option value="<?= $num; ?>" <?= $num == $filter_bulan ? 'selected' : ''; ?>><?= $nama; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-secondary mb-1" style="font-size: 13px;"><i class="bi bi-calendar3 me-1"></i>Tahun</label>
                        <select name="tahun" class="form-select fw-semibold" style="border-radius: 12px;">
                            <?php 
                            $curr_y = (int)date('Y');
                            for ($y = $curr_y; $y >= $curr_y - 4; $y--): 
                            ?>
                                <option value="<?= $y; ?>" <?= $y == $filter_tahun ? 'selected' : ''; ?>><?= $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary fw-semibold w-100 py-2 mt-4" style="border-radius: 12px;">
                            <i class="bi bi-filter me-1"></i> Tampilkan Informasi
                        </button>
                    </div>
                </form>
            </div>

            <!-- CARDS RINGKASAN MONITORING -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card-box">
                        <div class="stat-icon bg-total">
                            <i class="bi bi-building"></i>
                        </div>
                        <span class="text-secondary fw-semibold fs-6">Target Sekolah (<?= $nama_bulan[$filter_bulan]; ?>)</span>
                        <div class="stat-number-val text-dark"><?= $total_sekolah; ?></div>
                        <small class="text-muted" style="font-size: 12px;">Memiliki data acuan di bulan ini</small>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card-box">
                        <div class="stat-icon bg-green">
                            <i class="bi bi-check-circle-fill"></i>
                        </div>
                        <span class="text-secondary fw-semibold fs-6">Sudah Selesai (<?= $nama_bulan[$filter_bulan]; ?>)</span>
                        <div class="stat-number-val text-success"><?= $total_selesai; ?></div>
                        <small class="text-success fw-semibold" style="font-size: 12px;">
                            <?= $total_sekolah > 0 ? round(($total_selesai / $total_sekolah) * 100, 1) : 0; ?>% Terlaporkan
                        </small>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card-box">
                        <div class="stat-icon bg-red">
                            <i class="bi bi-x-circle-fill"></i>
                        </div>
                        <span class="text-secondary fw-semibold fs-6">Belum Selesai (<?= $nama_bulan[$filter_bulan]; ?>)</span>
                        <div class="stat-number-val text-danger"><?= $total_belum; ?></div>
                        <small class="text-danger fw-semibold" style="font-size: 12px;">
                            <?= $total_sekolah > 0 ? round(($total_belum / $total_sekolah) * 100, 1) : 0; ?>% Belum Realisasi
                        </small>
                    </div>
                </div>
            </div>

            <!-- TABEL INFORMASI DETAIL SEKOLAH -->
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card card-box border-0 shadow-sm">
                        <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
                            <h6 class="fw-bold mb-0 text-success">
                                <i class="bi bi-check2-square me-2"></i>Sekolah Sudah Realisasi
                            </h6>
                            <span class="badge bg-success rounded-pill px-3 py-2"><?= $total_selesai; ?> Sekolah</span>
                        </div>
                        <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                            <?php if (!empty($list_selesai)): ?>
                                <table class="table table-custom w-100 mb-0">
                                    <thead>
                                        <tr class="text-secondary fs-7">
                                            <th style="width: 50px;">No</th>
                                            <th>Nama Sekolah</th>
                                            <th class="text-end">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($list_selesai as $idx => $sch): ?>
                                            <tr>
                                                <td class="fw-bold text-secondary"><?= $idx + 1; ?></td>
                                                <td class="fw-bold text-dark"><?= htmlspecialchars($sch['nama'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td class="text-end">
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1.5 rounded-2" style="font-size: 11px;">
                                                        <i class="bi bi-check-lg me-1"></i>Selesai Realisasi
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary opacity-50"></i>
                                    <span>Belum ada sekolah target yang menyelesaikan realisasi di bulan <?= $nama_bulan[$filter_bulan]; ?> <?= $filter_tahun; ?>.</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card card-box border-0 shadow-sm">
                        <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
                            <h6 class="fw-bold mb-0 text-danger">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>Sekolah Belum Realisasi
                            </h6>
                            <span class="badge bg-danger rounded-pill px-3 py-2"><?= $total_belum; ?> Sekolah</span>
                        </div>
                        <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                            <?php if (!empty($list_belum)): ?>
                                <table class="table table-custom w-100 mb-0">
                                    <thead>
                                        <tr class="text-secondary fs-7">
                                            <th style="width: 50px;">No</th>
                                            <th>Nama Sekolah</th>
                                            <th class="text-end">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($list_belum as $idx => $sch): ?>
                                            <tr>
                                                <td class="fw-bold text-secondary"><?= $idx + 1; ?></td>
                                                <td class="fw-bold text-dark"><?= htmlspecialchars($sch['nama'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td class="text-end">
                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2.5 py-1.5 rounded-2" style="font-size: 11px;">
                                                        <i class="bi bi-clock me-1"></i>Belum Realisasi
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="text-center py-5 text-success">
                                    <i class="bi bi-check-circle-fill fs-2 d-block mb-2"></i>
                                    <span>Semua sekolah target telah menyelesaikan realisasi di bulan <?= $nama_bulan[$filter_bulan]; ?> <?= $filter_tahun; ?>.</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
    <div class="footer mt-5 text-center text-secondary" style="font-size: 14px;">© 2026 SI DIPTA</div>
</div>

<div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius: 20px;">
            <div class="modal-body p-5 text-center">
                <div class="text-danger mb-4">
                    <i class="bi bi-exclamation-circle-fill" style="font-size: 60px;"></i>
                </div>
                <h4 class="fw-bold mb-2">Konfirmasi Keluar</h4>
                <p class="text-secondary mb-4">Apakah Anda yakin ingin mengakhiri sesi dan keluar dari sistem?</p>
                <div class="d-flex gap-3 justify-content-center">
                    <button type="button" class="btn btn-light px-4 py-2 fw-semibold" data-bs-dismiss="modal" style="border-radius: 12px;">Batal</button>
                    <a href="logout.php" class="btn btn-danger px-4 py-2 fw-semibold" style="border-radius: 12px;">Ya, Keluar</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    const mainContent = document.getElementById('ajax-container');
    const dashboardHtml = mainContent.innerHTML; 
    const loader = document.getElementById('loader');
    const loaderBar = loader.querySelector('div');
    const pageTitle = document.getElementById('page-title');

    function sanitizePath(url) {
        if (!url) return 'dashboard';
        if (url.match(/^(https?:|\/\/|javascript:|data:|\.\.\/|\/)/i)) {
            return 'dashboard';
        }
        return url;
    }

    const checkSidebarState = () => {
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (isCollapsed && window.innerWidth > 992) {
            document.body.classList.add('collapsed');
        }
    };
    checkSidebarState();

    document.getElementById('sidebarToggle').addEventListener('click', () => {
        if (window.innerWidth > 992) {
            document.body.classList.toggle('collapsed');
            localStorage.setItem('sidebarCollapsed', document.body.classList.contains('collapsed'));
        } else {
            document.body.classList.toggle('mobile-show');
        }
    });

    async function loadPage(pageUrl, title, pushState = true) {
        pageUrl = sanitizePath(pageUrl);

        let finalTitle = title;
        if (!finalTitle || finalTitle === 'undefined') {
            if (pageUrl.includes('data_pegawai')) finalTitle = "Data Pegawai";
            else if (pageUrl.includes('data_ruang')) finalTitle = "Data Ruang";
            else if (pageUrl.includes('data_kop')) finalTitle = "Struktur Manajemen";
            else if (pageUrl.includes('input_acuan')) finalTitle = "Master Barang Acuan";
            else finalTitle = "Halaman";
        }

        if (pageUrl === 'dashboard' || pageUrl === 'index_admin.php') {
            mainContent.innerHTML = dashboardHtml;
            pageTitle.innerText = "Dashboard";
            if(pushState) history.pushState({page: 'dashboard', title: 'Dashboard'}, '', 'index_admin.php');
            updateActiveMenu('dashboard');
            return;
        }

        loader.style.display = 'block';
        loaderBar.style.width = '50%';
        
        try {
            const response = await fetch(pageUrl);
            if (!response.ok) throw new Error("Network response was not ok");
            
            const text = await response.text();
            mainContent.innerHTML = text;
            pageTitle.innerText = finalTitle;
            
            const scripts = mainContent.querySelectorAll("script");
            scripts.forEach(s => {
                const newScript = document.createElement("script");
                newScript.text = s.text;
                document.body.appendChild(newScript).parentNode.removeChild(newScript);
            });

            if(pushState) history.pushState({page: pageUrl, title: finalTitle}, '', 'index_admin.php?p=' + encodeURIComponent(pageUrl));
            updateActiveMenu(pageUrl);
        } catch (e) {
            mainContent.innerHTML = '<div class="alert alert-danger">Gagal memuat halaman atau akses ditolak.</div>';
        } finally {
            loaderBar.style.width = '100%';
            setTimeout(() => { loader.style.display = 'none'; loaderBar.style.width = '0'; }, 300);
        }
    }

    function updateActiveMenu(page) {
        document.querySelectorAll('.menu-btn, .submenu-btn').forEach(el => el.classList.remove('active'));
        const activeEl = document.querySelector(`[data-page="${page}"]`);
        if (activeEl) {
            activeEl.classList.add('active');
            const parentCollapse = activeEl.closest('.collapse');
            if (parentCollapse) {
                const bsCollapse = bootstrap.Collapse.getOrCreateInstance(parentCollapse, { toggle: false });
                bsCollapse.show();
                const triggerBtn = document.querySelector(`[href="#${parentCollapse.id}"]`);
                if (triggerBtn) triggerBtn.classList.add('active');
            }
        }
    }

    document.addEventListener('click', e => {
        const link = e.target.closest('.ajax-link');
        if (link) {
            e.preventDefault();
            loadPage(link.getAttribute('data-page'), link.getAttribute('data-title'));
            if (window.innerWidth <= 992) {
                document.body.classList.remove('mobile-show');
            }
        }
    });

    window.addEventListener('popstate', e => {
        if (e.state && e.state.page) loadPage(e.state.page, e.state.title, false);
        else loadPage('dashboard', 'Dashboard', false);
    });

    window.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        const pageParam = urlParams.get('p');
        if (pageParam) {
            const safeParam = sanitizePath(pageParam);
            const link = document.querySelector(`[data-page="${safeParam}"]`);
            const title = link ? link.getAttribute('data-title') : null;
            loadPage(safeParam, title, false);
        } else {
            updateActiveMenu('dashboard');
        }
    });

    function updateTime(){
        const d = new Date();
        const element = document.getElementById('time');
        if(element) {
            element.innerHTML = d.toLocaleDateString('id-ID', {weekday:'long', day:'numeric', month:'long', year:'numeric'}) + " | " + d.getHours().toString().padStart(2, '0') + ":" + d.getMinutes().toString().padStart(2, '0');
        }
    }
    setInterval(updateTime, 1000); updateTime();
</script>
</body>
</html>