<?php
// =========================================================================
// KEAMANAN 1: HTTP Security Headers Maksimal
// (Error reporting diatur terpusat via APP_DEBUG di env.php)
// =========================================================================

// Header Keamanan HTTP
header("X-Frame-Options: DENY"); // Cegah Clickjacking
header("X-Content-Type-Options: nosniff"); // Cegah MIME Sniffing
header("X-XSS-Protection: 1; mode=block"); // Aktifkan XSS Filter Browser
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: camera=(), microphone=(), geolocation=()");

// =========================================================================
// KEAMANAN 2: Proteksi Session Tingkat Lanjut (Strict & Secure Cookies)
// =========================================================================
ini_set('session.use_strict_mode', 1); 
ini_set('session.cookie_httponly', 1); 
ini_set('session.use_only_cookies', 1); 
ini_set('session.cookie_samesite', 'Strict'); 

if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === '1')) {
    ini_set('session.cookie_secure', 1); 
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// KEAMANAN 3: Proteksi Session Hijacking
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
if (isset($_SESSION['login']) && $_SESSION['login'] === true) {
    if (!isset($_SESSION['user_agent']) || $_SESSION['user_agent'] !== md5($user_agent)) {
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit;
    }
}

// KEAMANAN 4: Pembuatan CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Memanggil file koneksi bawaan
if (file_exists('koneksi.php')) {
    include 'koneksi.php';
} else {
    die("Error: File 'koneksi.php' tidak ditemukan. Pastikan file tersebut ada di folder yang sama.");
}

// Proteksi Tambahan: Amankan file log dari akses langsung via browser (.htaccess)
if (!file_exists('.htaccess')) {
    @file_put_contents('.htaccess', "<Files \"auth_security_log.txt\">\n    Order Allow,Deny\n    Deny from all\n</Files>\n");
}

// Fungsi Audit Log (Sanitasi CRLF Log Injection)
function catat_log($aksi, $user, $status) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $waktu = date('Y-m-d H:i:s');
    
    $user_clean   = str_replace(["\r", "\n"], '', $user);
    $aksi_clean   = str_replace(["\r", "\n"], '', $aksi);
    $status_clean = str_replace(["\r", "\n"], '', $status);

    $log_pesan = "[$waktu] IP: $ip | USER: $user_clean | AKSI: $aksi_clean | STATUS: $status_clean" . PHP_EOL;
    file_put_contents('auth_security_log.txt', $log_pesan, FILE_APPEND | LOCK_EX);
}

// =========================================================================
// FUNGSI SISTEM ANTI BRUTE-FORCE BERBASIS PERANGKAT (PER-DEVICE LOCKOUT)
// =========================================================================

function get_device_hash() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
    
    if (!isset($_COOKIE['dev_sec_token'])) {
        $device_token = bin2hex(random_bytes(16));
        $is_secure = (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === '1'));
        
        setcookie('dev_sec_token', $device_token, [
            'expires' => time() + (86400 * 365), 
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Strict',
            'secure' => $is_secure
        ]);
        $_COOKIE['dev_sec_token'] = $device_token;
    } else {
        $device_token = $_COOKIE['dev_sec_token'];
    }

    return md5($ip . '_' . $agent . '_' . $device_token);
}

function check_device_lockout($max_attempts = 3, $lockout_time = 900) {
    $device_hash = get_device_hash();
    $file = sys_get_temp_dir() . '/bf_dev_' . $device_hash . '.json';
    if (file_exists($file)) {
        $data = json_decode(@file_get_contents($file), true);
        if (isset($data['lockout_until']) && $data['lockout_until'] > time()) {
            return ceil(($data['lockout_until'] - time()) / 60);
        }
    }
    return 0;
}

function record_failed_device_attempt($max_attempts = 3, $lockout_time = 900) {
    $device_hash = get_device_hash();
    $file = sys_get_temp_dir() . '/bf_dev_' . $device_hash . '.json';
    $data = ['attempts' => 0, 'lockout_until' => 0];
    if (file_exists($file)) {
        $fetched = json_decode(@file_get_contents($file), true);
        if (is_array($fetched)) {
            $data = $fetched;
        }
    }
    
    if (isset($data['lockout_until']) && $data['lockout_until'] > 0 && $data['lockout_until'] <= time()) {
        $data['attempts'] = 0;
        $data['lockout_until'] = 0;
    }

    $data['attempts']++;
    if ($data['attempts'] >= $max_attempts) {
        $data['lockout_until'] = time() + $lockout_time;
    }
    @file_put_contents($file, json_encode($data), LOCK_EX);
    
    return $data['attempts'];
}

function reset_device_attempts() {
    $device_hash = get_device_hash();
    $file = sys_get_temp_dir() . '/bf_dev_' . $device_hash . '.json';
    if (file_exists($file)) {
        @unlink($file);
    }
}

// Redirect jika sudah login
if (isset($_SESSION['login']) && $_SESSION['login'] === true) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header("Location: index_admin.php");
    } else {
        header("Location: index.php");
    }
    exit;
}

$message = "";
$status = "";
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'login';
$login_success = false; 
$redirect_page = "";
$nama_sekolah_notif = "";

// =========================================================================
// PROSES POST DATA
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validasi CSRF Token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        catat_log("Validasi CSRF", "UNKNOWN", "DITOLAK (Potensi Serangan)");
        die("Akses ditolak: Token keamanan (CSRF) tidak valid atau sesi telah kedaluwarsa. Silakan refresh halaman.");
    }

    // Batasi panjang input untuk cegah DoS Payload
    $username = isset($_POST['username']) ? substr(trim($_POST['username']), 0, 50) : '';

    // --- LOGIKA REGISTER ---
    if ($mode === 'register') {
        $sekolah_raw = isset($_POST['sekolah_pilihan']) ? $_POST['sekolah_pilihan'] : '';
        
        if (!empty($sekolah_raw) && strpos($sekolah_raw, '|') !== false) {
            $sekolah_data = explode('|', $sekolah_raw);
            $id_sekolah   = trim($sekolah_data[0]);
            $nama_sekolah = trim($sekolah_data[1]);
        } else {
            $id_sekolah   = "";
            $nama_sekolah = "";
        }

        $password_raw = isset($_POST['password']) ? substr($_POST['password'], 0, 100) : '';

        if (strtolower($username) === 'admin') {
            $message = "Username 'admin' dicadangkan khusus untuk otoritas sistem!";
            $status = "error";
        } elseif (!preg_match('/^[a-zA-Z0-9_.-]{3,30}$/', $username)) {
            $message = "Username hanya boleh huruf, angka, titik, minus, dan underscore (3-30 karakter)!";
            $status = "error";
        } elseif (strlen($password_raw) < 8) {
            $message = "Password minimal harus 8 karakter!";
            $status = "error";
        } elseif (empty($id_sekolah) || empty($nama_sekolah)) {
            $message = "Silakan pilih sekolah terlebih dahulu!";
            $status = "error";
        } else {
            $password = password_hash($password_raw, PASSWORD_DEFAULT);

            // Prepared Statement Cek Username
            $stmt_cek_user = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ?");
            if ($stmt_cek_user) {
                mysqli_stmt_bind_param($stmt_cek_user, "s", $username);
                mysqli_stmt_execute($stmt_cek_user);
                mysqli_stmt_store_result($stmt_cek_user);
                
                if (mysqli_stmt_num_rows($stmt_cek_user) > 0) {
                    $message = "Username sudah terdaftar di sistem!";
                    $status = "error";
                } else {
                    // Prepared Statement Cek Sekolah
                    $stmt_cek_sekolah = mysqli_prepare($conn, "SELECT id FROM users WHERE id_sekolah = ?");
                    if ($stmt_cek_sekolah) {
                        mysqli_stmt_bind_param($stmt_cek_sekolah, "s", $id_sekolah);
                        mysqli_stmt_execute($stmt_cek_sekolah);
                        mysqli_stmt_store_result($stmt_cek_sekolah);
                        
                        if (mysqli_stmt_num_rows($stmt_cek_sekolah) > 0) {
                            $message = "Sekolah tersebut sudah terdaftar di sistem dengan akun lain!";
                            $status = "error";
                        } else {
                            // Prepared Statement Insert Data
                            $stmt_insert = mysqli_prepare($conn, "INSERT INTO users (id_sekolah, nama_sekolah, username, password) VALUES (?, ?, ?, ?)");
                            if ($stmt_insert) {
                                mysqli_stmt_bind_param($stmt_insert, "ssss", $id_sekolah, $nama_sekolah, $username, $password);
                                
                                if (mysqli_stmt_execute($stmt_insert)) {
                                    $message = "Akun berhasil dibuat! Silakan masuk.";
                                    $status = "success";
                                    $mode = 'login'; 
                                    catat_log("Registrasi", $username, "SUKSES");
                                } else {
                                    $message = "Pendaftaran gagal, terjadi gangguan pada sistem database.";
                                    $status = "error";
                                    catat_log("Registrasi", $username, "GAGAL DB");
                                }
                                mysqli_stmt_close($stmt_insert);
                            }
                        }
                        mysqli_stmt_close($stmt_cek_sekolah);
                    }
                }
                mysqli_stmt_close($stmt_cek_user);
            }
        }
    }

    // --- LOGIKA LOGIN ---
    elseif ($mode === 'login') {
        
        $sisa_menit = check_device_lockout(3, 15 * 60);
        if ($sisa_menit > 0) {
            $message = "Terlalu banyak percobaan gagal! Akses dari perangkat ini diblokir sementara. Coba lagi dalam $sisa_menit menit.";
            $status = "error";
            catat_log("Login Attempt", $username, "DIBLOKIR (Brute-Force Device)");
            goto skip_login;
        }

        $password = isset($_POST['password']) ? substr($_POST['password'], 0, 100) : '';

        $stmt_login = mysqli_prepare($conn, "SELECT * FROM users WHERE username = ?");
        if ($stmt_login) {
            mysqli_stmt_bind_param($stmt_login, "s", $username);
            mysqli_stmt_execute($stmt_login);
            $result = mysqli_stmt_get_result($stmt_login);
            
            // Prevention of Timing Attack
            $dummy_hash = '$2y$10$abcdefghijklmnopqrstuuGON13yNqD989sL6N/v8p0R3S3Z1S3u2';

            if ($result && mysqli_num_rows($result) === 1) {
                $row = mysqli_fetch_assoc($result);
                $db_password = isset($row['password']) ? $row['password'] : '';
                $is_password_valid = password_verify($password, $db_password);
            } else {
                $row = null;
                $is_password_valid = password_verify($password, $dummy_hash);
            }

            if ($row && $is_password_valid) {
                
                // Automatic Password Re-hash jika algoritma diperbarui
                if (password_needs_rehash($db_password, PASSWORD_DEFAULT)) {
                    $new_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt_up = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
                    if ($stmt_up) {
                        mysqli_stmt_bind_param($stmt_up, "si", $new_hash, $row['id']);
                        mysqli_stmt_execute($stmt_up);
                        mysqli_stmt_close($stmt_up);
                    }
                }

                reset_device_attempts();
                unset($_SESSION['login_attempts']);
                unset($_SESSION['lockout']);

                session_regenerate_id(true);
                
                $_SESSION['login'] = true;
                $_SESSION['id_user'] = $row['id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['user_agent'] = md5($_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN');
                
                // Penentuan Role (Mendukung kolom database 'role' maupun pengecekan username)
                $user_role = $row['role'] ?? ((strtolower($row['username']) === 'admin' || $row['id_sekolah'] == 0) ? 'admin' : 'user');

                if ($user_role === 'admin') {
                    $_SESSION['id_sekolah'] = '0'; 
                    $_SESSION['nama_sekolah'] = 'Dinas Pusat';
                    $_SESSION['role'] = 'admin'; 

                    $login_success = true;
                    $nama_sekolah_notif = 'Super Admin (Dinas Pusat)'; 
                    $redirect_page = 'index_admin.php'; 
                } else {
                    $_SESSION['id_sekolah'] = $row['id_sekolah'];
                    $_SESSION['nama_sekolah'] = $row['nama_sekolah']; 
                    $_SESSION['role'] = 'user'; 
                    
                    $login_success = true;
                    $nama_sekolah_notif = $row['nama_sekolah']; 
                    $redirect_page = 'index.php'; 
                }
                
                catat_log("Login", $username, "SUKSES");

            } else {
                $failed_count = record_failed_device_attempt(3, 15 * 60);
                $sisa_kesempatan = 3 - $failed_count;
                
                if ($sisa_kesempatan > 0) {
                    $message = "Akun tidak ditemukan atau kombinasi salah! (Sisa percobaan di perangkat ini: $sisa_kesempatan)";
                } else {
                    $message = "3 kali percobaan gagal! Akses dari perangkat ini diblokir selama 15 menit.";
                }
                
                $status = "error";
                catat_log("Login", $username, "GAGAL (Percobaan ke-$failed_count)");
            }

            mysqli_stmt_close($stmt_login);
        }
        
        skip_login:
    }
}

// Ambil data dropdown sekolah jika berada di form registrasi
$query_sekolah = false;
$sekolah_terdaftar_list = [];

if ($mode === 'register') {
    $query_sekolah = mysqli_query($conn, "SELECT id, nama_sekolah, kota_kab FROM kode_sekolah ORDER BY kota_kab ASC, nama_sekolah ASC");
    
    $get_registered = mysqli_query($conn, "SELECT DISTINCT id_sekolah FROM users WHERE id_sekolah IS NOT NULL AND id_sekolah != ''");
    if ($get_registered) {
        while ($reg_row = mysqli_fetch_assoc($get_registered)) {
            $sekolah_terdaftar_list[] = $reg_row['id_sekolah'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="logolog.jpeg">
    <title><?= ($mode=='login') ? 'Login' : 'Daftar' ?> | Sistem Belanja Modal</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --primary: #38bdf8;
            --primary-dark: #0284c7;
            --bg-gradient: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
        }
        body {
            background: var(--bg-gradient);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Plus Jakarta Sans', sans-serif;
            padding: 15px;
            margin: 0;
            box-sizing: border-box;
        }
        .auth-card {
            background: #ffffff;
            border: none;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.05);
            width: 100%;
            max-width: 400px;
            max-height: 96vh;
            overflow-y: auto;
        }
        .auth-card::-webkit-scrollbar {
            width: 0px;
            background: transparent;
        }
        .auth-header {
            background: #ffffff;
            padding: 15px 30px 10px 30px;
            text-align: center;
        }
        .img-top {
            width: 160px;
            height: auto;
            max-width: 100%;
            object-fit: contain;
            margin-bottom: -15px;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }
        .img-banner {
            width: 100%;
            max-height: 75px;
            object-fit: cover;
            border-radius: 12px;
            margin-top: 5px;
            margin-bottom: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }
        .auth-body { 
            padding: 0 30px 25px 30px; 
        }
        .form-label {
            font-weight: 600;
            font-size: 0.8rem;
            color: #475569;
            margin-bottom: 4px;
            letter-spacing: 0.5px;
        }
        .input-group-custom {
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            padding: 2px 12px;
            display: flex;
            align-items: center;
            transition: all 0.2s;
        }
        .input-group-custom:focus-within {
            border-color: var(--primary);
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(56, 189, 248, 0.15);
        }
        .input-group-custom i { color: #94a3b8; margin-right: 10px; }
        .input-group-custom select, .input-group-custom input {
            border: none;
            background: transparent;
            width: 100%;
            padding: 8px 0;
            font-weight: 500;
            outline: none;
            font-size: 0.9rem;
            color: #1e293b;
        }
        .input-group-custom select {
            text-overflow: ellipsis;
            white-space: nowrap;
            overflow: hidden;
        }
        .input-group-custom select optgroup {
            font-weight: 700;
            color: var(--primary-dark);
            background: #f0f9ff;
        }
        .input-group-custom select option {
            font-weight: 500;
            color: #1e293b;
            background: #ffffff;
        }
        .input-group-custom select option:disabled {
            color: #94a3b8 !important;
            background: #f1f5f9;
            font-style: italic;
        }
        .btn-auth {
            background: var(--primary);
            border: none;
            border-radius: 12px;
            padding: 10px;
            color: white;
            font-weight: 700;
            width: 100%;
            margin-top: 5px;
            transition: all 0.3s;
            font-size: 0.9rem;
            box-shadow: 0 4px 12px rgba(56, 189, 248, 0.2);
        }
        .btn-auth:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(2, 132, 199, 0.3);
        }
        .alert-custom {
            border-radius: 10px;
            border: none;
            font-weight: 600;
            font-size: 0.8rem;
            padding: 10px;
        }
    </style>
</head>
<body>

<div class="auth-card shadow">
    <div class="auth-header">
        <img src="logolog.jpeg" alt="Logo" class="img-top" onerror="this.style.display='none'">
        <h3 class="fw-bold text-dark mb-0 fs-4"><?= ($mode=='login') ? 'Selamat Datang' : 'Buat Akun' ?></h3>
        <img src="diptanew.jpeg" alt="Banner" class="img-banner" onerror="this.style.display='none'">
    </div>

    <div class="auth-body">
        <?php if(!empty($message) && $status === 'success'): ?>
            <div class="alert alert-success alert-custom mb-2">
                <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php elseif(!empty($message) && $status === 'error'): ?>
            <div class="alert alert-danger alert-custom mb-2">
                <i class="bi bi-exclamation-circle-fill me-2"></i> <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            
            <!-- INPUT HIDDEN CSRF TOKEN -->
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

            <?php if($mode == 'register' && $query_sekolah): ?>
            <div class="mb-2">
                <label class="form-label">PILIH SEKOLAH</label>
                <div class="input-group-custom">
                    <i class="bi bi-building"></i>
                    <select name="sekolah_pilihan" required>
                        <option value="">-- Pilih Nama Sekolah --</option>
                        <?php 
                        if (mysqli_num_rows($query_sekolah) > 0) {
                            $current_region = "";
                            while ($row_sekolah = mysqli_fetch_assoc($query_sekolah)) {
                                if (empty($row_sekolah['id']) || empty($row_sekolah['nama_sekolah'])) {
                                    continue;
                                }

                                $kota_kab = !empty($row_sekolah['kota_kab']) ? trim($row_sekolah['kota_kab']) : "Lainnya";
                                
                                if ($current_region != $kota_kab) {
                                    if ($current_region != "") {
                                        echo "</optgroup>";
                                    }
                                    $current_region = $kota_kab;
                                    echo "<optgroup label='" . htmlspecialchars(strtoupper($current_region), ENT_QUOTES, 'UTF-8') . "'>";
                                }
                                
                                $is_registered = in_array($row_sekolah['id'], $sekolah_terdaftar_list);
                                $disabled_attr = $is_registered ? "disabled" : "";
                                $display_name  = $is_registered ? $row_sekolah['nama_sekolah'] . " (Sudah Terdaftar)" : $row_sekolah['nama_sekolah'];

                                $combined_value = $row_sekolah['id'] . "|" . $row_sekolah['nama_sekolah'];
                                echo "<option value='". htmlspecialchars($combined_value, ENT_QUOTES, 'UTF-8') ."' " . $disabled_attr . ">" . htmlspecialchars($display_name, ENT_QUOTES, 'UTF-8') . "</option>";
                            }
                            echo "</optgroup>"; 
                        } else {
                            echo "<option value=''>Gagal memuat data sekolah</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>
            <?php endif; ?>

            <div class="mb-2">
                <label class="form-label">USERNAME</label>
                <div class="input-group-custom">
                    <i class="bi bi-person-fill"></i>
                    <input type="text" name="username" placeholder="Username" required autocomplete="username">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">PASSWORD</label>
                <div class="input-group-custom">
                    <i class="bi bi-key-fill"></i>
                    <input type="password" name="password" placeholder="••••••••" required autocomplete="current-password">
                </div>
            </div>

            <button type="submit" class="btn-auth">
                <?= ($mode == 'login') ? 'MASUK SEKARANG' : 'DAFTAR AKUN' ?>
            </button>
        </form>

        <div class="text-center mt-3 pt-2 border-top">
            <?php if($mode == 'login'): ?>
                <span class="small text-muted">Belum punya akun?</span>
                <a href="?mode=register" class="text-decoration-none fw-bold ms-1 text-primary">Daftar</a>
            <?php else: ?>
                <span class="small text-muted">Sudah punya akun?</span>
                <a href="?mode=login" class="text-decoration-none fw-bold ms-1 text-primary">Kembali untuk Masuk</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    <?php if($login_success && !empty($redirect_page)): ?>
    Swal.fire({
        title: 'Login Berhasil!',
        text: <?= json_encode($nama_sekolah_notif) ?>,
        icon: 'success',
        timer: 2000,
        showConfirmButton: false,
        timerProgressBar: true
    }).then(() => {
        window.location.replace(<?= json_encode($redirect_page) ?>);
    });
    <?php elseif(!empty($message) && $status === 'error' && $mode === 'login'): ?>
    Swal.fire({
        title: 'Gagal Masuk!',
        text: <?= json_encode($message) ?>,
        icon: 'error',
        confirmButtonColor: '#38bdf8',
        confirmButtonText: 'Coba Lagi'
    });
    <?php endif; ?>
});
</script>

</body>
</html>