<?php
// AMAN: Tidak boleh ada spasi/enter di atas tag <?php
ob_start();

// === KEAMANAN LAPIS BAJA: PENGATURAN SESI KETAT & COOKIE SECURE ===
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');

// Otomatis aktifkan cookie secure jika koneksi menggunakan HTTPS
$isHttps = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') ||
    (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
);
if ($isHttps) {
    ini_set('session.cookie_secure', 1);
}

// === KEAMANAN LAPIS BAJA: HTTP SECURITY HEADERS ===
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");

// 1. FORCE PAKSA MEMORI DAN WAKTU MAKSIMAL SERVER (ANTI MASALAH TEKNIS)
ini_set('memory_limit', '1024M');
ini_set('max_execution_time', '900'); // 15 Menit eksekusi aman

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// === KEAMANAN LAPIS BAJA: VALIDASI METHOD & CSRF TOKEN ===
// Tolak jika bukan request POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Metode request tidak diizinkan.']);
    exit;
}

// Tolak jika CSRF token kosong atau tidak cocok
if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    ob_end_clean();
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Keamanan: Akses ditolak (CSRF Token tidak valid).']);
    exit;
}

// Proteksi file
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    ob_end_clean();
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak. Silakan login terlebih dahulu.']);
    exit;
}

// Set counter awal data murni ke 0 & Lepas Kunci Sesi
$_SESSION['progress_download'] = 0;
session_write_close(); 

include "koneksi.php";
require 'vendor/autoload.php';

// Validasi dan Filter Input Rentang Nilai Ketat
$filter_bulan = filter_input(INPUT_POST, 'bulan', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 12]
]);
$filter_tahun = filter_input(INPUT_POST, 'tahun', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 2000, 'max_range' => 2100]
]);

if (!$filter_bulan || !$filter_tahun) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Parameter bulan (1-12) dan tahun valid wajib dipilih!']);
    exit;
}

// === HELPER AMAN UNTUK UPDATE PROGRESS SESSION TANPA MERUSAK HEADER ===
function updateProgressSafe($count) {
    ini_set('session.use_cookies', 0);
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    $_SESSION['progress_download'] = (int)$count;
    session_write_close();
}

// === HELPER SANITASI MENCEGAH FORMULA / EXCEL INJECTION ===
function safeCellString($value) {
    if ($value === null || $value === '') return '';
    $str = (string)$value;
    // Netralkan karakter berbahaya yang diawali =, +, -, @, Tab, CR
    if (preg_match('/^[\=\+\-\@\t\r]/', $str)) {
        return "'" . $str;
    }
    return $str;
}

$nama_bulan_indo = [
    1 => 'JANUARI', 2 => 'FEBRUARI', 3 => 'MARET', 4 => 'APRIL',
    5 => 'MEI', 6 => 'JUNI', 7 => 'JULI', 8 => 'AGUSTUS',
    9 => 'SEPTEMBER', 10 => 'OKTOBER', 11 => 'NOVEMBER', 12 => 'DESEMBER'
];
$teks_bulan_pilihan = $nama_bulan_indo[$filter_bulan] ?? '';

// === KEAMANAN LAPIS BAJA: PREPARED STATEMENT UTAMA (ANTI SQL INJECTION) ===
$query = "SELECT r.*, k.nama_sekolah as nama_sekolah_db 
          FROM `realisasi_barang_sekolah` r 
          LEFT JOIN `kode_sekolah` k ON r.id_sekolah = k.id_sekolah OR r.id_sekolah = k.id 
          WHERE r.`bulan_realisasi` = ? AND YEAR(r.`ba_tgl`) = ? 
          ORDER BY k.nama_sekolah ASC, r.`ba_tgl` ASC, r.`id` ASC";

$stmt = mysqli_prepare($conn, $query);
if (!$stmt) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Gagal mempersiapkan query database.']);
    exit;
}

mysqli_stmt_bind_param($stmt, "ii", $filter_bulan, $filter_tahun);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$totalRows = mysqli_num_rows($result);

// Jika data kosong, gagalkan download dengan respon bersih
if ($totalRows === 0) {
    mysqli_stmt_close($stmt);
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'empty',
        'message' => 'Data realisasi tidak ditemukan'
    ]);
    exit;
}

// Inisialisasi Spreadsheet
$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

// ==============================================================================
// STEP 1: SHEET REFERENSI 'KODE BARANG'
// ==============================================================================
$sheetMaster = $spreadsheet->createSheet();
$sheetMaster->setTitle('KODE BARANG');

$sheetMaster->setCellValue('A1', 'KODE BARANG');
$sheetMaster->setCellValue('B1', 'URAIAN');
$sheetMaster->setCellValue('C1', 'KODERING ASET');
$sheetMaster->setCellValue('D1', 'JENIS ASET');
$sheetMaster->setCellValue('E1', 'UMUR EKONOMIS');

$batasMaster = 2;
try {
    $db_inv = getenv('DB_INV') ?: 'db_inventaris';
    $query_master_inventaris = "SELECT kode_barang, uraian, kodering_aset, jenis_aset, umur_ekonomis
                                FROM `" . $db_inv . "`.kode_barang
                                WHERE kode_barang IS NOT NULL AND kode_barang != ''";
                                
    $qMaster = @mysqli_query($conn, $query_master_inventaris);

    if ($qMaster) {
        $mRow = 2;
        while($m = mysqli_fetch_assoc($qMaster)) {
            $sheetMaster->setCellValueExplicit('A' . $mRow, safeCellString(trim($m['kode_barang'])), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheetMaster->setCellValue('B' . $mRow, safeCellString($m['uraian']));
            $sheetMaster->setCellValueExplicit('C' . $mRow, safeCellString(trim($m['kodering_aset'])), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheetMaster->setCellValue('D' . $mRow, safeCellString($m['jenis_aset']));
            $sheetMaster->setCellValue('E' . $mRow, (int)$m['umur_ekonomis']);
            $mRow++;
        }
        $batasMaster = $mRow - 1;
    }
} catch (Throwable $t) {
    error_log("Master Inventaris Error: " . $t->getMessage());
}
if ($batasMaster < 2) { $batasMaster = 2; }

// ==============================================================================
// STEP 2: SET SHEET UTAMA
// ==============================================================================
$spreadsheet->setActiveSheetIndex(0);
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Laporan Belanja Modal');
$sheet->setShowGridlines(true);

// Kunci baris 1-9 dan kolom A-E saat di-scroll (Freeze Panes di F10)
$sheet->freezePane('F10');

$sheet->setCellValue('A1', 'DAFTAR PENGADAAN BARANG DARI BELANJA MODAL'); $sheet->mergeCells('A1:Y1');
$sheet->setCellValue('A2', 'SMAN/SMKN/SLBN'); $sheet->mergeCells('A2:Y2');
$sheet->setCellValue('A3', 'DARI TANGGAL 1 JANUARI S.D 31 DESEMBER ' . $filter_tahun); $sheet->mergeCells('A3:Y3');

$styleJudul = [
    'font' => ['bold' => true, 'color' => ['rgb' => '000000'], 'size' => 11, 'name' => 'Calibri'],
    'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
];
$sheet->getStyle('A1:A3')->applyFromArray($styleJudul);
$sheet->getStyle('A2')->getFont()->getColor()->setRGB('FF0000');

$sheet->setCellValue('A5', '*CATATAN HURUF KOLOM:');
$sheet->setCellValue('D5', ': Wajib Diisi secara Manual');
$sheet->setCellValue('D6', ': Terisi Otomatis');

$sheet->getStyle('C5')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('FF0000');
$sheet->getStyle('C6')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('000000');

// Header Tabel
$sheet->setCellValue('A8', 'No'); $sheet->mergeCells('A8:A9');
$sheet->setCellValue('B8', 'No. SP2D'); $sheet->mergeCells('B8:B9');
$sheet->setCellValue('C8', 'Sumber Perolehan'); $sheet->mergeCells('C8:C9');
$sheet->setCellValue('D8', 'Kodering Belanja'); $sheet->mergeCells('D8:D9');
$sheet->setCellValue('E8', 'No. SPK / Faktur / Kuitansi'); $sheet->mergeCells('E8:E9');
$sheet->setCellValue('F8', 'BA Penerimaan'); $sheet->mergeCells('F8:I8');
$sheet->setCellValue('J8', 'Kode Barang'); $sheet->mergeCells('J8:J9');
$sheet->setCellValue('K8', 'Rincian Barang'); $sheet->mergeCells('K8:R8');
$sheet->setCellValue('S8', 'Kodering Aset'); $sheet->mergeCells('S8:S9');
$sheet->setCellValue('T8', 'Nama Rekening Aset'); $sheet->mergeCells('T8:T9');
$sheet->setCellValue('U8', 'Umur Ekonomis'); $sheet->mergeCells('U8:U9');
$sheet->setCellValue('V8', 'Intrakomptabel'); $sheet->mergeCells('V8:W8');
$sheet->setCellValue('X8', 'Ekstrakomptabel'); $sheet->mergeCells('X8:X9');
$sheet->setCellValue('Y8', 'Nama Sekolah'); $sheet->mergeCells('Y8:Y9');

$sheet->setCellValue('F9', 'No'); $sheet->setCellValue('G9', 'Tgl'); $sheet->setCellValue('H9', 'Bln'); $sheet->setCellValue('I9', 'Thn');
$sheet->setCellValue('K9', 'Nama Barang'); $sheet->setCellValue('L9', 'Merk/Tipe'); $sheet->setCellValue('M9', 'No. Sertifikat/ No. Rangka/ No. Mesin');
$sheet->setCellValue('N9', 'Ukuran (Gedung/ Bangunan)'); $sheet->setCellValue('O9', 'Satuan'); $sheet->setCellValue('P9', 'Volume');
$sheet->setCellValue('Q9', 'Harga Satuan'); $sheet->setCellValue('R9', 'Nilai Perolehan');
$sheet->setCellValue('V9', 'Nilai Perolehan'); $sheet->setCellValue('W9', 'Beban Penyusutan');

$styleHeaderTable = [
    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DDEBF7']],
    'font' => ['bold' => false, 'size' => 11, 'name' => 'Calibri'],
    'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER, 'wrapText' => true],
    'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]
];
$sheet->getStyle('A8:Y9')->applyFromArray($styleHeaderTable);

$kolomMerah = ['C8', 'D8', 'E8', 'F8', 'F9', 'G9', 'H9', 'I9', 'J8', 'L9', 'M9', 'N9', 'O9', 'P9', 'Q9', 'R9', 'S8', 'T8', 'U8', 'W9', 'X8', 'Y8'];
foreach ($kolomMerah as $cell) { $sheet->getStyle($cell)->getFont()->getColor()->setRGB('FF0000'); }

// Format Code Tipe Data "Accounting" tanpa simbol Rp
$formatAccountingNone = '_(* #,##0.00_);_(* (#,##0.00);_(* "-"??_);_(@_)';

// Tracking Panjang Maksimal Kolom (Optimasi Cepat tanpa Loop getCell)
$maxLenCol = array_fill_keys(range('A', 'Y'), 0);

// Populasi Data Row
$rowNum = 10; $noIdx = 1; $currentCount = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $currentCount++;
    
    // Update progress secara aman per 50 baris
    if ($currentCount === 1 || $currentCount % 50 === 0 || $currentCount === $totalRows) {
        updateProgressSafe($currentCount);
    }

    $tg = ''; $bl = ''; $thn = '';
    if (!empty($row['ba_tgl']) && $row['ba_tgl'] != '0000-00-00') {
        $time = strtotime($row['ba_tgl']);
        $tg = (int)date('d', $time); $bl = (int)date('m', $time); $thn = date('Y', $time);
    }

    $nama_sekolah_tampil = !empty($row['nama_sekolah_db']) ? $row['nama_sekolah_db'] : "Sekolah ID: " . $row['id_sekolah'];

    $valB = safeCellString($row['no_sp2d']);
    $valC = safeCellString($row['sumber_perolehan']);
    $valD = safeCellString($row['kodering_belanja']);
    $valE = safeCellString($row['no_spk']);
    $valF = safeCellString($row['ba_no']);
    $valJ = safeCellString(trim($row['kode_barang']));
    $valL = safeCellString($row['merk_tipe']);
    $valM = safeCellString($row['no_sertifikat']);
    $valN = safeCellString($row['ukuran_bangunan']);
    $valO = safeCellString($row['satuan']);

    $sheet->setCellValue('A' . $rowNum, $noIdx);
    $sheet->setCellValue('B' . $rowNum, $valB);
    $sheet->setCellValue('C' . $rowNum, $valC);
    $sheet->setCellValue('D' . $rowNum, $valD);
    $sheet->setCellValue('E' . $rowNum, $valE);
    $sheet->setCellValue('F' . $rowNum, $valF);
    $sheet->setCellValue('G' . $rowNum, $tg);
    $sheet->setCellValue('H' . $rowNum, $bl);
    $sheet->setCellValue('I' . $rowNum, $thn);
    
    $sheet->setCellValueExplicit('J' . $rowNum, $valJ, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue('K' . $rowNum, '=IFERROR(VLOOKUP(J' . $rowNum . ',\'KODE BARANG\'!$A$2:$E$' . $batasMaster . ',2,FALSE),"")');
    $sheet->setCellValue('L' . $rowNum, $valL);
    $sheet->setCellValue('M' . $rowNum, $valM);
    $sheet->setCellValue('N' . $rowNum, $valN);
    $sheet->setCellValue('O' . $rowNum, $valO);
    
    $volume_clean = isset($row['volume']) ? (int)$row['volume'] : 0;
    $harga_clean = isset($row['harga_satuan']) ? (float)$row['harga_satuan'] : 0.0;
    $nilai_clean = isset($row['nilai_perolehan']) ? (float)$row['nilai_perolehan'] : 0.0;

    $sheet->setCellValueExplicit('P' . $rowNum, $volume_clean, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $sheet->setCellValueExplicit('Q' . $rowNum, $harga_clean, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $sheet->setCellValueExplicit('R' . $rowNum, $nilai_clean, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC); 
    
    $sheet->setCellValue('S' . $rowNum, '=IFERROR(VLOOKUP(J' . $rowNum . ',\'KODE BARANG\'!$A$2:$E$' . $batasMaster . ',3,FALSE),"")'); 
    $sheet->setCellValue('T' . $rowNum, '=IFERROR(VLOOKUP(J' . $rowNum . ',\'KODE BARANG\'!$A$2:$E$' . $batasMaster . ',4,FALSE),"")');
    $sheet->setCellValue('U' . $rowNum, '=IFERROR(VLOOKUP(J' . $rowNum . ',\'KODE BARANG\'!$A$2:$E$' . $batasMaster . ',5,FALSE),0)'); 
    
    $sheet->setCellValue('V' . $rowNum, '=R' . $rowNum);
    $sheet->setCellValue('W' . $rowNum, '=IF(AND($V' . $rowNum . '=0)," ",(($V' . $rowNum . '/$U' . $rowNum . ')*(13-H' . $rowNum . ')/12))'); 
    $sheet->setCellValue('X' . $rowNum, '=IF(Q' . $rowNum . '<=1000000,R' . $rowNum . ',0)'); 
    
    $sheet->setCellValue('Y' . $rowNum, safeCellString($nama_sekolah_tampil));

    // Recording Panjang Maksimal Nilai Sel Secara Real-Time (Cepat)
    $maxLenCol['B'] = max($maxLenCol['B'], strlen($valB));
    $maxLenCol['C'] = max($maxLenCol['C'], strlen($valC));
    $maxLenCol['D'] = max($maxLenCol['D'], strlen($valD));
    $maxLenCol['E'] = max($maxLenCol['E'], strlen($valE));
    $maxLenCol['F'] = max($maxLenCol['F'], strlen($valF));
    $maxLenCol['J'] = max($maxLenCol['J'], strlen($valJ));
    $maxLenCol['L'] = max($maxLenCol['L'], strlen($valL));
    $maxLenCol['M'] = max($maxLenCol['M'], strlen($valM));
    $maxLenCol['N'] = max($maxLenCol['N'], strlen($valN));
    $maxLenCol['O'] = max($maxLenCol['O'], strlen($valO));
    $maxLenCol['Y'] = max($maxLenCol['Y'], strlen((string)$nama_sekolah_tampil));

    // Styling
    $sheet->getStyle('A' . $rowNum . ':Y' . $rowNum)->getFont()->setSize(11)->setName('Calibri');
    $sheet->getStyle('A' . $rowNum . ':Y' . $rowNum)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)->getColor()->setRGB('000000');
    
    $sheet->getStyle('A' . $rowNum)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('G' . $rowNum . ':I' . $rowNum)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('O' . $rowNum . ':P' . $rowNum)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('U' . $rowNum)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    $sheet->getStyle('Q' . $rowNum . ':R' . $rowNum)->getNumberFormat()->setFormatCode($formatAccountingNone);
    $sheet->getStyle('V' . $rowNum . ':X' . $rowNum)->getNumberFormat()->setFormatCode($formatAccountingNone);

    $rowNum++; $noIdx++;
}

// Tutup Prepared Statement
mysqli_stmt_close($stmt);

// Total Box Hijau Row 7
$lastDataRow = $rowNum - 1;
if ($lastDataRow < 10) { $lastDataRow = 10; }
$sheet->setCellValue('R7', '=SUM(R10:R' . $lastDataRow . ')');
$sheet->getStyle('R7')->getNumberFormat()->setFormatCode($formatAccountingNone); 
$sheet->getStyle('R7')->getFont()->setSize(11)->setName('Calibri')->setBold(true)->getColor()->setRGB('000000');
$sheet->getStyle('R7')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('92D050');
$sheet->getStyle('R7')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)->getColor()->setRGB('000000');

// ==============================================================================
// LOGIKA SQUEEZE/OPTIMALISASI LEBAR TABEL MENGIKUTI ISINYA (MENGGUNAKAN HASIL REKAM DATA)
// ==============================================================================
foreach (range('A', 'Y') as $col) {
    if ($col === 'A') {
        $sheet->getColumnDimension($col)->setAutoSize(false)->setWidth(5);
        continue;
    }

    // Mengambil panjang data terbesar yang terekam (Untuk formula diisi default 14)
    $maxLen = in_array($col, ['K', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X']) ? 14 : $maxLenCol[$col];

    $finalWidth = $maxLen + 4; // Beri margin aman agar angka tidak berubah jadi ###

    // Batasan minimal rasional agar judul kolom atas tidak tergulung terlalu ekstrem
    $minWidth = 11;
    if (in_array($col, ['B', 'E', 'K', 'L', 'M', 'T', 'Y'])) { 
        $minWidth = 16; 
    }

    if ($finalWidth < $minWidth) { $finalWidth = $minWidth; }

    $sheet->getColumnDimension($col)->setAutoSize(false);
    $sheet->getColumnDimension($col)->setWidth($finalWidth);
}

// MEMBERSIHKAN SELURUH BUFFER SEBELUM OUTPUT FILE DIKIRIM (KUNCI UTAMA ANTI ERROR SERVER)
if (ob_get_length()) {
    ob_end_clean();
}

$filename = "Daftar_Pengadaan_Belanja_Modal_Bulan_" . $filter_bulan . "_" . $filter_tahun . ".xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0, no-cache, must-revalidate');
header('Pragma: public');

$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->save('php://output');
exit;