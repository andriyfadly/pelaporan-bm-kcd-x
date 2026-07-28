<?php
// === KEAMANAN TAMBAHAN 1: Proteksi Session & Cookie Strict ===
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

// === KEAMANAN TAMBAHAN 2: Autentikasi Pengguna & Akses Sesi (KHUSUS ADMIN) ===
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true || ($_SESSION['role'] ?? '') !== 'admin') {
    if (isset($_GET['ajax']) || (isset($_POST['action']) && $_POST['action'] === 'import_excel_master')) {
        header('HTTP/1.1 403 Forbidden');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => '❌ Akses ditolak! Sesi tidak valid atau Anda bukan Admin.']);
        exit;
    }
    header("Location: login.php");
    exit;
}

// === KEAMANAN TAMBAHAN 3: Generate Token CSRF ===
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// === KEAMANAN TAMBAHAN 4: Security Headers ===
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// ===================== KONEKSI DB =====================
$host = "localhost";
$user = "root";
$pass = "";
$db   = "db_inventaris";

// Sembunyikan Detail Error Database dari Publik
mysqli_report(MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR);
try {
    $conn = new mysqli($host, $user, $pass, $db);
    $conn->set_charset("utf8mb4"); 
} catch (Exception $e) {
    if (isset($_GET['ajax']) || (isset($_POST['action']))) {
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => '❌ Terjadi kendala koneksi ke server database.']);
        exit;
    }
    die("Koneksi gagal: Server sedang dalam pemeliharaan."); 
}

// Sanitasi nama file saat ini agar aman dari Path Traversal / Reflected XSS
$current_file = htmlspecialchars(basename($_SERVER['PHP_SELF']), ENT_QUOTES, 'UTF-8');

// ===================== LOAD PHPSPREADSHEET =====================
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$message = '';
$message_type = 'info';

// ===================== FUNCTION AMBIL DATA (KODE & NAMA/URAIAN) =====================
function ambilData($conn, $search=''){
    // 100% Anti SQL Injection menggunakan Prepared Statements
    if($search !== ''){
        $sql = "SELECT * FROM kode_barang WHERE kode_barang LIKE ? OR uraian LIKE ? OR jenis_aset LIKE ? ORDER BY kode_barang ASC";
        if($stmt = $conn->prepare($sql)) {
            $likeSearch = "%" . $search . "%";
            $stmt->bind_param("sss", $likeSearch, $likeSearch, $likeSearch);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();
            return $result;
        }
        return false;
    } else {
        $sql = "SELECT * FROM kode_barang ORDER BY kode_barang ASC";
        return $conn->query($sql);
    }
}

// ===================== AJAX SEARCH (REAL-TIME FILTER) =====================
if(isset($_GET['ajax'])){
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    
    $q = trim($_GET['q'] ?? '');
    $res = ambilData($conn, $q);

    if($res && $res->num_rows > 0){
        while($row = $res->fetch_assoc()){
            // Strict Anti-XSS (ENT_QUOTES & UTF-8)
            echo "<tr>
            <td class='fw-bold text-primary'>".htmlspecialchars($row['kode_barang'] ?? '', ENT_QUOTES, 'UTF-8')."</td>
            <td>".htmlspecialchars($row['uraian'] ?? '', ENT_QUOTES, 'UTF-8')."</td>
            <td><span class='badge bg-light text-dark border'>".htmlspecialchars($row['kodering_aset'] ?? '', ENT_QUOTES, 'UTF-8')."</span></td>
            <td>".htmlspecialchars($row['jenis_aset'] ?? '', ENT_QUOTES, 'UTF-8')."</td>
            <td class='text-center'>".htmlspecialchars($row['umur_ekonomis'] ?? '', ENT_QUOTES, 'UTF-8')."</td>
            </tr>";
        }
    } else {
        echo "<tr><td colspan='5' class='text-center text-danger py-4'><i class='bi bi-exclamation-circle me-1'></i> Data '" . htmlspecialchars($q, ENT_QUOTES, 'UTF-8') . "' tidak ditemukan</td></tr>";
    }
    exit;
}

// ===================== IMPORT EXCEL VIA AJAX POST =====================
if(isset($_POST['action']) && $_POST['action'] === 'import_excel_master'){
    header('Content-Type: application/json; charset=utf-8');
    
    // Validasi Anti-CSRF Token
    $csrf = $_POST['csrf_token'] ?? '';
    if(!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
        echo json_encode(['status' => 'error', 'message' => '❌ Sesi permintaan tidak valid (CSRF mismatch)!']);
        exit;
    }

    // Validasi Berkas Unggahan
    if(isset($_FILES['file_excel']['tmp_name']) && is_uploaded_file($_FILES['file_excel']['tmp_name'])){
        
        // Pembatasan Ukuran File (Maksimal 10 MB)
        if ($_FILES['file_excel']['size'] > 10 * 1024 * 1024) {
            echo json_encode(['status' => 'error', 'message' => '❌ Ukuran file terlalu besar! Maksimal 10 MB.']);
            exit;
        }

        $ext = strtolower(pathinfo($_FILES['file_excel']['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['xlsx', 'xls'];

        if(in_array($ext, $allowed_ext, true)){
            try {
                $file = $_FILES['file_excel']['tmp_name'];
                $spreadsheet = IOFactory::load($file);
                $sheetData = $spreadsheet->getActiveSheet()->toArray();

                $stmt = $conn->prepare("
                    INSERT INTO kode_barang
                    (kode_barang, uraian, kodering_aset, jenis_aset, umur_ekonomis)
                    VALUES (?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE
                        uraian=VALUES(uraian),
                        kodering_aset=VALUES(kodering_aset),
                        jenis_aset=VALUES(jenis_aset),
                        umur_ekonomis=VALUES(umur_ekonomis)
                ");

                $count = 0;
                for($i = 1; $i < count($sheetData); $i++){
                    if(!empty($sheetData[$i][0])){
                        // Sanitasi dan pembersihan tag HTML/Script dari isi sel Excel
                        $kode_barang   = htmlspecialchars(trim(strip_tags((string)($sheetData[$i][0] ?? ''))), ENT_QUOTES, 'UTF-8');
                        $uraian        = htmlspecialchars(trim(strip_tags((string)($sheetData[$i][1] ?? ''))), ENT_QUOTES, 'UTF-8');
                        $kodering_aset = htmlspecialchars(trim(strip_tags((string)($sheetData[$i][2] ?? ''))), ENT_QUOTES, 'UTF-8');
                        $jenis_aset    = htmlspecialchars(trim(strip_tags((string)($sheetData[$i][3] ?? ''))), ENT_QUOTES, 'UTF-8');
                        $umur_ekonomis = (int)($sheetData[$i][4] ?? 0);

                        $stmt->bind_param("ssssi", $kode_barang, $uraian, $kodering_aset, $jenis_aset, $umur_ekonomis);
                        $stmt->execute();
                        $count++;
                    }
                }
                $stmt->close();
                echo json_encode(['status' => 'success', 'message' => "✅ Berhasil mengimpor $count data ke database!"]);
                exit;
            } catch(Exception $e) {
                echo json_encode(['status' => 'error', 'message' => "❌ Gagal membaca atau menyimpan file Excel!"]);
                exit;
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => "❌ Format berkas tidak didukung! Harap unggah file .xlsx atau .xls"]);
            exit;
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => "❌ Silakan sertakan file Excel yang valid!"]);
        exit;
    }
}

// LOAD DATA UTAMA
$result = ambilData($conn);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Kode Barang | SINVENTARIS</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #2563eb;
            --bg-body: #f8fafc;
        }

        body { 
            background: var(--bg-body); 
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: #1e293b;
        }

        .card {
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            background: #ffffff;
        }

        .info-box {
            background: #eff6ff;
            border-left: 5px solid var(--primary);
            padding: 15px;
            border-radius: 15px;
            font-size: 14px;
        }

        .table-wrapper {
            max-height: 500px;
            overflow-y: auto;
            border-radius: 12px;
        }

        .soft-table thead th {
            position: sticky;
            top: 0;
            background: #ffffff;
            z-index: 10;
            border-bottom: 2px solid #f1f5f9;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
            color: #64748b;
            padding: 15px;
        }

        .soft-table td {
            padding: 15px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
        }

        .soft-table tbody tr:hover {
            background: #f8fafc;
        }

        .search-box {
            border-radius: 12px;
            padding: 12px 20px;
            border: 1.5px solid #e2e8f0;
            transition: 0.3s;
        }

        .search-box:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }

        .btn-primary {
            background: var(--primary);
            border: none;
            padding: 12px;
            border-radius: 12px;
            font-weight: 600;
        }

        .btn-outline-secondary {
            border-radius: 10px;
            padding: 8px 16px;
        }

        .table-wrapper::-webkit-scrollbar { width: 6px; }
        .table-wrapper::-webkit-scrollbar-track { background: #f1f5f9; }
        .table-wrapper::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    </style>
</head>

<body>
<div class="container py-5">

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1">
                <i class="bi bi-database-add text-primary me-2"></i>
                Master Kode Barang
            </h3>
            <p class="text-secondary mb-0">Kelola dan import data referensi barang inventaris</p>
        </div>

        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Dashboard
        </a>
    </div>

    <div id="alert-zone"></div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card p-4 mb-4">
                <h6 class="fw-bold mb-3">
                    <i class="bi bi-upload me-2 text-primary"></i>Upload File Excel
                </h6>

                <form id="formImportExcelMaster" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="import_excel_master">
                    <!-- Token Proteksi CSRF -->
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                    
                    <div class="mb-3">
                        <input type="file" name="file_excel" class="form-control" accept=".xlsx,.xls" required>
                    </div>

                    <button type="submit" id="btnSubmitMaster" class="btn btn-primary w-100 shadow-sm">
                        <i class="bi bi-cloud-upload me-2"></i> Import Sekarang
                    </button>
                </form>

                <div class="info-box mt-4">
                    <strong class="d-block mb-2"><i class="bi bi-info-circle me-1"></i> Panduan Import:</strong>
                    <ul class="ps-3 mb-0 text-secondary">
                        <li>Gunakan file format <strong>.xlsx</strong></li>
                        <li>Baris pertama adalah <strong>Header</strong></li>
                        <li>Sistem menggunakan <strong>Upsert</strong> (Jika kode sama, data akan diupdate)</li>
                    </ul>
                </div>
            </div>

            <div class="card p-4 text-center">
                <h6 class="fw-bold mb-3 text-start">
                    <i class="bi bi-search text-success me-2"></i>Pencarian Cepat
                </h6>
                <input type="text" id="searchBox" class="form-control search-box" placeholder="Cari Kode atau Nama Barang...">
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card p-0 overflow-hidden">
                <div class="p-4 border-bottom bg-white d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0">Daftar Referensi Kode</h6>
                    <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill small">Real-time Table</span>
                </div>
                
                <div class="table-responsive table-wrapper">
                    <table class="table soft-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Kode Barang</th>
                                <th>Nama Barang (Uraian)</th>
                                <th>Kodering Aset</th>
                                <th>Jenis Aset</th>
                                <th class="text-center">Umur</th>
                            </tr>
                        </thead>
                        <tbody id="dataTable">
                            <?php if($result && $result->num_rows > 0): ?>
                                <?php while($row=$result->fetch_assoc()): ?>
                                <tr>
                                    <td class="fw-bold text-primary"><?= htmlspecialchars($row['kode_barang'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($row['uraian'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($row['kodering_aset'] ?? '', ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td><?= htmlspecialchars($row['jenis_aset'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-center"><?= htmlspecialchars($row['umur_ekonomis'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center text-muted py-5">Belum ada data tersedia</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Menyimpan variabel nama file dinamis dari backend PHP ke JavaScript
    const currentFileName = "<?= $current_file; ?>";

    // ----------------------------------------------------
    // LOGIKA 1: LIVE REAL-TIME SEARCH (MENCARI KODE & NAMA BARANG)
    // ----------------------------------------------------
    let delay;
    const searchBox = document.getElementById("searchBox");
    const dataTable = document.getElementById("dataTable");

    searchBox.addEventListener("input", function(){
        clearTimeout(delay);
        let val = this.value;
        
        dataTable.style.opacity = "0.4"; // Efek loading transparan halus
        
        delay = setTimeout(() => {
            // Menggunakan variabel nama file dinamis agar routing AJAX Fetch selalu akurat
            fetch(`${currentFileName}?ajax=1&q=` + encodeURIComponent(val))
            .then(r => {
                if(!r.ok) throw new Error();
                return r.text();
            })
            .then(d => {
                dataTable.innerHTML = d;
                dataTable.style.opacity = "1";
            })
            .catch(err => {
                console.error("Gagal memuat pencarian data: ", err);
                dataTable.style.opacity = "1";
            });
        }, 200); // Kecepatan respon ketik diatur pada 200ms
    });

    // ----------------------------------------------------
    // LOGIKA 2: IMPORT FILE EXCEL VIA AJAX
    // ----------------------------------------------------
    document.getElementById('formImportExcelMaster').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const btnSubmit = document.getElementById('btnSubmitMaster');
        const alertZone = document.getElementById('alert-zone');
        
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Memproses Berkas...';
        
        const formData = new FormData(this);
        
        try {
            const response = await fetch(currentFileName, {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            
            if (result.status === 'success') {
                alertZone.innerHTML = `
                    <div class="alert alert-success border-0 shadow-sm alert-dismissible fade show mb-4" style="border-radius: 15px;">
                        ${result.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>`;
                
                searchBox.value = '';
                if (typeof loadPage === "function") {
                    setTimeout(() => { loadPage(currentFileName, 'Master Kode Barang', false); }, 1200);
                } else {
                    setTimeout(() => { location.reload(); }, 1200);
                }
            } else {
                alertZone.innerHTML = `
                    <div class="alert alert-danger border-0 shadow-sm alert-dismissible fade show mb-4" style="border-radius: 15px;">
                        ${result.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>`;
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = '<i class="bi bi-cloud-upload me-2"></i> Import Sekarang';
            }
        } catch (error) {
            alertZone.innerHTML = `
                <div class="alert alert-danger border-0 shadow-sm alert-dismissible fade show mb-4" style="border-radius: 15px;">
                    ❌ Terjadi kendala komunikasi dengan server database.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>`;
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = '<i class="bi bi-cloud-upload me-2"></i> Import Sekarang';
        }
    });
</script>
</body>
</html>