<?php
// AMAN: Tidak boleh ada spasi/enter di atas tag <?php
ob_start();

// 1. FORCE PAKSA MEMORI DAN WAKTU MAKSIMAL SERVER (ANTI MASALAH TEKNIS)
ini_set('memory_limit', '1024M');
ini_set('max_execution_time', '900'); // 15 Menit eksekusi aman

// === PROTEKSI & KEAMANAN SESSION ===
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');
if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === '1')) {
    ini_set('session.cookie_secure', 1);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// === RESPONSE SECURITY HEADERS ===
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// === PROTEKSI 1: Cek Session Login ===
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    header("Location: login.php");
    exit;
}

// === PROTEKSI 2: Proteksi Anti Session Hijacking ===
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
if (!isset($_SESSION['user_agent'])) {
    $_SESSION['user_agent'] = md5($user_agent);
} elseif ($_SESSION['user_agent'] !== md5($user_agent)) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}

// === PROTEKSI 3: Mencegah Session Fixation ===
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

// === PROTEKSI 4: Hak Akses Khusus User (Jika Admin mengetik URL ini, kembalikan ke index_admin.php) ===
$role_user = $_SESSION['role'] ?? '';
if ($role_user === 'admin') {
    header("Location: index_admin.php");
    exit;
}

// === HELPER SANITASI EXCEL FORMULA INJECTION ===
if (!function_exists('safeExcelVal')) {
    function safeExcelVal($val) {
        if ($val === null || $val === '') return '';
        $val = (string)$val;
        if (preg_match('/^[\=\+\-\@\t\r]/', $val)) {
            return "'" . $val;
        }
        return $val;
    }
}

// === FITUR BARU: ENDPOINT AJAX UNTUK MENGECEK PROGRESS PERSENTASE ===
if (isset($_GET['get_progress'])) {
    $progress = $_SESSION['progress_download'] ?? 0;
    session_write_close();
    header('Content-Type: application/json');
    echo json_encode(['progress' => $progress]);
    exit;
}

// Inisialisasi awal progress hanya saat tombol cetak excel ditekan
if (isset($_GET['proses_cetak_excel']) || isset($_GET['proses_cetak_template'])) {
    $_SESSION['progress_download'] = 0;
}
session_write_close(); 

include "koneksi.php";

// Ambil ID Sekolah pengguna dari session
$id_sekolah_session = $_SESSION['id_sekolah'] ?? '';


// ==============================================================================
// 1. LOGIKA BACKEND EXCEL: EXPORT LAPORAN UTAMA (SUPER FAST BATCH INJECTION)
// ==============================================================================
if (isset($_GET['proses_cetak_excel'])) {

    require 'vendor/autoload.php';

    $filter_bulan = isset($_GET['filter_bulan']) ? (int)$_GET['filter_bulan'] : 0;
    $filter_tahun = isset($_GET['filter_tahun']) ? (int)$_GET['filter_tahun'] : 0;
    $filter_barang = isset($_GET['filter_barang']) ? trim($_GET['filter_barang']) : '';

    $nama_bulan_indo = [
        1 => 'JANUARI', 2 => 'FEBRUARI', 3 => 'MARET', 4 => 'APRIL',
        5 => 'MEI', 6 => 'JUNI', 7 => 'JULI', 8 => 'AGUSTUS',
        9 => 'SEPTEMBER', 10 => 'OKTOBER', 11 => 'NOVEMBER', 12 => 'DESEMBER'
    ];
    
    $nama_sekolah_user = 'SEKOLAH USER';
    $qSklh = "SELECT nama_sekolah FROM `kode_sekolah` WHERE `id_sekolah` = ? OR `id` = ? LIMIT 1";
    if ($stmtSklh = mysqli_prepare($conn, $qSklh)) {
        mysqli_stmt_bind_param($stmtSklh, "ss", $id_sekolah_session, $id_sekolah_session);
        mysqli_stmt_execute($stmtSklh);
        $resSklh = mysqli_stmt_get_result($stmtSklh);
        if ($dSklh = mysqli_fetch_assoc($resSklh)) {
            $nama_sekolah_user = $dSklh['nama_sekolah'] ?? 'SEKOLAH USER';
        }
        mysqli_stmt_close($stmtSklh);
    }

    $sql_excel = "SELECT r.*, k.nama_sekolah as nama_sekolah_db 
                  FROM `realisasi_barang_sekolah` r 
                  LEFT JOIN `kode_sekolah` k ON r.id_sekolah = k.id_sekolah OR r.id_sekolah = k.id 
                  WHERE r.`id_sekolah` = ?";
    
    $params_excel = [$id_sekolah_session];
    $types_excel = "s";

    if ($filter_bulan > 0) {
        $sql_excel .= " AND r.`bulan_realisasi` = ?";
        $params_excel[] = $filter_bulan;
        $types_excel .= "i";
    }
    if ($filter_tahun > 0) {
        $sql_excel .= " AND YEAR(r.`ba_tgl`) = ?";
        $params_excel[] = $filter_tahun;
        $types_excel .= "i";
    }
    if (!empty($filter_barang)) {
        $sql_excel .= " AND (r.`nama_barang` LIKE ? OR r.`kode_barang` LIKE ?)";
        $search_param = "%" . $filter_barang . "%";
        $params_excel[] = $search_param;
        $params_excel[] = $search_param;
        $types_excel .= "ss";
    }

    $sql_excel .= " ORDER BY k.nama_sekolah ASC, r.`ba_tgl` ASC, r.`id` ASC";

    $stmtExcel = mysqli_prepare($conn, $sql_excel);
    mysqli_stmt_bind_param($stmtExcel, $types_excel, ...$params_excel);
    mysqli_stmt_execute($stmtExcel);
    $result = mysqli_stmt_get_result($stmtExcel);
    $totalRows = mysqli_num_rows($result);

    if ($totalRows === 0) {
        mysqli_stmt_close($stmtExcel);
        ob_end_clean();
        setcookie("download_status", "empty", ['expires' => time() + 60, 'path' => '/']);
        header("HTTP/1.1 204 No Content");
        exit;
    }

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

    // -- SHEET REFERENSI 'KODE BARANG' (BULK INJECTION) --
    $sheetMaster = $spreadsheet->createSheet();
    $sheetMaster->setTitle('KODE BARANG');
    $sheetMaster->setCellValue('A1', 'KODE BARANG'); $sheetMaster->setCellValue('B1', 'URAIAN');
    $sheetMaster->setCellValue('C1', 'KODERING ASET'); $sheetMaster->setCellValue('D1', 'JENIS ASET');
    $sheetMaster->setCellValue('E1', 'UMUR EKONOMIS');

    $query_master_inventaris = "SELECT kode_barang, uraian, kodering_aset, jenis_aset, umur_ekonomis FROM db_inventaris.kode_barang WHERE kode_barang IS NOT NULL AND kode_barang != ''";
    $qMaster = @mysqli_query($conn, $query_master_inventaris);
    $batasMaster = 2;

    if ($qMaster) {
        $masterRows = [];
        while($m = mysqli_fetch_assoc($qMaster)) {
            $masterRows[] = [
                safeExcelVal(trim($m['kode_barang'])),
                safeExcelVal($m['uraian']),
                safeExcelVal(trim($m['kodering_aset'])),
                safeExcelVal($m['jenis_aset']),
                (int)$m['umur_ekonomis']
            ];
        }
        if (!empty($masterRows)) {
            $sheetMaster->fromArray($masterRows, NULL, 'A2');
            $batasMaster = count($masterRows) + 1;
        }
    }
    if ($batasMaster < 2) { $batasMaster = 2; }

    // -- SHEET UTAMA --
    $spreadsheet->setActiveSheetIndex(0);
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Laporan Belanja Modal');
    $sheet->setShowGridlines(true);
    $sheet->freezePane('F10');

    $sheet->setCellValue('A1', 'DAFTAR PENGADAAN BARANG DARI BELANJA MODAL'); $sheet->mergeCells('A1:Y1');
    $sheet->setCellValue('A2', strtoupper(safeExcelVal($nama_sekolah_user))); $sheet->mergeCells('A2:Y2');
    $sheet->setCellValue('A3', 'PERIODE TAHUN ' . ($filter_tahun > 0 ? $filter_tahun : '2026')); $sheet->mergeCells('A3:Y3');

    $styleJudul = ['font' => ['bold' => true, 'color' => ['rgb' => '000000'], 'size' => 11, 'name' => 'Calibri'], 'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER]];
    $sheet->getStyle('A1:A3')->applyFromArray($styleJudul);
    $sheet->getStyle('A2')->getFont()->getColor()->setRGB('FF0000');

    $sheet->setCellValue('A5', '*CATATAN HURUF KOLOM:');
    $sheet->setCellValue('D5', ': Wajib Diisi secara Manual'); $sheet->setCellValue('D6', ': Terisi Otomatis');
    $sheet->getStyle('C5')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('FF0000');
    $sheet->getStyle('C6')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('000000');

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

    $styleHeaderTable = ['fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DDEBF7']], 'font' => ['bold' => false, 'size' => 11, 'name' => 'Calibri'], 'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER, 'wrapText' => true], 'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]];
    $sheet->getStyle('A8:Y9')->applyFromArray($styleHeaderTable);

    $kolomMerah = ['C8', 'D8', 'E8', 'F8', 'F9', 'G9', 'H9', 'I9', 'J8', 'L9', 'M9', 'N9', 'O9', 'P9', 'Q9', 'R9', 'S8', 'T8', 'U8', 'W9', 'X8', 'Y8'];
    foreach ($kolomMerah as $cell) { $sheet->getStyle($cell)->getFont()->getColor()->setRGB('FF0000'); }

    $formatAccountingNone = '_(* #,##0.00_);_(* (#,##0.00);_(* "-"??_);_(@_)';
    
    // Tracking panjang string untuk Auto-width instan di PHP
    $maxLenCol = array_fill_keys(range('A', 'Y'), 0);
    $dataRows = [];
    $rowNum = 10; 
    $noIdx = 1; 
    $currentCount = 0;

    while ($row = mysqli_fetch_assoc($result)) {
        $currentCount++;
        if ($currentCount === 1 || $currentCount % 100 === 0 || $currentCount === $totalRows) {
            if (session_status() === PHP_SESSION_NONE) { session_start(); }
            $_SESSION['progress_download'] = round(($currentCount / $totalRows) * 100); 
            session_write_close(); 
        }

        $tg = ''; $bl = ''; $thn = '';
        if (!empty($row['ba_tgl']) && $row['ba_tgl'] != '0000-00-00') {
            $time = strtotime($row['ba_tgl']);
            $tg = (int)date('d', $time); $bl = (int)date('m', $time); $thn = date('Y', $time);
        }

        $nama_sekolah_tampil = !empty($row['nama_sekolah_db']) ? $row['nama_sekolah_db'] : $nama_sekolah_user;

        $valB = safeExcelVal($row['no_sp2d']);
        $valC = safeExcelVal($row['sumber_perolehan']);
        $valD = safeExcelVal($row['kodering_belanja']);
        $valE = safeExcelVal($row['no_spk']);
        $valF = safeExcelVal($row['ba_no']);
        $valJ = safeExcelVal(trim($row['kode_barang']));
        $valL = safeExcelVal($row['merk_tipe']);
        $valM = safeExcelVal($row['no_sertifikat']);
        $valN = safeExcelVal($row['ukuran_bangunan']);
        $valO = safeExcelVal($row['satuan']);

        $volume_val = (int)($row['volume'] ?? 0);
        $harga_val  = (float)($row['harga_satuan'] ?? 0.0);
        $nilai_val  = (float)($row['nilai_perolehan'] ?? 0.0);

        // Kumpulkan baris data ke Array PHP
        $dataRows[] = [
            $noIdx,                                                // A
            $valB,                                                 // B
            $valC,                                                 // C
            $valD,                                                 // D
            $valE,                                                 // E
            $valF,                                                 // F
            $tg,                                                   // G
            $bl,                                                   // H
            $thn,                                                  // I
            $valJ,                                                 // J
            '=IFERROR(VLOOKUP(J' . $rowNum . ',\'KODE BARANG\'!$A$2:$E$' . $batasMaster . ',2,FALSE),"")', // K
            $valL,                                                 // L
            $valM,                                                 // M
            $valN,                                                 // N
            $valO,                                                 // O
            $volume_val,                                           // P
            $harga_val,                                            // Q
            $nilai_val,                                            // R
            '=IFERROR(VLOOKUP(J' . $rowNum . ',\'KODE BARANG\'!$A$2:$E$' . $batasMaster . ',3,FALSE),"")', // S
            '=IFERROR(VLOOKUP(J' . $rowNum . ',\'KODE BARANG\'!$A$2:$E$' . $batasMaster . ',4,FALSE),"")', // T
            '=IFERROR(VLOOKUP(J' . $rowNum . ',\'KODE BARANG\'!$A$2:$E$' . $batasMaster . ',5,FALSE),0)',  // U
            '=R' . $rowNum,                                        // V
            '=IF(AND($V' . $rowNum . '=0)," ",(($V' . $rowNum . '/$U' . $rowNum . ')*(13-H' . $rowNum . ')/12))', // W
            '=IF(Q' . $rowNum . '<=1000000,R' . $rowNum . ',0)',   // X
            safeExcelVal($nama_sekolah_tampil)                     // Y
        ];

        // Hitung panjang karakter maksimum untuk auto-width
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

        $rowNum++; 
        $noIdx++;
    }
    mysqli_stmt_close($stmtExcel);

    // DUMP MASSAL SEMUA BARIS SEKANGLIKUS KE EXCEL
    if (!empty($dataRows)) {
        $sheet->fromArray($dataRows, NULL, 'A10');
    }

    $lastDataRow = $rowNum - 1; 
    if ($lastDataRow < 10) { $lastDataRow = 10; }

    // STYLING DAN FORMAT BATCH (MASSAL SEKALIGUS)
    if ($lastDataRow >= 10) {
        $rangeAll = 'A10:Y' . $lastDataRow;
        $sheet->getStyle($rangeAll)->getFont()->setSize(11)->setName('Calibri');
        $sheet->getStyle($rangeAll)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)->getColor()->setRGB('000000');

        $sheet->getStyle('A10:A' . $lastDataRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('G10:I' . $lastDataRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('O10:P' . $lastDataRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('U10:U' . $lastDataRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle('Q10:R' . $lastDataRow)->getNumberFormat()->setFormatCode($formatAccountingNone);
        $sheet->getStyle('V10:X' . $lastDataRow)->getNumberFormat()->setFormatCode($formatAccountingNone);
    }

    // Total Box Hijau Row 7
    $sheet->setCellValue('R7', '=SUM(R10:R' . $lastDataRow . ')');
    $sheet->getStyle('R7')->getNumberFormat()->setFormatCode($formatAccountingNone); 
    $sheet->getStyle('R7')->getFont()->setSize(11)->setName('Calibri')->setBold(true)->getColor()->setRGB('000000');
    $sheet->getStyle('R7')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('92D050');
    $sheet->getStyle('R7')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)->getColor()->setRGB('000000');

    // LEBAR KOLOM SERTTA OTOMATIS TANPA LOOPING SEL EXCEL
    foreach (range('A', 'Y') as $col) {
        if ($col === 'A') { $sheet->getColumnDimension($col)->setAutoSize(false)->setWidth(5); continue; }
        
        $maxLen = in_array($col, ['K', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X']) ? 14 : $maxLenCol[$col];
        $finalWidth = $maxLen + 4;
        $minWidth = in_array($col, ['B', 'E', 'K', 'L', 'M', 'T', 'Y']) ? 16 : 11;
        
        if ($finalWidth < $minWidth) { $finalWidth = $minWidth; }
        $sheet->getColumnDimension($col)->setAutoSize(false)->setWidth($finalWidth);
    }

    if (ob_get_length()) { ob_end_clean(); }
    setcookie("download_status", "complete", ['expires' => time() + 60, 'path' => '/']);
    $filename = "Daftar_Pengadaan_Belanja_Modal_Bulan_" . ($filter_bulan > 0 ? $filter_bulan : 'All') . "_" . ($filter_tahun > 0 ? $filter_tahun : '2026') . ".xlsx";
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->setPreCalculateFormulas(false); // MATIKAN PRE-CALCULATE SUPAYA EKSEKUSI KILAT
    $writer->save('php://output');
    exit;
}

// ==============================================================================
// 2. LOGIKA BACKEND EXCEL: EXPORT KE TEMPLATE IMPORT INVENTARIS (BULK INJECTION)
// ==============================================================================
if (isset($_GET['proses_cetak_template'])) {

    require 'vendor/autoload.php';

    $template_file = 'template_import_inventaris.xlsx';
    if (!file_exists($template_file)) {
        ob_end_clean();
        header("HTTP/1.1 500 Internal Server Error");
        echo "File template ('$template_file') tidak ditemukan di direktori server.";
        exit;
    }

    $filter_bulan = isset($_GET['filter_bulan']) ? (int)$_GET['filter_bulan'] : 0;
    $filter_tahun = isset($_GET['filter_tahun']) ? (int)$_GET['filter_tahun'] : 0;
    $filter_barang = isset($_GET['filter_barang']) ? trim($_GET['filter_barang']) : '';

    $sql_excel = "SELECT r.* 
                  FROM `realisasi_barang_sekolah` r 
                  INNER JOIN `master_barang_sekolah` m ON r.id_master_barang = m.id 
                  WHERE r.`id_sekolah` = ? AND m.kategori = 'Peralatan & Mesin'";
                  
    $params_excel = [$id_sekolah_session];
    $types_excel = "s";

    if ($filter_bulan > 0) {
        $sql_excel .= " AND r.`bulan_realisasi` = ?";
        $params_excel[] = $filter_bulan;
        $types_excel .= "i";
    }
    if ($filter_tahun > 0) {
        $sql_excel .= " AND YEAR(r.`ba_tgl`) = ?";
        $params_excel[] = $filter_tahun;
        $types_excel .= "i";
    }
    if (!empty($filter_barang)) {
        $sql_excel .= " AND (r.`nama_barang` LIKE ? OR r.`kode_barang` LIKE ?)";
        $search_param = "%" . $filter_barang . "%";
        $params_excel[] = $search_param;
        $params_excel[] = $search_param;
        $types_excel .= "ss";
    }
    $sql_excel .= " ORDER BY r.`ba_tgl` ASC, r.`id` ASC";

    $stmtExcel = mysqli_prepare($conn, $sql_excel);
    mysqli_stmt_bind_param($stmtExcel, $types_excel, ...$params_excel);
    mysqli_stmt_execute($stmtExcel);
    $result = mysqli_stmt_get_result($stmtExcel);
    $totalRows = mysqli_num_rows($result);

    if ($totalRows === 0) {
        mysqli_stmt_close($stmtExcel);
        ob_end_clean();
        setcookie("download_status", "empty", ['expires' => time() + 60, 'path' => '/']);
        header("HTTP/1.1 204 No Content");
        exit;
    }

    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
    $spreadsheet = $reader->load($template_file);
    $sheet = $spreadsheet->getActiveSheet();

    $templateRows = []; 
    $currentCount = 0;

    while ($row = mysqli_fetch_assoc($result)) {
        $currentCount++;
        
        if ($currentCount === 1 || $currentCount % 100 === 0 || $currentCount === $totalRows) {
            if (session_status() === PHP_SESSION_NONE) { session_start(); }
            $_SESSION['progress_download'] = round(($currentCount / $totalRows) * 100); 
            session_write_close(); 
        }

        $tg = ''; $bl = ''; $thn = '';
        if (!empty($row['ba_tgl']) && $row['ba_tgl'] != '0000-00-00') {
            $time = strtotime($row['ba_tgl']);
            $tg = (int)date('d', $time); 
            $bl = (int)date('m', $time); 
            $thn = date('Y', $time);
        }

        $templateRows[] = [
            safeExcelVal(trim($row['kode_barang'])),
            safeExcelVal($row['nama_barang']),
            safeExcelVal($row['merk_tipe']),
            $tg,
            $bl,
            $thn,
            safeExcelVal($row['satuan']),
            (int)($row['volume'] ?? 0),
            (float)($row['harga_satuan'] ?? 0.0)
        ];
    }
    mysqli_stmt_close($stmtExcel);

    if (!empty($templateRows)) {
        $sheet->fromArray($templateRows, NULL, 'A2');
    }

    if (ob_get_length()) { ob_end_clean(); }
    setcookie("download_status", "complete", ['expires' => time() + 60, 'path' => '/']);
    $filename = "Format_Isian_Inventaris_Bulan_" . ($filter_bulan > 0 ? $filter_bulan : 'All') . "_" . ($filter_tahun > 0 ? $filter_tahun : '2026') . ".xlsx";
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->setPreCalculateFormulas(false);
    $writer->save('php://output');
    exit;
}

// --- LOGIKA FILTERING DATA UNTUK VIEW HALAMAN ---
$filter_barang = trim($_GET['filter_barang'] ?? '');
$filter_bulan  = isset($_GET['filter_bulan']) ? (int)$_GET['filter_bulan'] : 0;
$filter_tahun  = isset($_GET['filter_tahun']) ? (int)$_GET['filter_tahun'] : 0;

$sql_base = "FROM `realisasi_barang_sekolah` WHERE `id_sekolah` = ?";
$params_view = [$id_sekolah_session];
$types_view = "s";

if (!empty($filter_barang)) {
    $sql_base .= " AND (`nama_barang` LIKE ? OR `kode_barang` LIKE ?)";
    $search_view = "%" . $filter_barang . "%";
    $params_view[] = $search_view; $params_view[] = $search_view;
    $types_view .= "ss";
}
if ($filter_bulan > 0) {
    $sql_base .= " AND `bulan_realisasi` = ?";
    $params_view[] = $filter_bulan; $types_view .= "i";
}
if ($filter_tahun > 0) {
    $sql_base .= " AND YEAR(`ba_tgl`) = ?";
    $params_view[] = $filter_tahun; $types_view .= "i";
}

$akumulasi_biaya = 0; $akumulasi_volume = 0;
$sql_total = "SELECT SUM(`nilai_perolehan`) as total_all, SUM(`volume`) as total_vol " . $sql_base;
if ($stmtTotal = mysqli_prepare($conn, $sql_total)) {
    mysqli_stmt_bind_param($stmtTotal, $types_view, ...$params_view);
    mysqli_stmt_execute($stmtTotal);
    $resTotal = mysqli_stmt_get_result($stmtTotal);
    if ($dTotal = mysqli_fetch_assoc($resTotal)) {
        $akumulasi_biaya = $dTotal['total_all'] ?? 0;
        $akumulasi_volume = $dTotal['total_vol'] ?? 0;
    }
    mysqli_stmt_close($stmtTotal);
}

$sql_data = "SELECT * " . $sql_base . " ORDER BY `ba_tgl` DESC, `id` DESC";
$stmtData = mysqli_prepare($conn, $sql_data);
mysqli_stmt_bind_param($stmtData, $types_view, ...$params_view);
mysqli_stmt_execute($stmtData);
$result = mysqli_stmt_get_result($stmtData);

$qTahun_sql = "SELECT DISTINCT YEAR(`ba_tgl`) as thn FROM `realisasi_barang_sekolah` WHERE `id_sekolah` = ? AND `ba_tgl` IS NOT NULL ORDER BY thn DESC";
$stmtTahun = mysqli_prepare($conn, $qTahun_sql);
mysqli_stmt_bind_param($stmtTahun, "s", $id_sekolah_session);
mysqli_stmt_execute($stmtTahun);
$qTahun = mysqli_stmt_get_result($stmtTahun);

$bulan_list = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
?>

<div class="container-fluid px-0">
    <div class="row g-4 mb-4">
        <div class="col-md-6 col-xl-4">
            <div class="card p-4 border-0 shadow-sm bg-primary text-white" style="border-radius: 20px;">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="small text-white text-opacity-75 fw-semibold text-uppercase d-block mb-1">Total Nilai Perolehan</span>
                        <h3 class="fw-bold mb-0">Rp <?= number_format($akumulasi_biaya, 0, ',', '.'); ?></h3>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                        <i class="bi bi-cash-stack fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm p-4 mb-4" style="border-radius: 24px; background: #ffffff;">
        <h6 class="fw-bold text-dark mb-3"><i class="bi bi-funnel-fill text-primary me-2"></i>Filter Pencarian Realisasi</h6>
        
        <form id="form-filter-realisasi" method="GET" action="data_realisasi.php">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-secondary">Nama / Kode Barang</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" name="filter_barang" value="<?= htmlspecialchars($filter_barang, ENT_QUOTES, 'UTF-8'); ?>" class="form-control bg-light border-start-0 ps-0" placeholder="Ketik nama...">
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold text-secondary">Bulan Realisasi</label>
                    <select name="filter_bulan" class="form-select bg-light">
                        <option value="">-- Semua --</option>
                        <?php foreach ($bulan_list as $num => $nama) {
                            $selected = ($filter_bulan == $num) ? 'selected' : '';
                            echo "<option value='" . htmlspecialchars($num, ENT_QUOTES, 'UTF-8') . "' $selected>" . htmlspecialchars($nama, ENT_QUOTES, 'UTF-8') . "</option>";
                        } ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold text-secondary">Tahun</label>
                    <select name="filter_tahun" class="form-select bg-light">
                        <option value="">-- Semua --</option>
                        <?php while ($t = mysqli_fetch_assoc($qTahun)) {
                            $selected = ($filter_tahun == $t['thn']) ? 'selected' : '';
                            echo "<option value='" . htmlspecialchars($t['thn'], ENT_QUOTES, 'UTF-8') . "' $selected>" . htmlspecialchars($t['thn'], ENT_QUOTES, 'UTF-8') . "</option>";
                        } mysqli_stmt_close($stmtTahun); ?>
                    </select>
                </div>
                <div class="col-md-5 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary fw-semibold"><i class="bi bi-filter"></i> Cari</button>
                    <!-- TOMBOL DOWNLOAD -->
                    <button type="button" id="btn-download-excel" class="btn btn-success fw-semibold" title="Download Excel Sesuai Filter"><i class="bi bi-file-earmark-excel-fill"></i> Laporan</button>
                    <button type="button" id="btn-download-template" class="btn btn-info text-white fw-semibold" title="Download Format Template"><i class="bi bi-cloud-arrow-down-fill"></i> Template Isian</button>
                    <button type="button" id="btn-reset-filter" class="btn btn-light border" title="Reset Filter"><i class="bi bi-arrow-clockwise"></i></button>
                </div>
            </div>
        </form>
    </div>

    <div class="card border-0 shadow-sm p-4" style="border-radius: 24px; background: #ffffff;">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <h5 class="fw-bold text-dark mb-0"><i class="bi bi-table text-primary me-2"></i>Data Realisasi Belanja Modal</h5>
            <span class="badge bg-secondary bg-opacity-10 text-secondary px-3 py-2 rounded-pill fw-bold small">Total: <?= mysqli_num_rows($result); ?> Baris</span>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0" style="min-width: 1700px; border-color: #e2e8f0;">
                <thead class="table-light text-center small fw-bold text-secondary" style="background-color: #f1f5f9;">
                    <tr>
                        <th rowspan="2" class="align-middle" width="40">No</th>
                        <th rowspan="2" class="align-middle" width="130">No. SP2D</th>
                        <th rowspan="2" class="align-middle" width="130">Sumber Perolehan</th>
                        <th rowspan="2" class="align-middle" width="120">Kodering Belanja</th>
                        <th rowspan="2" class="align-middle" width="160">No. SPK /Faktur /Kuitansi</th>
                        <th colspan="4" class="py-2">BA Penerimaan</th>
                        <th rowspan="2" class="align-middle" width="120">Bulan Realisasi</th>
                        <th rowspan="2" class="align-middle" width="110">Kode Barang</th>
                        <th colspan="5" class="py-2">Rincian Barang</th>
                    </tr>
                    <tr>
                        <th width="140">No</th>
                        <th width="45">Tgl</th>
                        <th width="45">Bln</th>
                        <th width="50">Thn</th>
                        <th width="180">Nama Barang</th>
                        <th width="150">Merk/Tipe</th>
                        <th width="140">No.Sertifikat/ No.Rangka/ No.Mesin</th>
                        <th width="100">Ukuran (Gedung/ Bangunan)</th>
                        <th width="70">Satuan</th>
                        <th width="60">Volume</th>
                        <th width="120">Harga Satuan</th>
                        <th width="130">Nilai Perolehan</th>
                    </tr>
                </thead>
                <tbody class="small">
                    <?php if (mysqli_num_rows($result) === 0): ?>
                        <tr>
                            <td colspan="19" class="text-center text-muted py-5">
                                <i class="bi bi-folder-x fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                <p class="mb-0 fw-semibold">Tidak ditemukan data realisasi pengadaan aset.</p>
                            </td>
                        </tr>
                    <?php else: 
                        $no = 1;
                        while ($row = mysqli_fetch_assoc($result)): 
                            $tg = '-'; $bl = '-'; $thn = '-';
                            if (!empty($row['ba_tgl']) && $row['ba_tgl'] != '0000-00-00') {
                                $time_stamp = strtotime($row['ba_tgl']);
                                $tg  = date('d', $time_stamp);
                                $bl  = date('m', $time_stamp);
                                $thn = date('Y', $time_stamp);
                            }
                            $bln_realisasi_id = isset($row['bulan_realisasi']) ? (int)$row['bulan_realisasi'] : 0;
                            $nama_bulan_realisasi = $bulan_list[$bln_realisasi_id] ?? '-';
                    ?>
                        <tr>
                            <td class="text-center fw-bold text-secondary"><?= $no++; ?></td>
                            <td><span class="fw-semibold text-dark"><?= htmlspecialchars($row['no_sp2d'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                            <td><?= htmlspecialchars($row['sumber_perolehan'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><span class="badge bg-secondary bg-opacity-10 text-dark font-monospace"><?= htmlspecialchars($row['kodering_belanja'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                            <td><code class="text-primary fw-medium"><?= htmlspecialchars($row['no_spk'], ENT_QUOTES, 'UTF-8'); ?></code></td>
                            <td><?= htmlspecialchars($row['ba_no'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="text-center"><?= htmlspecialchars($tg, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="text-center"><?= htmlspecialchars($bl, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="text-center fw-medium"><?= htmlspecialchars($thn, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="text-center fw-semibold text-info"><?= htmlspecialchars($nama_bulan_realisasi, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="font-monospace text-secondary"><?= htmlspecialchars($row['kode_barang'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="fw-bold text-dark"><?= htmlspecialchars($row['nama_barang'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($row['merk_tipe'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="text-muted"><?= htmlspecialchars($row['no_sertifikat'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="text-center"><?= htmlspecialchars($row['ukuran_bangunan'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="text-center"><?= htmlspecialchars($row['satuan'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="text-center fw-bold text-dark"><?= number_format($row['volume'], 0, ',', '.'); ?></td>
                            <td class="text-end text-secondary">Rp <?= number_format($row['harga_satuan'], 0, ',', '.'); ?></td>
                            <td class="text-end fw-bold text-primary" style="background-color: #f8fafc;">Rp <?= number_format($row['nilai_perolehan'], 0, ',', '.'); ?></td>
                        </tr>
                    <?php endwhile; mysqli_stmt_close($stmtData); endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalProgressExcel" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="modalProgressExcelLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 20px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
            <div class="modal-body text-center p-5">
                <div class="spinner-border text-success mb-4" role="status" style="width: 3.5rem; height: 3.5rem; border-width: 4px;">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <h5 class="fw-bold text-dark mb-1" id="titleProgressExcel">Mengolah Data Realisasi</h5>
                <p class="text-muted small mb-4">Mohon tunggu, server sedang merender file Excel Anda.</p>
                <div class="progress mb-3" style="height: 22px; border-radius: 30px; background-color: #e2e8f0; overflow: hidden;">
                    <div id="barProgressExcel" class="progress-bar progress-bar-striped progress-bar-animated bg-success fw-bold" role="progressbar" style="width: 0%; transition: width 0.2s ease;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                </div>
                <div id="textStatusExcel" class="small fw-semibold text-secondary">Persiapan awal data...</div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEmptyExcel" tabindex="-1" aria-labelledby="modalEmptyExcelLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 20px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
            <div class="modal-body text-center p-5">
                <i class="bi bi-folder-x text-warning mb-3 d-block" style="font-size: 4rem;"></i>
                <h5 class="fw-bold text-dark mb-2">Data Tidak Ditemukan</h5>
                <p class="text-muted small mb-4">Tidak ada data realisasi yang dapat diexport berdasarkan filter saat ini.</p>
                <button type="button" class="btn btn-warning text-white fw-bold px-4 rounded-pill" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        const filterForm = document.getElementById('form-filter-realisasi');
        const resetBtn = document.getElementById('btn-reset-filter');
        const excelBtn = document.getElementById('btn-download-excel');
        const templateBtn = document.getElementById('btn-download-template');
        
        if (filterForm) {
            filterForm.addEventListener('submit', function(e) {
                e.preventDefault(); 
                const bng = filterForm.querySelector('[name="filter_barang"]').value;
                const bln = filterForm.querySelector('[name="filter_bulan"]').value;
                const thn = filterForm.querySelector('[name="filter_tahun"]').value;
                const targetUrl = `data_realisasi.php?filter_barang=${encodeURIComponent(bng)}&filter_bulan=${bln}&filter_tahun=${thn}`;
                
                if (typeof loadPage === 'function') {
                    loadPage(targetUrl, 'Data Realisasi Aset');
                }
            });
        }

        function jalankanProsesDownload(urlParameter) {
            document.cookie = "download_status=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";

            const barProgress = document.getElementById('barProgressExcel');
            const textStatus = document.getElementById('textStatusExcel');
            const titleProgress = document.getElementById('titleProgressExcel');
            
            barProgress.style.width = '0%';
            barProgress.setAttribute('aria-valuenow', 0);
            barProgress.innerText = '0%';
            textStatus.innerText = 'Menghubungkan ke server...';
            titleProgress.innerText = 'Mengolah Dokumen Excel';

            const targetModalElement = document.getElementById('modalProgressExcel');
            const bsModalInstance = new bootstrap.Modal(targetModalElement);
            bsModalInstance.show();

            window.location.href = `data_realisasi.php?${urlParameter}`;
            
            let intervalCekProgress = setInterval(function() {
                fetch('data_realisasi.php?get_progress=true')
                    .then(res => res.json())
                    .then(data => {
                        let persenVal = parseInt(data.progress) || 0;
                        if (persenVal > 100) persenVal = 100;
                        
                        barProgress.style.width = persenVal + '%';
                        barProgress.setAttribute('aria-valuenow', persenVal);
                        barProgress.innerText = persenVal + '%';
                        
                        if (persenVal < 100) {
                            textStatus.innerText = `Memproses: ${persenVal}% baris data`;
                        } else {
                            textStatus.innerText = 'Selesai 100%! Menunggu browser mengunduh...';
                        }
                    })
                    .catch(err => console.error('Gagal mengambil status progress:', err));

                let cekCookieComplete = document.cookie.split(';').filter((item) => item.trim().startsWith('download_status=complete')).length;
                let cekCookieEmpty = document.cookie.split(';').filter((item) => item.trim().startsWith('download_status=empty')).length;
                
                if (cekCookieComplete > 0 || cekCookieEmpty > 0) {
                    clearInterval(intervalCekProgress);
                    setTimeout(function() {
                        bsModalInstance.hide();
                        if (cekCookieEmpty > 0) {
                            const emptyModalElement = document.getElementById('modalEmptyExcel');
                            const bsEmptyModal = new bootstrap.Modal(emptyModalElement);
                            bsEmptyModal.show();
                        }
                        document.cookie = "download_status=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
                    }, 1000);
                }
            }, 400);
        }

        if (excelBtn) {
            excelBtn.addEventListener('click', function() {
                const bng = filterForm.querySelector('[name="filter_barang"]').value;
                const bln = filterForm.querySelector('[name="filter_bulan"]').value;
                const thn = filterForm.querySelector('[name="filter_tahun"]').value;
                
                const param = `proses_cetak_excel=true&filter_barang=${encodeURIComponent(bng)}&filter_bulan=${bln}&filter_tahun=${thn}`;
                jalankanProsesDownload(param);
            });
        }

        if (templateBtn) {
            templateBtn.addEventListener('click', function() {
                const bng = filterForm.querySelector('[name="filter_barang"]').value;
                const bln = filterForm.querySelector('[name="filter_bulan"]').value;
                const thn = filterForm.querySelector('[name="filter_tahun"]').value;
                
                const param = `proses_cetak_template=true&filter_barang=${encodeURIComponent(bng)}&filter_bulan=${bln}&filter_tahun=${thn}`;
                jalankanProsesDownload(param);
            });
        }

        if (resetBtn) {
            resetBtn.addEventListener('click', function() {
                if (typeof loadPage === 'function') {
                    loadPage('data_realisasi.php', 'Data Realisasi Aset');
                }
            });
        }
    })();
</script>