<?php
// ========================================================
// KEAMANAN 1: HTTP SECURITY HEADERS (STANDAR BSSN / SPBE)
// ========================================================
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; font-src 'self' https://cdn.jsdelivr.net https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self';");

// ========================================================
// KEAMANAN 2: SESSION HARDENING & COOKIE PROTECTION
// ========================================================
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);

$is_https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $is_https,
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validasi Autentikasi Login
if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit;
}

// Regenerasi Session ID secara berkala untuk mencegah Session Hijacking
if (!isset($_SESSION['last_regeneration'])) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
} else if (time() - $_SESSION['last_regeneration'] > 1800) { // 30 Menit
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// ========================================================
// KEAMANAN 3: GENERATE CSRF TOKEN PER SESI
// ========================================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

include "koneksi.php";

$id_sekolah = $_SESSION['id_sekolah'] ?? '';

// Matikan pelaporan error mentah MySQL ke publik
mysqli_report(MYSQLI_REPORT_OFF);

// ========================================================
// LOGIKA INTERSEPTOR 1: LIVE SEARCH BARANG (DB: db_inventaris)
// ========================================================
if (isset($_GET['search_barang'])) {
    header('Content-Type: application/json');
    $host = "localhost";
    $user = "root";
    $pass = ""; 
    
    $conn_inv = @new mysqli($host, $user, $pass, "db_inventaris");
    if ($conn_inv->connect_error) {
        error_log("DB Connection Error (db_inventaris): " . $conn_inv->connect_error);
        echo json_encode(['status' => 'error', 'message' => 'Layanan database pencarian barang sedang tidak tersedia.']);
        exit;
    }
    
    $keyword = $_GET['keyword'] ?? '';
    $search_param = "%" . $keyword . "%";

    $stmt_search = $conn_inv->prepare("SELECT kode_barang, uraian, jenis_aset FROM kode_barang WHERE kode_barang LIKE ? OR uraian LIKE ? LIMIT 10");
    if ($stmt_search) {
        $stmt_search->bind_param("ss", $search_param, $search_param);
        $stmt_search->execute();
        $result = $stmt_search->get_result();
        
        $data = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $data[] = [
                    'kode_barang' => htmlspecialchars($row['kode_barang'] ?? '', ENT_QUOTES, 'UTF-8'),
                    'nama_barang' => htmlspecialchars($row['uraian'] ?? '', ENT_QUOTES, 'UTF-8'),
                    'jenis_aset'  => htmlspecialchars($row['jenis_aset'] ?? '', ENT_QUOTES, 'UTF-8')
                ];
            }
            echo json_encode(['status' => 'success', 'data' => $data]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Tidak ada realisasi']);
        }
        $stmt_search->close();
    } else {
        error_log("DB Prepare Error Search: " . $conn_inv->error);
        echo json_encode(['status' => 'error', 'message' => 'Gagal memproses pencarian data.']);
    }
    
    $conn_inv->close();
    exit;
}

// ================= DATA SEKOLAH ACTIVE & NAMA SEKOLAH UNTUK HEADER KANAN =================
$satuan_pendidikan_tampil = "Sekolah Tidak Diketahui";
$id_sekolah_tampil = $id_sekolah;

$stmt_user = mysqli_prepare($conn, "SELECT id_sekolah FROM users WHERE id_sekolah = ? LIMIT 1");
if ($stmt_user) {
    mysqli_stmt_bind_param($stmt_user, "s", $id_sekolah);
    mysqli_stmt_execute($stmt_user);
    $res_user = mysqli_stmt_get_result($stmt_user);
    if ($dataSekolah = mysqli_fetch_assoc($res_user)) {
        $id_sekolah_tampil = $dataSekolah['id_sekolah'] ?? $id_sekolah;
    }
    mysqli_stmt_close($stmt_user);
}

$stmt_master = mysqli_prepare($conn, "SELECT * FROM `kode_sekolah` WHERE `id_sekolah` = ? OR `id` = ? LIMIT 1");
if ($stmt_master) {
    mysqli_stmt_bind_param($stmt_master, "ss", $id_sekolah_tampil, $id_sekolah_tampil);
    mysqli_stmt_execute($stmt_master);
    $result_master = mysqli_stmt_get_result($stmt_master);

    if ($result_master && mysqli_num_rows($result_master) > 0) {
        $row_master = mysqli_fetch_assoc($result_master);
        $satuan_pendidikan_tampil = $row_master['nama_sekolah'] ?? ($row_master['satuan_pendidikan'] ?? 'Sekolah Tidak Diketahui');
    } else {
        $stmt_alt = mysqli_prepare($conn, "SELECT `satuan_pendidikan` FROM `data_barang_acuan` WHERE `id_sekolah` = ? LIMIT 1");
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

// ========================================================
// OLAHKAN DATA INFORMASI DASHBOARD USER (REKAP PER BULAN)
// ========================================================
$bulan_sekarang_num = (int)date('n'); 
$bulan_lapor_num = ($bulan_sekarang_num === 1) ? 12 : ($bulan_sekarang_num - 1); 

$nama_bulan_arr = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

$nama_bulan_sekarang = $nama_bulan_arr[$bulan_sekarang_num] ?? 'Juli';
$nama_bulan_lapor = $nama_bulan_arr[$bulan_lapor_num] ?? 'Juni';

// Status Laporan Bulan Lalu
$status_bulan_lapor_text = "BELUM SELESAI";
$status_badge_bg = "#dc2626"; 
$status_badge_icon = "bi-x-circle-fill";

$stmt_status_dash = mysqli_prepare($conn, "SELECT `status` FROM `laporan_realisasi` WHERE `id_sekolah` = ? AND `bulan` = ? LIMIT 1");
if ($stmt_status_dash) {
    mysqli_stmt_bind_param($stmt_status_dash, "si", $id_sekolah_tampil, $bulan_lapor_num);
    mysqli_stmt_execute($stmt_status_dash);
    $res_status_dash = mysqli_stmt_get_result($stmt_status_dash);
    if ($res_status_dash && $row_st = mysqli_fetch_assoc($res_status_dash)) {
        $st_raw = strtolower(trim($row_st['status']));
        if ($st_raw === 'disetujui' || $st_raw === 'selesai' || $st_raw === 'menunggu approval') {
            $status_bulan_lapor_text = "SELESAI";
            $status_badge_bg = "#16a34a"; 
            $status_badge_icon = "bi-check-circle-fill";
        }
    }
    mysqli_stmt_close($stmt_status_dash);
}

// Inisialisasi rekap bulanan 1-12
$dash_rekap = [];
$total_keseluruhan_acuan = 0;
$total_keseluruhan_realisasi = 0;
$total_keseluruhan_aset = 0;
$total_keseluruhan_spk = 0;

for ($m = 1; $m <= 12; $m++) {
    $dash_rekap[$m] = [
        'bulan_nama'      => $nama_bulan_arr[$m],
        'nilai_acuan'     => 0,
        'total_realisasi' => 0,
        'total_aset'      => 0,
        'berkas_spk'      => 0
    ];
}

// Fetch Nilai Acuan Per Bulan
$stmt_acuan_dash = mysqli_prepare($conn, "SELECT `bulan`, SUM(`nominal`) as total_acuan FROM `data_barang_acuan` WHERE TRIM(`satuan_pendidikan`) = TRIM(?) GROUP BY `bulan`");
if ($stmt_acuan_dash) {
    mysqli_stmt_bind_param($stmt_acuan_dash, "s", $satuan_pendidikan_tampil);
    mysqli_stmt_execute($stmt_acuan_dash);
    $res_acuan_dash = mysqli_stmt_get_result($stmt_acuan_dash);
    while ($row_ac = mysqli_fetch_assoc($res_acuan_dash)) {
        $b = (int)$row_ac['bulan'];
        if ($b >= 1 && $b <= 12) {
            $val_ac = (float)$row_ac['total_acuan'];
            $dash_rekap[$b]['nilai_acuan'] = $val_ac;
            $total_keseluruhan_acuan += $val_ac;
        }
    }
    mysqli_stmt_close($stmt_acuan_dash);
}

// Fetch Realisasi, Total Aset, dan Berkas SPK Per Bulan
$stmt_real_dash = mysqli_prepare($conn, "SELECT `bulan_realisasi`, SUM(`nilai_perolehan`) as total_realisasi, COUNT(`id`) as total_aset, COUNT(DISTINCT CASE WHEN TRIM(`no_spk`) != '' THEN `no_spk` END) as berkas_spk FROM `master_barang_sekolah` WHERE `id_sekolah` = ? GROUP BY `bulan_realisasi`");
if ($stmt_real_dash) {
    mysqli_stmt_bind_param($stmt_real_dash, "s", $id_sekolah_tampil);
    mysqli_stmt_execute($stmt_real_dash);
    $res_real_dash = mysqli_stmt_get_result($stmt_real_dash);
    while ($row_re = mysqli_fetch_assoc($res_real_dash)) {
        $b = (int)$row_re['bulan_realisasi'];
        if ($b >= 1 && $b <= 12) {
            $val_re = (float)$row_re['total_realisasi'];
            $val_ast = (int)$row_re['total_aset'];
            $val_spk = (int)$row_re['berkas_spk'];

            $dash_rekap[$b]['total_realisasi'] = $val_re;
            $dash_rekap[$b]['total_aset']      = $val_ast;
            $dash_rekap[$b]['berkas_spk']      = $val_spk;

            $total_keseluruhan_realisasi += $val_re;
            $total_keseluruhan_aset      += $val_ast;
            $total_keseluruhan_spk       += $val_spk;
        }
    }
    mysqli_stmt_close($stmt_real_dash);
}

// ========================================================
// LOGIKA INTERSEPTOR 2: PROSES SIMPAN MULTIPLE REALISASI (MASSAL JSON)
// ========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['simpan_realisasi']) || isset($_POST['simpan_massal_server']))) {
    header('Content-Type: application/json');

    // KEAMANAN 4: VALIDASI CSRF TOKEN ON POST
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $client_token = $_POST['csrf_token'] ?? ($headers['X-CSRF-Token'] ?? ($headers['x-csrf-token'] ?? ''));

    if (empty($client_token) || !hash_equals($_SESSION['csrf_token'], $client_token)) {
        echo json_encode(['status' => 'error', 'message' => 'Gagal Keamanan: Token CSRF tidak valid atau telah kedaluwarsa. Silakan refresh halaman Anda.']);
        exit;
    }
    
    $all_payloads_json = $_POST['all_kodering_payloads'] ?? '';
    
    if (empty($all_payloads_json)) {
        echo json_encode(['status' => 'error', 'message' => 'Gagal Interseptor Server: Data payload kosong atau tidak terbaca dari browser.']);
        exit;
    }

    $decoded_data = json_decode($all_payloads_json, true);
    if (!is_array($decoded_data)) {
        echo json_encode(['status' => 'error', 'message' => 'Gagal Interseptor Server: Format JSON data rusak.']);
        exit;
    }

    $success_count = 0;
    $error_messages = [];

    $query_insert = "INSERT INTO `realisasi_barang_sekolah` 
        (`id_sekolah`, `no_sp2d`, `sumber_perolehan`, `kodering_belanja`, `bulan_realisasi`, `no_spk`, `ba_no`, `ba_tgl`, `kode_barang`, `nama_barang`, `jenis_aset`, `merk_tipe`, `no_sertifikat`, `ukuran_bangunan`, `satuan`, `volume`, `harga_satuan`, `nilai_perolehan`, `created_at`)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt_insert = mysqli_prepare($conn, $query_insert);

    foreach ($decoded_data as $md5_key => $data_spj) {
        $no_sp2d          = trim($data_spj['no_sp2d'] ?? '');
        $sumber_perolehan = trim($data_spj['sumber_perolehan'] ?? '');
        $no_spk           = trim($data_spj['no_spk'] ?? '');
        $ba_no            = trim($data_spj['ba_no'] ?? '');
        $ba_tgl           = trim($data_spj['ba_tgl'] ?? '');
        $bulan_realisasi  = trim($_GET['bulan_realisasi'] ?? ($_POST['bulan_realisasi'] ?? ''));
        $kodering_belanja = trim($data_spj['kodering_asli_fallback'] ?? '');

        if (empty($bulan_realisasi)) {
            echo json_encode(['status' => 'error', 'message' => 'Gagal Validasi: Parameter Bulan Realisasi tidak ditemukan pada context URL / Form.']);
            exit;
        }

        // --- VALIDASI ANGGARAN TARGET SISI SERVER ---
        $nominal_acuan_sah = 0;
        $kodering_ditemukan = false;
        
        $stmt_verify = mysqli_prepare($conn, "SELECT * FROM `data_barang_acuan` WHERE TRIM(`satuan_pendidikan`) = TRIM(?)");
        if ($stmt_verify) {
            mysqli_stmt_bind_param($stmt_verify, "s", $satuan_pendidikan_tampil);
            mysqli_stmt_execute($stmt_verify);
            $res_verify = mysqli_stmt_get_result($stmt_verify);

            if ($res_verify && mysqli_num_rows($res_verify) > 0) {
                while ($row_acuan = mysqli_fetch_assoc($res_verify)) {
                    $match_bulan = false;
                    foreach ($row_acuan as $key => $val) {
                        if (in_array(strtolower(trim($key)), ['bulan', 'waktu', 'periode', 'bln'])) {
                            if ((int)$val === (int)$bulan_realisasi) { $match_bulan = true; break; }
                        }
                    }
                    if ($match_bulan) {
                        $check_kodering_val = $row_acuan['kodering'] ?? ($row_acuan['KODERING'] ?? '');
                        if (trim($check_kodering_val) === $kodering_belanja) {
                            $nominal_acuan_sah = (float)($row_acuan['nominal'] ?? ($row_acuan['NOMINAL'] ?? ($row_acuan['harga'] ?? ($row_acuan['total'] ?? 0))));
                            $kodering_ditemukan = true;
                            break;
                        }
                    }
                }
            }
            mysqli_stmt_close($stmt_verify);
        }

        // Hitung total nilai inputan
        $total_perolehan_user = 0;
        if (!empty($data_spj['itemsBarang']) && is_array($data_spj['itemsBarang'])) {
            foreach ($data_spj['itemsBarang'] as $item) {
                $vol = (int)($item['volume'] ?? 0);
                $hrg = (float)str_replace(['Rp', '.', ' ', ','], '', $item['harga'] ?? '0');
                $total_perolehan_user += ($vol * $hrg);
            }
        }

        // Jalankan Proteksi Batas Anggaran
        if (!$kodering_ditemukan) {
            echo json_encode(['status' => 'error', 'message' => "Gagal Proteksi Server: Kodering [$kodering_belanja] tidak terdaftar pada acuan bulan terpilih."]);
            exit;
        } else if ($total_perolehan_user != $nominal_acuan_sah) {
            echo json_encode(['status' => 'error', 'message' => "Gagal Simpan: Jumlah inputan Rekening $kodering_belanja (Rp " . number_format($total_perolehan_user,0,',','.') . ") tidak balance dengan nilai acuan target (Rp " . number_format($nominal_acuan_sah,0,',','.') . "). Data harus bernilai sama pas!"]);
            exit;
        }

        // --- PROSES INSERT DATABASE KOLEKTIF ---
        if (!empty($data_spj['itemsBarang']) && is_array($data_spj['itemsBarang'])) {
            foreach ($data_spj['itemsBarang'] as $item) {
                $kode_barang     = trim($item['kode_barang'] ?? '');
                $nama_barang     = trim($item['nama_barang'] ?? '');
                $jenis_aset      = trim($item['jenis_aset'] ?? '');
                $merk_tipe       = trim($item['merk'] ?? '');
                $no_sertifikat   = !empty($item['sertifikat']) ? trim($item['sertifikat']) : '-';
                $ukuran_bangunan = !empty($item['ukuran']) ? trim($item['ukuran']) : '-';
                $satuan          = trim($item['satuan'] ?? '');
                $volume          = (int)($item['volume'] ?? 0);
                $harga_satuan    = (float)str_replace(['Rp', '.', ' ', ','], '', $item['harga'] ?? '0');
                $nilai_perolehan = $volume * $harga_satuan;

                if (empty($kode_barang) || $volume <= 0) continue;
                
                $ba_tgl_val = empty($ba_tgl) ? null : $ba_tgl;

                if ($stmt_insert) {
                    mysqli_stmt_bind_param($stmt_insert, "ssssssssssssssisdd", 
                        $id_sekolah_tampil, $no_sp2d, $sumber_perolehan, $kodering_belanja, $bulan_realisasi, 
                        $no_spk, $ba_no, $ba_tgl_val, $kode_barang, $nama_barang, $jenis_aset, $merk_tipe, 
                        $no_sertifikat, $ukuran_bangunan, $satuan, $volume, $harga_satuan, $nilai_perolehan
                    );

                    if (mysqli_stmt_execute($stmt_insert)) {
                        $success_count++;
                    } else {
                        // KEAMANAN 5: SEMBUNYIKAN ERROR DATABASE DARI PENGGUNA, TULIS KE LOG SERVER
                        error_log("DB Insert Error (realisasi_barang_sekolah): " . mysqli_stmt_error($stmt_insert));
                        $error_messages[] = "Gagal memproses item barang: " . htmlspecialchars($nama_barang, ENT_QUOTES, 'UTF-8');
                    }
                }
            }
        }
    }
    
    if (isset($stmt_insert) && $stmt_insert) {
        mysqli_stmt_close($stmt_insert);
    }

    // --- RETURN HASIL KE BROWSER ---
    if ($success_count > 0 && empty($error_messages)) {
        echo json_encode([
            'status' => 'success', 
            'message' => "Berhasil Simpan Ke DB! Sebanyak $success_count item data realisasi belanja modal sekolah Anda sukses diinput permanen."
        ]);
    } else {
        echo json_encode([
            'status' => 'error', 
            'message' => "Gagal Simpan Ke DB! Terjadi kendala sistem database. " . implode(" | ", array_unique($error_messages))
        ]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= $csrf_token; ?>">
    <title>SI DIPTA | Dashboard User</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=Yellowtail&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #2563eb; --secondary: #64748b; --dark: #0f172a;
            --sidebar-width: 280px; --sidebar-collapsed: 80px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --bg-body: #f8fafc; --bg-card: #ffffff; --border-color: #e2e8f0; --text-main: #0f172a;
        }

        * { font-family: 'Plus Jakarta Sans', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }
        body { background: var(--bg-body); color: var(--text-main); overflow-x: hidden; }

        .sidebar .brand { 
            padding: 35px 20px 25px 20px; 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            justify-content: center;
            text-align: center;
        }
        .brand-text-wrapper { line-height: 1; margin-top: 5px; }
        .brand-si-dipta { 
            display: block; font-weight: 900; font-size: 1.6rem; color: #1e3a8a; 
            text-transform: uppercase; letter-spacing: -1px; 
        }
        .brand-beu { 
            display: block; font-family: 'Yellowtail', cursive; font-size: 2.3rem; 
            color: #f59e0b; margin-top: -6px; margin-left: 30px; transform: rotate(-3deg);
        }
        .brand-sub { font-size: 8px; color: #94a3b8; font-weight: 700; margin-top: 5px; letter-spacing: 0.5px; }
        body.collapsed .brand-beu, body.collapsed .brand-sub { display: none; }
        body.collapsed .brand-si-dipta { font-size: 1rem; }

        .sidebar {
            width: var(--sidebar-width); height: 100vh; position: fixed; top: 0; left: 0;
            background: var(--bg-card); border-right: 1px solid var(--border-color); z-index: 1000;
            transition: var(--transition); display: flex; flex-direction: column;
        }
        body.collapsed .sidebar { width: var(--sidebar-collapsed); }

        .nav-wrapper { padding: 10px 15px 30px 15px; flex-grow: 1; overflow-y: auto; overflow-x: hidden; }
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

        .card-box { border: none; border-radius: 20px; background: var(--bg-card); box-shadow: 0 10px 25px rgba(148, 163, 184, 0.05); padding: 30px; border: 1px solid rgba(241, 245, 249, 0.8); }

        /* Style Khusus Penegasan Informasi Dashboard (Bold Typography) */
        .dash-bold-title { font-weight: 900 !important; color: #0f172a !important; letter-spacing: -0.5px; }
        .dash-bold-text { font-weight: 800 !important; color: #1e293b !important; }
        .dash-bold-sub { font-weight: 700 !important; color: #334155 !important; }
        .dash-status-box {
            background: #ffffff;
            border: 2px solid #e2e8f0;
            border-radius: 18px;
            padding: 20px 24px;
            box-shadow: 0 10px 20px -5px rgba(0,0,0,0.03);
        }
        .dash-table-header {
            background: #1e3a8a !important;
            color: #ffffff !important;
            font-weight: 800 !important;
            text-transform: uppercase;
            font-size: 13px;
            letter-spacing: 0.5px;
        }

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
        <div class="brand-text-wrapper">
            <span class="brand-si-dipta">SI DIPTA</span>
            <span class="brand-beu">Beu!</span>
        </div>
        <div class="brand-sub">SISTEM DIGITALISASI PELAPORAN ASET</div>
    </div>

    <div class="nav-wrapper">
        <div class="menu-label">Menu Utama User</div>
        
        <a href="index.php" class="menu-btn ajax-link" data-page="dashboard" data-title="Dashboard">
            <i class="bi bi-grid-1x2-fill"></i>
            <span>Dashboard</span>
        </a>
        <a href="pilih_bulan_data_barang.php" class="menu-btn ajax-link" data-page="pilih_bulan_data_barang.php" data-title="Data Barang">
            <i class="bi bi-file-earmark-arrow-up-fill"></i>
            <span>Data Barang</span>
        </a>
        
        <a href="pilih_bulan.php" class="menu-btn ajax-link" data-page="pilih_bulan.php" data-title="Input Realisasi">
            <i class="bi bi-file-earmark-arrow-up-fill"></i>
            <span>Input Realisasi</span>
        </a>
        <a href="data_realisasi.php" class="menu-btn ajax-link" data-page="data_realisasi.php" data-title="Data Realisasi">
            <i class="bi bi-file-earmark-arrow-up-fill"></i>
            <span>Data Realisasi</span>
        </a>
    </div>

    <div class="p-3 border-top mt-auto">
        <button id="btn-sidebar-logout" class="menu-btn text-danger border-0 w-100 bg-transparent">
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
            <small class="text-secondary" style="font-weight: 600; text-transform: uppercase; font-size: 12px;">ID SEKOLAH: <?= htmlspecialchars($id_sekolah_tampil, ENT_QUOTES, 'UTF-8'); ?></small>
        </div>
    </div>
    
    <div class="d-flex align-items-center gap-3">
        <div id="time" class="fw-bold d-none d-xl-block me-2" style="font-size: 14px; color: #64748b;"></div>
        
        <div class="text-end d-none d-sm-block">
            <h6 class="mb-0 fw-bold text-dark" style="font-size: 14px; max-width: 280px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($satuan_pendidikan_tampil, ENT_QUOTES, 'UTF-8'); ?></h6>
            <span class="badge bg-success bg-opacity-10 text-success fw-bold p-1 px-2 rounded" style="font-size: 10px;"><i class="bi bi-shield-check me-1"></i>Akses Sekolah</span>
        </div>
        
        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 42px; height: 42px; min-width: 42px;">
            <i class="bi bi-person-fill fs-5"></i>
        </div>
    </div>
</div>

<div class="content" id="main-content">
    <div id="notification-area"></div>

    <div class="content-body">
        <div id="ajax-container">
            <div id="dashboard-content">
                
                <!-- BANNER UTAMA DASHBOARD INFORMASI -->
                <div class="card card-box p-4 mb-4" style="background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%); color: #ffffff; border: none;">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-warning text-dark fw-bold px-3 py-2 rounded-3" style="font-weight: 800; font-size: 12px;">
                                    <i class="bi bi-calendar-check-fill me-1"></i> BULAN BERJALAN: <?= strtoupper($nama_bulan_sekarang); ?>
                                </span>
                                <span class="badge bg-white text-primary fw-bold px-3 py-2 rounded-3" style="font-weight: 800; font-size: 12px;">
                                    ID: <?= htmlspecialchars($id_sekolah_tampil, ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </div>
                            <h3 class="fw-bold mb-1" style="font-weight: 900 !important; font-size: 24px;">
                                DASHBOARD REKAPITULASI ASET SEKOLAH
                            </h3>
                            <div class="fw-bold opacity-90" style="font-weight: 700 !important; font-size: 14px; color: #e2e8f0;">
                                <?= htmlspecialchars($satuan_pendidikan_tampil, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        </div>

                        <!-- PANEL STATUS KANAN ATAS -->
                        <div class="dash-status-box text-md-end" style="min-width: 280px; background: rgba(255, 255, 255, 0.95); border: 2px solid rgba(255,255,255,0.3);">
                            <div class="dash-bold-sub text-uppercase mb-1" style="font-size: 11px; color: #64748b; letter-spacing: 0.5px;">
                                STATUS LAPORAN BULAN <?= strtoupper($nama_bulan_lapor); ?>:
                            </div>
                            <div class="d-inline-flex align-items-center gap-2 px-3 py-2 rounded-3 text-white fw-bold shadow-sm" style="background: <?= $status_badge_bg; ?>; font-size: 15px; font-weight: 900 !important;">
                                <i class="bi <?= $status_badge_icon; ?> fs-5"></i>
                                <span>STATUS <?= strtoupper($nama_bulan_lapor); ?>: <?= $status_bulan_lapor_text; ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RINGKASAN REKAPITULASI SETAHUN (4 METRIK UTAMA) -->
                <div class="row g-3 mb-4">
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="card card-box p-3 border-start border-4 border-primary h-100">
                            <div class="dash-bold-sub text-muted text-uppercase mb-1" style="font-size: 11px;">TOTAL ACUAN ANGGARAN</div>
                            <div class="dash-bold-title fs-4 text-primary">Rp <?= number_format($total_keseluruhan_acuan, 0, ',', '.'); ?></div>
                            <div class="dash-bold-sub text-secondary small mt-1">Target Anggaran 12 Bulan</div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="card card-box p-3 border-start border-4 border-success h-100">
                            <div class="dash-bold-sub text-muted text-uppercase mb-1" style="font-size: 11px;">TOTAL REALISASI BELANJA</div>
                            <div class="dash-bold-title fs-4 text-success">Rp <?= number_format($total_keseluruhan_realisasi, 0, ',', '.'); ?></div>
                            <div class="dash-bold-sub text-secondary small mt-1">Capaian Realisasi Aset</div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="card card-box p-3 border-start border-4 border-warning h-100">
                            <div class="dash-bold-sub text-muted text-uppercase mb-1" style="font-size: 11px;">TOTAL FISIK ASET</div>
                            <div class="dash-bold-title fs-4 text-dark"><?= number_format($total_keseluruhan_aset, 0, ',', '.'); ?> <small class="fs-6 text-muted">Item</small></div>
                            <div class="dash-bold-sub text-secondary small mt-1">Jumlah Rincian Barang</div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="card card-box p-3 border-start border-4 border-info h-100">
                            <div class="dash-bold-sub text-muted text-uppercase mb-1" style="font-size: 11px;">TOTAL BERKAS SPK</div>
                            <div class="dash-bold-title fs-4 text-info"><?= number_format($total_keseluruhan_spk, 0, ',', '.'); ?> <small class="fs-6 text-muted">Berkas</small></div>
                            <div class="dash-bold-sub text-secondary small mt-1">Dokumen Kontrak Terdata</div>
                        </div>
                    </div>
                </div>

                <!-- TABEL DETAIL RINCIAN REKAPITULASI PER BULAN (1 - 12) -->
                <div class="card card-box p-4 mb-2">
                    <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-3">
                        <div>
                            <h5 class="dash-bold-title mb-1"><i class="bi bi-table text-primary me-2"></i>RINCIAN PERKEMBANGAN REALISASI TIAP BULAN</h5>
                            <div class="dash-bold-sub text-secondary small">Rangkuman Nilai Acuan, Realisasi Belanja, Jumlah Aset, dan Berkas SPK per Periode Bulan</div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle mb-0" style="border: 2px solid #cbd5e1;">
                            <thead>
                                <tr class="text-center align-middle dash-table-header">
                                    <th width="5%" class="py-3">NO</th>
                                    <th width="18%" class="py-3 text-start ps-3">PERIODE BULAN</th>
                                    <th width="22%" class="py-3 text-end pe-3">NILAI ACUAN</th>
                                    <th width="23%" class="py-3 text-end pe-3">TOTAL REALISASI</th>
                                    <th width="16%" class="py-3 text-center">TOTAL ASET</th>
                                    <th width="16%" class="py-3 text-center">BERKAS SPK</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dash_rekap as $m_num => $data_m): 
                                    $is_bulan_lapor = ($m_num === $bulan_lapor_num);
                                    $is_bulan_sekarang = ($m_num === $bulan_sekarang_num);
                                    
                                    $row_bg = "";
                                    if ($is_bulan_sekarang) {
                                        $row_bg = "background: #f0f9ff;";
                                    } else if ($is_bulan_lapor) {
                                        $row_bg = "background: #fefce8;";
                                    }
                                ?>
                                    <tr style="<?= $row_bg; ?>">
                                        <td class="text-center dash-bold-text py-3"><?= $m_num; ?></td>
                                        <td class="py-3 ps-3">
                                            <span class="dash-bold-title" style="font-size: 14px;"><?= htmlspecialchars($data_m['bulan_nama'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php if ($is_bulan_sekarang): ?>
                                                <span class="badge bg-primary ms-1 fw-bold" style="font-size: 10px;">Bulan Berjalan</span>
                                            <?php elseif ($is_bulan_lapor): ?>
                                                <span class="badge bg-warning text-dark ms-1 fw-bold" style="font-size: 10px;">Bulan Lapor</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-3 dash-bold-text text-secondary" style="font-size: 14px;">
                                            Rp <?= number_format($data_m['nilai_acuan'], 0, ',', '.'); ?>
                                        </td>
                                        <td class="text-end pe-3 dash-bold-title text-primary" style="font-size: 14px;">
                                            Rp <?= number_format($data_m['total_realisasi'], 0, ',', '.'); ?>
                                        </td>
                                        <td class="text-center dash-bold-text text-dark" style="font-size: 14px;">
                                            <span class="badge bg-light text-dark border px-3 py-2 fw-bold" style="font-weight: 800 !important; font-size: 13px;">
                                                <?= number_format($data_m['total_aset'], 0, ',', '.'); ?> Item
                                            </span>
                                        </td>
                                        <td class="text-center dash-bold-text text-dark" style="font-size: 14px;">
                                            <span class="badge bg-light text-primary border px-3 py-2 fw-bold" style="font-weight: 800 !important; font-size: 13px;">
                                                <?= number_format($data_m['berkas_spk'], 0, ',', '.'); ?> Dokumen
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot style="background: #f1f5f9; border-top: 3px solid #0f172a;">
                                <tr>
                                    <td colspan="2" class="text-end py-3 ps-3 dash-bold-title text-uppercase" style="font-size: 13px; color: #0f172a;">
                                        TOTAL KESELURUHAN SETAHUN:
                                    </td>
                                    <td class="text-end pe-3 dash-bold-title text-secondary" style="font-size: 15px;">
                                        Rp <?= number_format($total_keseluruhan_acuan, 0, ',', '.'); ?>
                                    </td>
                                    <td class="text-end pe-3 dash-bold-title text-success" style="font-size: 15px;">
                                        Rp <?= number_format($total_keseluruhan_realisasi, 0, ',', '.'); ?>
                                    </td>
                                    <td class="text-center dash-bold-title text-dark" style="font-size: 15px;">
                                        <?= number_format($total_keseluruhan_aset, 0, ',', '.'); ?> Item
                                    </td>
                                    <td class="text-center dash-bold-title text-dark" style="font-size: 15px;">
                                        <?= number_format($total_keseluruhan_spk, 0, ',', '.'); ?> Dokumen
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <div class="footer mt-5 text-center text-secondary" style="font-size: 14px; font-weight: 700;">© 2026 SI DIPTA - Sistem Digitalisasi Pelaporan Aset</div>
</div>

<div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius: 20px;">
            <div class="modal-body p-5 text-center">
                <div class="text-danger mb-4">
                    <i class="bi bi-exclamation-circle-fill" style="font-size: 60px;"></i>
                </div>
                <h4 class="fw-bold mb-2">Konfirmasi Keluar</h4>
                <p class="text-secondary mb-4">Apakah Anda yakin ingin mengakhiri sesi dan keluar dari sistem user?</p>
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
    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const mainContent = document.getElementById('ajax-container');
    const dashboardHtml = mainContent.innerHTML; 
    const loader = document.getElementById('loader');
    const loaderBar = loader.querySelector('div');
    const pageTitle = document.getElementById('page-title');
    const notifArea = document.getElementById('notification-area');

    const myLogoutModal = new bootstrap.Modal(document.getElementById('logoutModal'), {
        keyboard: false
    });

    document.getElementById('btn-sidebar-logout').addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        myLogoutModal.show();
    });

    document.getElementById('sidebarToggle').addEventListener('click', () => {
        if (window.innerWidth > 992) {
            document.body.classList.toggle('collapsed');
        } else {
            document.body.classList.toggle('mobile-show');
        }
    });

    async function loadPage(pageUrl, title, pushState = true) {
        let finalTitle = title;
        if (!finalTitle || finalTitle === 'undefined') {
            if (pageUrl.includes('input_realisasi') || pageUrl.includes('tambah_spj')) finalTitle = "Input Realisasi";
            else if (pageUrl.includes('data_barang_input')) finalTitle = "Data Barang";
            else finalTitle = "Data Barang";
        }

        if (pageUrl === 'dashboard' || pageUrl === 'index.php') {
            mainContent.innerHTML = dashboardHtml;
            pageTitle.innerText = "Dashboard";
            notifArea.innerHTML = ""; 
            if(pushState) history.pushState({page: 'dashboard', title: 'Dashboard'}, '', 'index.php');
            updateActiveMenu('dashboard');
            return;
        }

        loader.style.display = 'block';
        loaderBar.style.width = '50%';
        
        try {
            let fetchUrl = pageUrl;
            const urlParams = new URLSearchParams(window.location.search);
            
            if (!fetchUrl.includes('bulan_realisasi=') && urlParams.has('bulan_realisasi')) {
                fetchUrl += (fetchUrl.includes('?') ? '&' : '?') + 'bulan_realisasi=' + urlParams.get('bulan_realisasi');
            }

            const response = await fetch(fetchUrl, {
                headers: {
                    'X-CSRF-Token': CSRF_TOKEN
                }
            });
            if (!response.ok) throw new Error('Halaman tidak ditemukan');
            
            const text = await response.text();
            mainContent.innerHTML = text;
            pageTitle.innerText = finalTitle;
            
            const scripts = mainContent.querySelectorAll("script");
            scripts.forEach(s => {
                const newScript = document.createElement("script");
                newScript.text = s.text;
                document.body.appendChild(newScript).parentNode.removeChild(newScript);
            });

            attachFormInterceptor();
            
            if(pushState) {
                let baseFile = pageUrl.split('?')[0];
                let stateUrl = 'index.php?p=' + baseFile;
                
                if (pageUrl.includes('?')) {
                    let sideParams = new URLSearchParams(pageUrl.split('?')[1]);
                    sideParams.forEach((value, key) => {
                        stateUrl += `&${key}=${value}`;
                    });
                }
                
                if (!stateUrl.includes('bulan_realisasi=') && urlParams.has('bulan_realisasi')) {
                    stateUrl += '&bulan_realisasi=' + urlParams.get('bulan_realisasi');
                }
                
                history.pushState({page: pageUrl, title: finalTitle}, '', stateUrl);
            }
            
            let basePageName = pageUrl.split('?')[0];
            updateActiveMenu(basePageName);
        } catch (e) {
            mainContent.innerHTML = '<div class="alert alert-danger m-3">Gagal memuat halaman internal atau file tidak ditemukan (404).</div>';
        } finally {
            loaderBar.style.width = '100%';
            setTimeout(() => { loader.style.display = 'none'; loaderBar.style.width = '0'; }, 300);
        }
    }

    function attachFormInterceptor() {
        const targetForm = mainContent.querySelector('#form_realisasi') || mainContent.querySelector('#form_realisasi_final');
        if (targetForm) {
            targetForm.addEventListener('submit', async function(e) {
                e.preventDefault(); 
                loader.style.display = 'block';
                loaderBar.style.width = '40%';
                const formData = new FormData(this);
                formData.append('simpan_realisasi', '1'); 
                formData.append('csrf_token', CSRF_TOKEN); // Proteksi CSRF Form Input

                try {
                    const response = await fetch('index.php' + window.location.search, { 
                        method: 'POST', 
                        headers: {
                            'X-CSRF-Token': CSRF_TOKEN
                        },
                        body: formData 
                    });
                    const resData = await response.json();
                    if (resData.status === 'success') {
                        notifArea.innerHTML = `<div class='alert alert-success border-0 shadow-sm mb-4'><i class='bi bi-check-circle-fill me-2'></i>${resData.message}</div>`;
                        targetForm.reset(); 
                        const totalLabels = mainContent.querySelectorAll('[id^="total_label_"]');
                        totalLabels.forEach(lbl => lbl.innerText = "Rp 0");
                        
                        if (typeof hapusDraftManual === 'function') {
                            hapusDraftManual();
                        }
                    } else {
                        notifArea.innerHTML = `<div class='alert alert-danger border-0 shadow-sm mb-4'><i class='bi bi-exclamation-triangle-fill me-2'></i>${resData.message}</div>`;
                    }
                    window.scrollTo({ top: 0, behavior: 'smooth' }); 
                } catch (error) {
                    notifArea.innerHTML = `<div class='alert alert-danger border-0 shadow-sm mb-4'><i class='bi bi-exclamation-triangle-fill me-2'></i>Koneksi bermasalah saat mengirim data form.</div>`;
                } finally {
                    loaderBar.style.width = '100%';
                    setTimeout(() => { loader.style.display = 'none'; loaderBar.style.width = '0'; }, 300);
                }
            });
        }
    }

    function updateActiveMenu(page) {
        document.querySelectorAll('.menu-btn').forEach(el => el.classList.remove('active'));
        
        let checkPage = page;
        if (checkPage === 'data_barang_input.php') {
            checkPage = 'pilih_bulan_data_barang.php';
        } else if (checkPage === 'input_realisasi.php' || checkPage === 'tambah_spj.php') {
            checkPage = 'pilih_bulan.php';
        }
        
        const activeEl = document.querySelector(`[data-page="${checkPage}"]`) || document.querySelector(`[data-page*="${checkPage}"]`);
        if (activeEl) activeEl.classList.add('active');
    }

    document.addEventListener('click', e => {
        const link = e.target.closest('.ajax-link');
        if (link) {
            e.preventDefault();
            let targetPage = link.getAttribute('data-page');
            
            if(!targetPage.includes('input_realisasi') && !targetPage.includes('tambah_spj')) {
                const url = new URL(window.location.href);
                url.searchParams.delete('bulan_realisasi');
                window.history.replaceState({}, '', url.toString());
            }
            loadPage(targetPage, link.getAttribute('data-title'));
            if (window.innerWidth <= 992) document.body.classList.remove('mobile-show');
        }
    });

    window.addEventListener('popstate', e => {
        if (e.state && e.state.page) loadPage(e.state.page, e.state.title, false);
        else {
            const urlParams = new URLSearchParams(window.location.search);
            const pageParam = urlParams.get('p');
            if(pageParam) {
                let fullUrl = pageParam;
                urlParams.forEach((val, key) => {
                    if(key !== 'p') fullUrl += `&${key}=${val}`;
                });
                loadPage(fullUrl, null, false);
            } else {
                loadPage('dashboard', 'Dashboard', false);
            }
        }
    });

    window.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        const pageParam = urlParams.get('p');
        const bulanParam = urlParams.get('bulan_realisasi');
        
        if (pageParam) {
            let fullUrl = pageParam;
            let queryParts = [];
            urlParams.forEach((value, key) => {
                if (key !== 'p') {
                    queryParts.push(`${encodeURIComponent(key)}=${encodeURIComponent(value)}`);
                }
            });
            if (queryParts.length > 0) {
                fullUrl += (fullUrl.includes('?') ? '&' : '?') + queryParts.join('&');
            }
            
            let checkPage = pageParam;
            if (checkPage === 'data_barang_input.php') {
                checkPage = 'pilih_bulan_data_barang.php';
            } else if (checkPage === 'input_realisasi.php' || checkPage === 'tambah_spj.php') {
                checkPage = 'pilih_bulan.php';
            }
            
            const link = document.querySelector(`[data-page="${checkPage}"]`);
            const title = link ? link.getAttribute('data-title') : null;
            loadPage(fullUrl, title, false);
        } else if (bulanParam && !pageParam) {
            loadPage('input_realisasi.php?bulan_realisasi=' + bulanParam, 'Input Realisasi', false);
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