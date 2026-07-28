<?php
// === KEAMANAN TAMBAHAN 1: Cookie & Session Security Flags ===
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

// === KEAMANAN TAMBAHAN 2: HTTP Security Headers ===
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// === KEAMANAN TAMBAHAN 3: CSRF Token Generation ===
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Validasi Login
if (!isset($_SESSION['login'])) {
    if (isset($_GET['action']) || isset($_POST['action'])) {
        header('HTTP/1.1 403 Forbidden');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => '❌ Akses ditolak! Sesi telah berakhir atau tidak valid.']);
        exit;
    }
    header("Location: login.php");
    exit;
}

// Sanitasi ID Sekolah & Role
$id_sekolah_session = htmlspecialchars($_SESSION['id_sekolah'] ?? '', ENT_QUOTES, 'UTF-8');
$role_user          = htmlspecialchars($_SESSION['role'] ?? 'user', ENT_QUOTES, 'UTF-8');

// === KEAMANAN TAMBAHAN: Proteksi Akses Khusus Admin ===
if ($role_user !== 'admin') {
    if (isset($_GET['action']) || isset($_POST['action'])) {
        header('HTTP/1.1 403 Forbidden');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => '❌ Akses ditolak! Halaman ini hanya dapat diakses oleh Admin.']);
        exit;
    }
    header("Location: index.php");
    exit;
}

// === KEAMANAN TAMBAHAN 4: Sembunyikan Pesan Error Database bawaan ===
mysqli_report(MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR);
try {
    include "koneksi.php";
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    if (isset($_GET['action']) || isset($_POST['action'])) {
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => '❌ Server sedang sibuk atau dalam pemeliharaan.']);
        exit;
    }
    die("Koneksi gagal: Server sedang sibuk atau dalam pemeliharaan.");
}

// === HELPER FUNCTION: Helper Exec Prepared Query Dynamic ===
if (!function_exists('execPreparedQuery')) {
    function execPreparedQuery($conn, $sql, $types = '', $params = []) {
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            return false;
        }
        if (!empty($types) && !empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        return $result;
    }
}

// === HELPER FUNCTION: Validasi CSRF Token ===
function verifyCsrfToken() {
    $csrf = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => '❌ Permintaan tidak valid! Token CSRF tidak cocok.']);
        exit;
    }
}

// =========================================================================
// LOGIKA PENENTUAN BULAN DEFAULT (Bulan Realisasi = Bulan Sekarang Minus 1)
// =========================================================================
$target_month_num = date('n') - 1; 
if ($target_month_num == 0) {
    $target_month_num = 12; 
}

$indonesian_months = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
$target_month_name = $indonesian_months[$target_month_num];

$list_bulan = [];
if ($role_user === 'admin') {
    $qBulan = mysqli_query($conn, "SELECT DISTINCT bulan FROM data_barang_acuan WHERE bulan != ''");
} else {
    $qBulan = execPreparedQuery($conn, "SELECT DISTINCT bulan FROM data_barang_acuan WHERE id_sekolah=? AND bulan != ''", "s", [$id_sekolah_session]);
}

if($qBulan) {
    while($b = mysqli_fetch_assoc($qBulan)) {
        $list_bulan[] = $b['bulan'];
    }
}

if (isset($_GET['action']) && $_GET['action'] == 'filter_table_ajax' && isset($_GET['filter_bulan'])) {
    $_SESSION['last_filter_bulan'] = htmlspecialchars($_GET['filter_bulan'], ENT_QUOTES, 'UTF-8');
}

if (isset($_SESSION['last_filter_bulan'])) {
    $default_filter_bulan = $_SESSION['last_filter_bulan'];
} else {
    if (in_array($target_month_num, $list_bulan)) {
        $default_filter_bulan = $target_month_num;
    } elseif (in_array($target_month_name, $list_bulan)) {
        $default_filter_bulan = $target_month_name;
    } else {
        $default_filter_bulan = !empty($list_bulan) ? $list_bulan[0] : '';
    }
    $_SESSION['last_filter_bulan'] = $default_filter_bulan;
}


// ==========================================
// LOGIKA 1: PROSES IMPORT VIA AJAX POST
// ==========================================
if (isset($_POST['action']) && $_POST['action'] === 'import_excel_ajax') {
    header('Content-Type: application/json; charset=utf-8');
    verifyCsrfToken();
    
    if (isset($_FILES['file_template']['tmp_name']) && is_uploaded_file($_FILES['file_template']['tmp_name'])) {
        
        // Pembatasan Ukuran File (Maksimal 10 MB)
        if ($_FILES['file_template']['size'] > 10 * 1024 * 1024) {
            echo json_encode(['status' => 'error', 'message' => '❌ Ukuran file terlalu besar! Maksimal 10 MB.']);
            exit;
        }

        $allowed_extension = array('xls', 'xlsx');
        $file_array = explode(".", $_FILES['file_template']['name']);
        $file_extension = strtolower(end($file_array));

        if (in_array($file_extension, $allowed_extension, true)) {
            if (file_exists('vendor/autoload.php')) {
                require 'vendor/autoload.php';
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Library PhpSpreadsheet belum diinstal!']);
                exit;
            }
            
            try {
                $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader(ucfirst($file_extension));
                $spreadsheet = $reader->load($_FILES['file_template']['tmp_name']);
                $worksheet = $spreadsheet->getSheetByName('Template_Import') ?: $spreadsheet->getActiveSheet();
                
                $highestRow = $worksheet->getHighestRow();
                $sukses = 0;
                $gagal = 0;

                $stmtInsert = $conn->prepare("INSERT INTO data_barang_acuan (id_sekolah, satuan_pendidikan, npsn, tanggal, kodering, bku, uraian, nominal, bulan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmtCariId = $conn->prepare("SELECT id FROM kode_sekolah WHERE LOWER(REPLACE(REPLACE(nama_sekolah, ' ', ''), CHAR(160), '')) = ? LIMIT 1");
                $stmtCadangan = $conn->prepare("SELECT id FROM kode_sekolah WHERE LOWER(REPLACE(REPLACE(nama_sekolah, ' ', ''), CHAR(160), '')) LIKE ? LIMIT 1");

                for ($row = 2; $row <= $highestRow; $row++) {
                    $satuan_pendidikan = htmlspecialchars(trim(strip_tags((string)($worksheet->getCellByColumnAndRow(1, $row)->getValue() ?? ''))), ENT_QUOTES, 'UTF-8');
                    $npsn              = htmlspecialchars(trim(strip_tags((string)($worksheet->getCellByColumnAndRow(2, $row)->getValue() ?? ''))), ENT_QUOTES, 'UTF-8');
                    
                    if(empty($npsn) && empty($satuan_pendidikan)) continue;

                    if ($role_user === 'admin') {
                        $clean_nama_excel = preg_replace('/[\s\x{00a0}]+/u', '', strtolower($satuan_pendidikan));
                        
                        $stmtCariId->bind_param("s", $clean_nama_excel);
                        $stmtCariId->execute();
                        $res_cari_id = $stmtCariId->get_result();
                        
                        if ($res_cari_id && mysqli_num_rows($res_cari_id) > 0) {
                            $row_id = mysqli_fetch_assoc($res_cari_id);
                            $id_sekolah_insert = $row_id['id'];
                        } else {
                            $like_clean_nama = "%" . $clean_nama_excel . "%";
                            $stmtCadangan->bind_param("s", $like_clean_nama);
                            $stmtCadangan->execute();
                            $res_cadangan = $stmtCadangan->get_result();
                            
                            if ($res_cadangan && mysqli_num_rows($res_cadangan) > 0) {
                                $row_id_cadangan = mysqli_fetch_assoc($res_cadangan);
                                $id_sekolah_insert = $row_id_cadangan['id'];
                            } else {
                                $id_sekolah_insert = 0;
                            }
                        }
                    } else {
                        $id_sekolah_insert = $id_sekolah_session;
                    }
                    
                    $cellTanggal = $worksheet->getCellByColumnAndRow(3, $row);
                    $valTanggal  = $cellTanggal->getValue();
                    $tanggal     = '';

                    if (!empty($valTanggal)) {
                        if (\PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cellTanggal)) {
                            $dateTimeObj = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($valTanggal);
                            $tanggal     = $dateTimeObj->format('Y-m-d');
                        } else {
                            $tanggal = trim($valTanggal);
                        }
                    }
                    $tanggal = htmlspecialchars(trim(strip_tags($tanggal)), ENT_QUOTES, 'UTF-8');

                    $kodering = htmlspecialchars(trim(strip_tags((string)($worksheet->getCellByColumnAndRow(4, $row)->getValue() ?? ''))), ENT_QUOTES, 'UTF-8');
                    $bku      = htmlspecialchars(trim(strip_tags((string)($worksheet->getCellByColumnAndRow(5, $row)->getValue() ?? ''))), ENT_QUOTES, 'UTF-8');
                    $uraian   = htmlspecialchars(trim(strip_tags((string)($worksheet->getCellByColumnAndRow(6, $row)->getValue() ?? ''))), ENT_QUOTES, 'UTF-8');
                    $nominal  = htmlspecialchars(trim(strip_tags((string)($worksheet->getCellByColumnAndRow(7, $row)->getValue() ?? ''))), ENT_QUOTES, 'UTF-8');
                    $bulan    = htmlspecialchars(trim(strip_tags((string)($worksheet->getCellByColumnAndRow(8, $row)->getValue() ?? ''))), ENT_QUOTES, 'UTF-8');

                    if(empty($uraian)) continue;

                    $stmtInsert->bind_param("sssssssss", $id_sekolah_insert, $satuan_pendidikan, $npsn, $tanggal, $kodering, $bku, $uraian, $nominal, $bulan);
                    
                    if ($stmtInsert->execute()) {
                        $sukses++;
                    } else {
                        $gagal++;
                    }
                }
                
                $stmtInsert->close();
                $stmtCariId->close();
                $stmtCadangan->close();

                echo json_encode(['status' => 'success', 'message' => "Berhasil mengimpor data! (Sukses: $sukses, Gagal: $gagal)"]);
                exit;
            } catch (Exception $e) {
                echo json_encode(['status' => 'error', 'message' => 'Error membaca file: Gagal memproses data.']);
                exit;
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Format file tidak didukung. Harap gunakan file .xlsx atau .xls!']);
            exit;
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Silakan pilih file Excel yang valid terlebih dahulu!']);
        exit;
    }
}

// ==========================================
// LOGIKA 2: PROSES HAPUS PER ITEM (VIA AJAX)
// ==========================================
if (isset($_POST['action']) && $_POST['action'] === 'hapus_item_ajax') {
    header('Content-Type: application/json; charset=utf-8');
    verifyCsrfToken();

    $id_hapus = (int)$_POST['id'];
    
    if ($role_user === 'admin') {
        $stmtHapus = $conn->prepare("DELETE FROM data_barang_acuan WHERE id = ?");
        $stmtHapus->bind_param("i", $id_hapus);
    } else {
        $stmtHapus = $conn->prepare("DELETE FROM data_barang_acuan WHERE id = ? AND id_sekolah = ?");
        $stmtHapus->bind_param("is", $id_hapus, $id_sekolah_session);
    }
    
    if ($stmtHapus->execute()) {
        $stmtHapus->close();
        echo json_encode(['status' => 'success', 'message' => 'Item acuan berhasil dihapus.']);
    } else {
        $stmtHapus->close();
        echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus item dari database.']);
    }
    exit;
}

// ==========================================
// LOGIKA 3: PROSES HAPUS DATA BERDASARKAN FILTER BULAN (VIA AJAX)
// ==========================================
if (isset($_POST['action']) && $_POST['action'] === 'hapus_semua_ajax') {
    header('Content-Type: application/json; charset=utf-8');
    verifyCsrfToken();
    
    $bulan_filter = isset($_POST['filter_bulan']) ? htmlspecialchars(trim(strip_tags($_POST['filter_bulan'])), ENT_QUOTES, 'UTF-8') : '';

    if ($role_user === 'admin') {
        if (!empty($bulan_filter)) {
            $stmtDelAll = $conn->prepare("DELETE FROM data_barang_acuan WHERE bulan = ?");
            $stmtDelAll->bind_param("s", $bulan_filter);
        } else {
            $stmtDelAll = $conn->prepare("DELETE FROM data_barang_acuan WHERE 1=1");
        }
        $msg_sukses = 'Data acuan dari semua sekolah';
    } else {
        if (!empty($bulan_filter)) {
            $stmtDelAll = $conn->prepare("DELETE FROM data_barang_acuan WHERE id_sekolah = ? AND bulan = ?");
            $stmtDelAll->bind_param("ss", $id_sekolah_session, $bulan_filter);
        } else {
            $stmtDelAll = $conn->prepare("DELETE FROM data_barang_acuan WHERE id_sekolah = ?");
            $stmtDelAll->bind_param("s", $id_sekolah_session);
        }
        $msg_sukses = 'Data acuan sekolah Anda';
    }

    if (!empty($bulan_filter)) {
        $msg_sukses .= " untuk bulan <strong>" . htmlspecialchars($bulan_filter, ENT_QUOTES, 'UTF-8') . "</strong>";
    } else {
        $msg_sukses .= " untuk <strong>Semua Bulan</strong>";
    }
    $msg_sukses .= " berhasil dikosongkan!";

    if ($stmtDelAll->execute()) {
        $stmtDelAll->close();
        echo json_encode(['status' => 'success', 'message' => $msg_sukses]);
    } else {
        $stmtDelAll->close();
        echo json_encode(['status' => 'error', 'message' => 'Gagal mengosongkan data acuan.']);
    }
    exit;
}

// ==========================================
// LOGIKA 4: LIVE SEARCH / FILTER TABLE VIEW (PREPARED STATEMENTS 100%)
// ==========================================
if (isset($_GET['action']) && $_GET['action'] === 'filter_table_ajax') {
    header('Content-Type: application/json; charset=utf-8');
    
    $search_satuan = trim(strip_tags($_GET['search_satuan'] ?? ''));
    $filter_bulan  = trim(strip_tags($_GET['filter_bulan'] ?? ''));
    
    $limit = 50;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) $page = 1;
    $offset = ($page - 1) * $limit;

    // Konstruksi Query Dinamis dengan Prepared Statement
    $whereParts = [];
    $params = [];
    $types = "";

    if ($role_user !== 'admin') {
        $whereParts[] = "id_sekolah = ?";
        $params[] = $id_sekolah_session;
        $types .= "s";
    } else {
        $whereParts[] = "1=1";
    }

    if (!empty($search_satuan)) {
        $whereParts[] = "satuan_pendidikan LIKE ?";
        $params[] = "%" . $search_satuan . "%";
        $types .= "s";
    }

    if (!empty($filter_bulan)) {
        $whereParts[] = "bulan = ?";
        $params[] = $filter_bulan;
        $types .= "s";
    }

    $whereClause = "WHERE " . implode(" AND ", $whereParts);

    // 1. Count Records
    $sql_count = "SELECT COUNT(*) as total FROM data_barang_acuan $whereClause";
    $res_count = execPreparedQuery($conn, $sql_count, $types, $params);
    $total_records = $res_count ? ($res_count->fetch_assoc()['total'] ?? 0) : 0;
    $total_pages = ceil($total_records / $limit);

    // 2. Sum Nominal
    $sql_sum = "SELECT SUM(nominal) as total_sum FROM data_barang_acuan $whereClause";
    $res_sum = execPreparedQuery($conn, $sql_sum, $types, $params);
    $total_nominal_filtered = $res_sum ? ($res_sum->fetch_assoc()['total_sum'] ?? 0) : 0;

    // 3. Count Distinct NPSN (Sekolah)
    $sql_school_count = "SELECT COUNT(DISTINCT npsn) as total_sekolah FROM data_barang_acuan $whereClause";
    $res_school_count = execPreparedQuery($conn, $sql_school_count, $types, $params);
    $total_sekolah_filtered = $res_school_count ? ($res_school_count->fetch_assoc()['total_sekolah'] ?? 0) : 0;

    // 4. Main Data Query with LIMIT & OFFSET
    $sql = "SELECT * FROM data_barang_acuan $whereClause ORDER BY id DESC LIMIT ? OFFSET ?";
    $paramsWithLimit = array_merge($params, [$limit, $offset]);
    $typesWithLimit = $types . "ii";

    $qData = execPreparedQuery($conn, $sql, $typesWithLimit, $paramsWithLimit);

    $html_table = "";
    $no = $offset + 1;

    if ($qData && $qData->num_rows > 0) {
        while ($row = $qData->fetch_assoc()) {
            $html_table .= "<tr>
                    <td class='text-center'>".$no++."</td>
                    <td>".htmlspecialchars($row['satuan_pendidikan'] ?? '', ENT_QUOTES, 'UTF-8')."</td>
                    <td class='text-center'>".htmlspecialchars($row['npsn'] ?? '', ENT_QUOTES, 'UTF-8')."</td>
                    <td class='text-center'>".htmlspecialchars($row['tanggal'] ?? '', ENT_QUOTES, 'UTF-8')."</td>
                    <td class='text-center'>".htmlspecialchars($row['kodering'] ?? '', ENT_QUOTES, 'UTF-8')."</td>
                    <td>".htmlspecialchars($row['bku'] ?? '', ENT_QUOTES, 'UTF-8')."</td>
                    <td>".htmlspecialchars($row['uraian'] ?? '', ENT_QUOTES, 'UTF-8')."</td>
                    <td class='text-end fw-semibold text-primary'>Rp ".number_format((float)($row['nominal'] ?? 0), 0, ',', '.')."</td>
                    <td class='text-center'>".htmlspecialchars($row['bulan'] ?? '', ENT_QUOTES, 'UTF-8')."</td>
                    <td class='text-center'>
                        <button type='button' class='btn btn-link text-danger p-0 m-0' onclick='hapusItemAcuan(".(int)$row['id'].")' title='Hapus Baris Ini'>
                            <i class='bi bi-trash-fill fs-5'></i>
                        </button>
                    </td>
                  </tr>";
        }
    } else {
        $html_table .= "<tr><td colspan='10' class='text-center text-secondary py-4'><i class='bi bi-info-circle me-1'></i> Data tidak ditemukan untuk filter ini.</td></tr>";
    }

    $html_pagination = "";
    if ($total_pages > 1) {
        $html_pagination .= "<ul class='pagination pagination-sm mb-0 justify-content-center'>";
        
        $disabled_prev = ($page <= 1) ? "disabled" : "";
        $html_pagination .= "<li class='page-item $disabled_prev'><a class='page-link' href='javascript:void(0)' onclick='filterDataAcuan(".($page - 1).")'>Sebelumnya</a></li>";
        
        $start_loop = max(1, $page - 2);
        $end_loop = min($total_pages, $page + 2);
        
        for ($i = $start_loop; $i <= $end_loop; $i++) {
            $active_class = ($page == $i) ? "active" : "";
            $html_pagination .= "<li class='page-item $active_class'><a class='page-link' href='javascript:void(0)' onclick='filterDataAcuan($i)'>$i</a></li>";
        }

        $disabled_next = ($page >= $total_pages) ? "disabled" : "";
        $html_pagination .= "<li class='page-item $disabled_next'><a class='page-link' href='javascript:void(0)' onclick='filterDataAcuan(".($page + 1).")'>Selanjutnya</a></li>";
        $html_pagination .= "</ul>";
    }

    echo json_encode([
        'table' => $html_table,
        'pagination' => $html_pagination,
        'total_nominal' => "Rp " . number_format((float)$total_nominal_filtered, 0, ',', '.'),
        'total_sekolah' => number_format((int)$total_sekolah_filtered, 0, ',', '.') . " Sekolah"
    ]);
    exit;
}

// =========================================================================
// QUERY INITIAL VIEW (PREPARED STATEMENTS)
// =========================================================================
$wherePartsInit = [];
$paramsInit = [];
$typesInit = "";

if ($role_user !== 'admin') {
    $wherePartsInit[] = "id_sekolah = ?";
    $paramsInit[] = $id_sekolah_session;
    $typesInit .= "s";
} else {
    $wherePartsInit[] = "1=1";
}

if (!empty($default_filter_bulan)) {
    $wherePartsInit[] = "bulan = ?";
    $paramsInit[] = $default_filter_bulan;
    $typesInit .= "s";
}

$whereClauseInit = "WHERE " . implode(" AND ", $wherePartsInit);

// Total Sum Init
$sql_init_sum = "SELECT SUM(nominal) as total_sum FROM data_barang_acuan $whereClauseInit";
$res_init_sum = execPreparedQuery($conn, $sql_init_sum, $typesInit, $paramsInit);
$total_nominal_init = $res_init_sum ? ($res_init_sum->fetch_assoc()['total_sum'] ?? 0) : 0;

// Total School Init
$sql_init_school = "SELECT COUNT(DISTINCT npsn) as total_sekolah FROM data_barang_acuan $whereClauseInit";
$res_init_school = execPreparedQuery($conn, $sql_init_school, $typesInit, $paramsInit);
$total_sekolah_init = $res_init_school ? ($res_init_school->fetch_assoc()['total_sekolah'] ?? 0) : 0;

// Total Records Init
$sql_init_count = "SELECT COUNT(*) as total FROM data_barang_acuan $whereClauseInit";
$res_init_count = execPreparedQuery($conn, $sql_init_count, $typesInit, $paramsInit);
$total_records_init = $res_init_count ? ($res_init_count->fetch_assoc()['total'] ?? 0) : 0;
$total_pages_init = ceil($total_records_init / 50);
?>

<style>
    /* UI Palette: Soft Blue / Biru Tipis */
    .btn-custom-blue {
        background-color: #eff6ff;
        color: #2563eb;
        border: 1px solid #bfdbfe;
        font-weight: 600;
        transition: all 0.2s ease;
    }
    .btn-custom-blue:hover {
        background-color: #2563eb;
        color: #ffffff;
        border-color: #2563eb;
    }
    .card-import {
        border: 1px solid #e2e8f0;
        background: #ffffff;
        border-radius: 16px;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.03);
    }
    .import-zone {
        border: 2px dashed #bfdbfe;
        background-color: #f8fafc;
        border-radius: 12px;
        padding: 30px;
        text-align: center;
        position: relative;
        transition: all 0.2s ease;
    }
    .import-zone:hover {
        background-color: #f0f7ff;
        border-color: #3b82f6;
    }
    .table-acuan thead th {
        background-color: #eff6ff;
        color: #1e3a8a;
        font-weight: 700;
        font-size: 13px;
        border-bottom: 2px solid #dbeafe;
    }
    .table-acuan tbody td {
        font-size: 13.5px;
        color: #334155;
        vertical-align: middle;
    }
    .search-filter-box {
        background-color: #f8fafc;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
    }
    
    /* Widget Summary Penyelarasan Atas */
    .widget-summary-box {
        background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
        border: 1px solid #bbf7d0;
        border-radius: 12px;
    }
    .widget-total-nominal {
        background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
        border: 1px solid #bfdbfe;
        border-radius: 12px;
    }
</style>

<div class="container-fluid px-0">
    <div id="alert-container"></div>

    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="card card-import p-4">
                <div class="d-md-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h5 class="fw-bold text-dark mb-1"><i class="bi bi-file-earmark-excel-fill text-success me-2"></i>Import Master Barang Acuan</h5>
                        <p class="text-secondary small mb-0">Unggah data acuan belanja modal menggunakan format template resmi.</p>
                    </div>
                    <div class="mt-3 mt-md-0">
                        <a href="template_import_vendor_acuan.xlsx" download class="btn btn-custom-blue px-4 py-2" style="border-radius: 10px;">
                            <i class="bi bi-cloud-arrow-down-fill me-2"></i>Download Template XLSX
                        </a>
                    </div>
                </div>

                <form id="formImportExcel" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="import_excel_ajax">
                    <!-- Token Proteksi CSRF -->
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                    
                    <div class="import-zone mb-3">
                        <i class="bi bi-file-earmark-arrow-up text-primary" style="font-size: 40px;"></i>
                        <h6 class="fw-semibold text-dark mt-2 mb-1">Pilih File Excel Anda</h6>
                        <p class="text-secondary small mb-3">Mendukung format berkas .xlsx atau .xls</p>
                        <div class="d-flex justify-content-center">
                            <input type="file" name="file_template" class="form-control form-control-sm w-50" style="border-radius: 8px;" accept=".xls, .xlsx" required>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="submit" id="btnSubmitImport" class="btn btn-primary px-4 py-2" style="border-radius: 10px; font-weight: 600;">
                            <i class="bi bi-box-arrow-in-down-left me-2"></i>Proses Import Data
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="card card-import p-4">
        <div class="d-md-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold text-dark mb-3 mb-md-0"><i class="bi bi-table text-primary me-2"></i>Data Acuan Aktif (Role: <?= ucfirst($role_user); ?>)</h6>
            
            <button type="button" class="btn btn-outline-danger btn-sm px-3 py-2 fw-semibold" onclick="hapusSemuaDataAcuan()" style="border-radius: 8px;">
                <i class="bi bi-trash3-fill me-1"></i> Kosongkan Semua Data Acuan
            </button>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <div class="widget-total-nominal p-3 h-100 d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-secondary small fw-bold text-uppercase tracking-wider"><i class="bi bi-cash-stack me-1"></i> Total Akumulasi Nominal Acuan</span>
                        <h3 class="fw-bold text-primary mb-0 mt-1" id="total-nominal-header">Rp <?= number_format((float)$total_nominal_init, 0, ',', '.'); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="widget-summary-box p-3 h-100 d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-success small fw-bold text-uppercase tracking-wider"><i class="bi bi-building-check me-1"></i> Total Sekolah Terealisasi</span>
                        <h3 class="fw-bold text-success mb-0 mt-1" id="total-sekolah-header"><?= number_format((int)$total_sekolah_init, 0, ',', '.'); ?> Sekolah</h3>
                    </div>
                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2 fw-semibold d-none d-sm-inline">Real-time</span>
                </div>
            </div>
        </div>

        <div class="search-filter-box p-3 mb-3">
            <div class="row g-3">
                <div class="col-md-7">
                    <label class="form-label small fw-bold text-secondary"><i class="bi bi-search me-1"></i> Cari Satuan Pendidikan</label>
                    <input type="text" id="search_satuan" class="form-control form-control-sm" placeholder="Ketik nama sekolah vendor... (Contoh: SMKN 1 KUNINGAN)" style="border-radius: 8px;">
                </div>
                <div class="col-md-5">
                    <label class="form-label small fw-bold text-secondary"><i class="bi bi-calendar-event me-1"></i> Filter Berdasarkan Bulan</label>
                    <select id="filter_bulan" class="form-select form-select-sm" style="border-radius: 8px;">
                        <option value="">-- Semua Bulan --</option>
                        <?php 
                        foreach ($list_bulan as $bln) {
                            $selected = ($bln == $default_filter_bulan) ? "selected" : "";
                            echo "<option value='".htmlspecialchars($bln, ENT_QUOTES, 'UTF-8')."' $selected>".htmlspecialchars($bln, ENT_QUOTES, 'UTF-8')."</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover table-striped table-bordered table-acuan mb-0">
                <thead class="text-center align-middle">
                    <tr>
                        <th>No</th>
                        <th>Satuan Pendidikan</th>
                        <th>NPSN</th>
                        <th>Tanggal</th>
                        <th>Kodering</th>
                        <th>BKU</th>
                        <th>Uraian Barang</th>
                        <th>Nominal</th>
                        <th>Bulan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="table-body-acuan">
                    <?php
                    $no = 1;
                    $sql_init_data = "SELECT * FROM data_barang_acuan $whereClauseInit ORDER BY id DESC LIMIT 50";
                    $qData = execPreparedQuery($conn, $sql_init_data, $typesInit, $paramsInit);

                    if ($qData && $qData->num_rows > 0) {
                        while ($row = $qData->fetch_assoc()) {
                            echo "<tr>
                                    <td class='text-center'>".$no++."</td>
                                    <td>".htmlspecialchars($row['satuan_pendidikan'] ?? '', ENT_QUOTES, 'UTF-8')."</td>
                                    <td class='text-center'>".htmlspecialchars($row['npsn'] ?? '', ENT_QUOTES, 'UTF-8')."</td>
                                    <td class='text-center'>".htmlspecialchars($row['tanggal'] ?? '', ENT_QUOTES, 'UTF-8')."</td>
                                    <td class='text-center'>".htmlspecialchars($row['kodering'] ?? '', ENT_QUOTES, 'UTF-8')."</td>
                                    <td>".htmlspecialchars($row['bku'] ?? '', ENT_QUOTES, 'UTF-8')."</td>
                                    <td>".htmlspecialchars($row['uraian'] ?? '', ENT_QUOTES, 'UTF-8')."</td>
                                    <td class='text-end fw-semibold text-primary'>Rp ".number_format((float)($row['nominal'] ?? 0), 0, ',', '.')."</td>
                                    <td class='text-center'>".htmlspecialchars($row['bulan'] ?? '', ENT_QUOTES, 'UTF-8')."</td>
                                    <td class='text-center'>
                                        <button type='button' class='btn btn-link text-danger p-0 m-0' onclick='hapusItemAcuan(".(int)$row['id'].")' title='Hapus Baris Ini'>
                                            <i class='bi bi-trash-fill fs-5'></i>
                                        </button>
                                    </td>
                                  </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='10' class='text-center text-secondary py-4'>Tidak ada data barang acuan di sistem untuk filter bulan ini.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-center mt-3" id="pagination-container">
            <?php if ($total_pages_init > 1): ?>
                <ul class="pagination pagination-sm mb-0 justify-content-center">
                    <li class="page-item disabled"><a class="page-link" href="javascript:void(0)">Sebelumnya</a></li>
                    <?php 
                    $end_loop_init = min($total_pages_init, 5);
                    for($i = 1; $i <= $end_loop_init; $i++): ?>
                        <li class="page-item <?= ($i == 1) ? 'active' : ''; ?>"><a class="page-link" href="javascript:void(0)" onclick="filterDataAcuan(<?= $i; ?>)"><?= $i; ?></a></li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($total_pages_init <= 1) ? 'disabled' : ''; ?>"><a class="page-link" href="javascript:void(0)" onclick="filterDataAcuan(2)">Selanjutnya</a></li>
                </ul>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
    // Token Proteksi CSRF untuk JS
    const csrfToken = "<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>";

    function refreshInternalContainer() {
        if (typeof loadPage === "function") {
            loadPage('input_acuan.php', 'Master Barang Acuan', false);
        } else {
            location.reload();
        }
    }

    async function filterDataAcuan(pageNumber = 1) {
        const searchVal = document.getElementById('search_satuan').value;
        const bulanVal = document.getElementById('filter_bulan').value;
        const tbody = document.getElementById('table-body-acuan');
        const pagContainer = document.getElementById('pagination-container');
        const totalHeader = document.getElementById('total-nominal-header');
        const sekolahHeader = document.getElementById('total-sekolah-header'); 

        try {
            const response = await fetch(`input_acuan.php?action=filter_table_ajax&search_satuan=${encodeURIComponent(searchVal)}&filter_bulan=${encodeURIComponent(bulanVal)}&page=${pageNumber}`);
            const result = await response.json();
            
            tbody.innerHTML = result.table;
            pagContainer.innerHTML = result.pagination;
            totalHeader.innerHTML = result.total_nominal; 
            sekolahHeader.innerHTML = result.total_sekolah; 
        } catch (error) {
            console.error("Gagal memfilter data tabel: ", error);
        }
    }
    
    document.getElementById('search_satuan').addEventListener('input', () => filterDataAcuan(1));
    document.getElementById('filter_bulan').addEventListener('change', () => filterDataAcuan(1));

    document.getElementById('formImportExcel').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btnSubmit = document.getElementById('btnSubmitImport');
        const alertContainer = document.getElementById('alert-container');
        
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Memproses...';
        
        const formData = new FormData(this);
        
        try {
            const response = await fetch('input_acuan.php', { method: 'POST', body: formData });
            const result = await response.json();
            
            if (result.status === 'success') {
                alertContainer.innerHTML = `<div class='alert alert-success'><i class='bi bi-check-circle-fill me-2'></i>${result.message}</div>`;
                setTimeout(refreshInternalContainer, 1200);
            } else {
                alertContainer.innerHTML = `<div class='alert alert-danger'><i class='bi bi-exclamation-triangle-fill me-2'></i>${result.message}</div>`;
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = '<i class="bi bi-box-arrow-in-down-left me-2"></i>Proses Import Data';
            }
        } catch (error) {
            alertContainer.innerHTML = `<div class='alert alert-danger'><i class='bi bi-exclamation-triangle-fill me-2'></i>Koneksi bermasalah saat import!</div>`;
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = '<i class="bi bi-box-arrow-in-down-left me-2"></i>Proses Import Data';
        }
    });

    async function hapusItemAcuan(idItem) {
        if (confirm("Apakah Anda yakin ingin menghapus data acuan baris ini?")) {
            const alertContainer = document.getElementById('alert-container');
            const dataForm = new FormData();
            dataForm.append('action', 'hapus_item_ajax');
            dataForm.append('id', idItem);
            dataForm.append('csrf_token', csrfToken);

            try {
                const response = await fetch('input_acuan.php', { method: 'POST', body: dataForm });
                const result = await response.json();

                if (result.status === 'success') {
                    alertContainer.innerHTML = `<div class='alert alert-success'><i class='bi bi-check-circle-fill me-2'></i>${result.message}</div>`;
                    setTimeout(refreshInternalContainer, 1000);
                } else {
                    alertContainer.innerHTML = `<div class='alert alert-danger'><i class='bi bi-exclamation-triangle-fill me-2'></i>${result.message}</div>`;
                }
            } catch (error) {
                alertContainer.innerHTML = `<div class='alert alert-danger'><i class='bi bi-exclamation-triangle-fill me-2'></i>Gagal terhubung ke server saat menghapus data!</div>`;
            }
        }
    }

    async function hapusSemuaDataAcuan() {
        const filterBulanSelect = document.getElementById('filter_bulan');
        const bulanVal = filterBulanSelect.value;
        const teksBulan = bulanVal ? `bulan "${bulanVal}"` : "SEMUA BULAN";

        if (confirm(`⚠️ PERINGATAN KERAS!\n\nApakah Anda benar-benar yakin ingin MENGHAPUS data acuan khusus untuk ${teksBulan.toUpperCase()}?\n\nData yang dihapus tidak bisa dikembalikan.`)) {
            
            const alertContainer = document.getElementById('alert-container');
            const dataForm = new FormData();
            
            dataForm.append('action', 'hapus_semua_ajax');
            dataForm.append('filter_bulan', bulanVal);
            dataForm.append('csrf_token', csrfToken);

            try {
                const response = await fetch('input_acuan.php', { method: 'POST', body: dataForm });
                const result = await response.json();

                if (result.status === 'success') {
                    alertContainer.innerHTML = `<div class='alert alert-success'><i class='bi bi-check-circle-fill me-2'></i>${result.message}</div>`;
                    setTimeout(refreshInternalContainer, 1500);
                } else {
                    alertContainer.innerHTML = `<div class='alert alert-danger'><i class='bi bi-exclamation-triangle-fill me-2'></i>${result.message}</div>`;
                }
            } catch (error) {
                console.error(error);
                alertContainer.innerHTML = `<div class='alert alert-danger'><i class='bi bi-exclamation-triangle-fill me-2'></i>Gagal terhubung ke server saat mengosongkan data!</div>`;
            }
        }
    }
</script>