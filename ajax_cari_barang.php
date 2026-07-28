<?php
// 1. Sembunyikan error mentah MySQL/PHP dari publik untuk mencegah Information Disclosure
error_reporting(0);
ini_set('display_errors', '0');

// 2. Pasang Security Headers
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// 3. Batasi hanya Method GET yang diizinkan
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([]);
    exit;
}

// 4. Kredensial Database (Mendukung Environment Variables dengan fallback default)
$host = getenv('DB_HOST') ?: "localhost"; 
$user = getenv('DB_USER') ?: "root"; 
$pass = getenv('DB_PASS') ?: ""; 
$db   = getenv('DB_NAME') ?: "db_inventaris"; 

// Matikan exception otomatis MySQLi agar pesan error tidak bocor saat koneksi gagal
mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    echo json_encode([]);
    exit;
}

// Set charset ke utf8mb4 untuk keamanan encoding data
$conn->set_charset('utf8mb4');

// 5. Sanitisasi & Validasi Input
$keyword = isset($_GET['q']) ? trim(mb_strtolower($_GET['q'], 'UTF-8')) : '';

if ($keyword === '') {
    echo json_encode([]);
    $conn->close();
    exit;
}

// Escape karakter spesial LIKE (% dan _) untuk mencegah Wildcard DoS / hasil query tak terduga
$escaped_keyword = addcslashes($keyword, '%_');
$search_param = '%' . $escaped_keyword . '%';

// 6. PREPARED STATEMENT (100% Bebas SQL Injection)
$query = "SELECT k1.`id`, k1.`kode_barang`, k1.`uraian`, k1.`kodering_aset`, k1.`jenis_aset`, k1.`umur_ekonomis` 
          FROM `kode_barang` k1
          WHERE (LOWER(k1.`kode_barang`) LIKE ? OR LOWER(k1.`uraian`) LIKE ?) 
          AND NOT EXISTS (
              SELECT 1 FROM `kode_barang` k2 
              WHERE k2.`kode_barang` LIKE CONCAT(k1.`kode_barang`, '%') 
              AND k2.`kode_barang` <> k1.`kode_barang`
          )
          LIMIT 100";

$stmt = $conn->prepare($query);

if (!$stmt) {
    echo json_encode([]);
    $conn->close();
    exit;
}

// Bind parameter secara aman
$stmt->bind_param("ss", $search_param, $search_param);
$stmt->execute();
$result = $stmt->get_result();

$data = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id'            => $row['id'],
            'kode_barang'   => $row['kode_barang'],
            'nama_barang'   => $row['uraian'], 
            'kodering_aset' => $row['kodering_aset'],
            'jenis_aset'    => $row['jenis_aset'],
            'umur_ekonomis' => $row['umur_ekonomis'] ?? 0
        ];
    }
}

$stmt->close();
$conn->close();

// 7. Output JSON dengan Flag Proteksi XSS Context
echo json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
exit;
?>