<?php
/**
 * SISTEM MONITORING BERKAS UNIT (PENCARIAN NAMA & NPSN)
 * Clean, Modular & Fixed Filter Engine (2026) - Hardened & Secure Edition
 * Integrated with Back Arrow to pilih_bulan_rekapan.php
 * Feature Added: ACC Realisasi, Buka Kunci Edit, Auto-Sort TUNTAS/Disetujui on Top.
 * Update: Konfirmasi Nama Sekolah & Auto-Close Modal 3 Detik.
 */

// === KEAMANAN TAMBAHAN 1: SESSION & CSRF MANAGEMENT ===
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

// === VALIDASI AKSES KHUSUS ADMIN ===
if (!isset($_SESSION['login']) || ($_SESSION['role'] ?? '') !== 'admin') {
    if (isset($_POST['action']) || isset($_GET['ajax_id_sekolah'])) {
        header('HTTP/1.1 403 Forbidden');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => '🚨 Akses ditolak! Halaman ini hanya dapat diakses oleh Admin.']);
        exit;
    }
    header("Location: login.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// === KEAMANAN TAMBAHAN 2: PROTEKSI HEADER HTTP & CSP ===
header("X-Frame-Options: SAMEORIGIN"); 
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://static.cloudflareinsights.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; font-src 'self' https://cdnjs.cloudflare.com; img-src 'self' data:; connect-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://cloudflareinsights.com https://static.cloudflareinsights.com;");

// 1. CONFIG & DATABASE CONNECTION (host/user/pass + DB_MAIN dari .env, fallback lokal)
require __DIR__ . '/env.php';
$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_NAME = getenv('DB_MAIN') ?: 'belanja_modal';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '';

try {
    $pdo = new PDO("mysql:host=" . $DB_HOST . ";dbname=" . $DB_NAME . ";charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    die("Koneksi database gagal. Silakan hubungi administrator sistem.");
}

// HELPER SANITASI XSS UTILS
function e($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function e_js($value) {
    return htmlspecialchars(json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
}

// 2. HELPER FUNCTIONS (STANDARISASI FORMAT BULAN DATABASE)
function dapatkanBulanSistem($bulan) {
    $bulan = strtolower(trim((string)$bulan));
    if (empty($bulan) || $bulan == '-') return '-';
    
    $map = [
        '1'  => ['januari', 'jan', '1', '01'], 
        '2'  => ['februari', 'feb', '2', '02'],
        '3'  => ['maret', 'mar', '3', '03'],   
        '4'  => ['april', 'apr', '4', '04'],
        '5'  => ['mei', '5', '05'],     
        '6'  => ['juni', 'jun', '6', '06'],
        '7'  => ['juli', 'jul', '7', '07'],    
        '8'  => ['agustus', 'agt', 'aug', '8', '08'],
        '9'  => ['september', 'sep', '9', '09'],
        '10' => ['oktober', 'okt', '10'],
        '11' => ['november', 'nov', '11'],     
        '12' => ['desember', 'des', '12']
    ];
    foreach ($map as $key => $values) {
        if (in_array($bulan, $values, true)) {
            return $key;
        }
    }
    return e($bulan);
}

function konversiAngkaKeBulanIndo($angka) {
    $map = [
        '1' => 'JANUARI', '2' => 'FEBRUARI', '3' => 'MARET', '4' => 'APRIL',
        '5' => 'MEI', '6' => 'JUNI', '7' => 'JULI', '8' => 'AGUSTUS',
        '9' => 'SEPTEMBER', '10' => 'OKTOBER', '11' => 'NOVEMBER', '12' => 'DESEMBER'
    ];
    return $map[$angka] ?? e(strtoupper((string)$angka));
}

// 2B. AJAX POST HANDLER (ACC LAPORAN & BUKA EDIT)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // VALIDASI CSRF TOKEN & REFERER
    $csrf_token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => '🚨 Akses ditolak: Token Keamanan (CSRF) tidak valid.']);
        exit;
    }

    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($referer && parse_url($referer, PHP_URL_HOST) !== $host) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => '🚨 Akses ditolak: Permintaan tidak sah (CSRF).']);
        exit;
    }

    $id_sekolah = filter_var($_POST['id_sekolah'] ?? 0, FILTER_VALIDATE_INT);
    $bulan_target = trim((string)($_POST['bulan'] ?? ''));
    $bulan_num = dapatkanBulanSistem($bulan_target);

    if (!$id_sekolah || empty($bulan_target)) {
        echo json_encode(['success' => false, 'message' => 'Parameter input tidak valid.']);
        exit;
    }

    $action = $_POST['action'];

    // AKSI 1: ACC LAPORAN (Ubah Status Jadi Disetujui)
    if ($action === 'acc_realisasi') {
        try {
            $stmt = $pdo->prepare("UPDATE laporan_realisasi SET status = 'Disetujui' WHERE id_sekolah = ? AND (bulan = ? OR bulan = ?)");
            $stmt->execute([$id_sekolah, $bulan_target, $bulan_num]);
            echo json_encode(['success' => true, 'message' => 'Laporan realisasi berhasil disetujui (Disetujui)!']);
        } catch (Exception $e) {
            error_log("ACC Realisasi Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Gagal menyetujui laporan realisasi.']);
        }
        exit;
    }

    // AKSI 2: BUKA EDIT (Kembalikan status ke Belum Kirim agar user bisa input lagi tanpa hapus data fisik)
    if ($action === 'buka_edit_realisasi') {
        try {
            $stmt = $pdo->prepare("DELETE FROM laporan_realisasi WHERE id_sekolah = ? AND (bulan = ? OR bulan = ?)");
            $stmt->execute([$id_sekolah, $bulan_target, $bulan_num]);
            echo json_encode(['success' => true, 'message' => 'Kunci sukses dibuka! User sekarang bisa mengisi kembali data inputan realisasi.']);
        } catch (Exception $e) {
            error_log("Buka Edit Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Gagal membuka kunci edit user.']);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenali.']);
    exit;
}

// 3. ROUTER & AJAX HANDLER (DETAIL RINCIAN KODERING & REKENING ACUAN)
if (isset($_GET['ajax_id_sekolah']) && isset($_GET['ajax_bulan'])) {
    $id_sekolah = filter_var($_GET['ajax_id_sekolah'], FILTER_VALIDATE_INT);
    $bulan_target = trim((string)$_GET['ajax_bulan']); 
    $bulan_num = dapatkanBulanSistem($bulan_target);

    if (!$id_sekolah) {
        echo "<div class='alert alert-danger m-3'>ID Sekolah tidak valid.</div>";
        exit;
    }

    // Ambil info nama sekolah & npsn
    $stmt = $pdo->prepare("SELECT satuan_pendidikan, npsn FROM data_barang_acuan WHERE id_sekolah = ? LIMIT 1");
    $stmt->execute([$id_sekolah]);
    $sekolah = $stmt->fetch() ?: ['satuan_pendidikan' => 'SEKOLAH ID ' . $id_sekolah, 'npsn' => '-'];
    $nama_sekolah_raw = strtoupper($sekolah['satuan_pendidikan']);
    
    // Cek Status Approval saat ini dari tabel laporan_realisasi
    $stmt_status = $pdo->prepare("SELECT status FROM laporan_realisasi WHERE id_sekolah = ? AND (bulan = ? OR bulan = ?) LIMIT 1");
    $stmt_status->execute([$id_sekolah, $bulan_target, $bulan_num]);
    $status_berkas = $stmt_status->fetch();
    $current_status = $status_berkas ? $status_berkas['status'] : 'Belum Kirim';

    // Ambil semua data acuan untuk sekolah ini
    $stmt = $pdo->prepare("SELECT * FROM data_barang_acuan WHERE id_sekolah = ?");
    $stmt->execute([$id_sekolah]);
    $semua_acuan = $stmt->fetchAll();

    $list_acuan_bulan = [];
    foreach ($semua_acuan as $ac) {
        if (dapatkanBulanSistem($ac['bulan']) === dapatkanBulanSistem($bulan_target)) {
            $list_acuan_bulan[] = $ac;
        }
    }

    // Peta Realisasi Fisik Berdasarkan ID Uraian Acuan
    $realisasi_map = [];
    if (count($list_acuan_bulan) > 0) {
        $placeholders = implode(',', array_fill(0, count($list_acuan_bulan), '?'));
        $ids_acuan = array_column($list_acuan_bulan, 'id');
        
        $stmt_r = $pdo->prepare("SELECT id_uraian, SUM(nilai_perolehan) as total FROM realisasi_barang_sekolah WHERE id_sekolah = ? AND id_uraian IN ($placeholders) GROUP BY id_uraian");
        $stmt_r->execute(array_merge([$id_sekolah], $ids_acuan));
        
        foreach ($stmt_r->fetchAll() as $r) {
            $realisasi_map[$r['id_uraian']] = (float)$r['total'];
        }
    }

    // Grouping Kodering Konten AJAX
    $grouped_data = [];
    $totals = ['acuan' => 0, 'realisasi' => 0, 'kekurangan' => 0, 'valid' => 0, 'total_item' => 0];

    foreach ($list_acuan_bulan as $ac) {
        $kodering = trim($ac['kodering']) ?: 'TANPA KODERING';
        $nominal_acuan = (float)$ac['nominal'];
        $nominal_realisasi = $realisasi_map[$ac['id']] ?? 0;

        if (!isset($grouped_data[$kodering])) {
            $grouped_data[$kodering] = [
                'kodering'   => $kodering, 
                'acuan'      => 0, 
                'realisasi'  => 0, 
                'kekurangan' => 0, 
                'uraian'     => []
            ];
        }

        $grouped_data[$kodering]['acuan'] += $nominal_acuan;
        $grouped_data[$kodering]['realisasi'] += $nominal_realisasi;
        $grouped_data[$kodering]['uraian'][] = $ac['uraian'];
    }

    foreach ($grouped_data as $kodering => $data) {
        $kekurangan_grup = max(0, $data['acuan'] - $data['realisasi']);
        $grouped_data[$kodering]['kekurangan'] = $kekurangan_grup;

        if ($kodering !== 'TANPA KODERING') {
            $totals['total_item']++;
            if ($data['realisasi'] >= $data['acuan'] && $data['acuan'] > 0) {
                $totals['valid']++;
            }
        }

        $totals['acuan'] += $data['acuan'];
        $totals['realisasi'] += $data['realisasi'];
        $totals['kekurangan'] += $kekurangan_grup;
    }
    ?>
    <div class="card p-4 mb-4 bg-white panel-detail-box">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <h5 class="fw-bold text-dark mb-1"><i class="fa-regular fa-folder-open text-primary me-2"></i>Rincian Rekening Acuan & Input Realisasi (Bulan <?= e(strtoupper($bulan_target)); ?>)</h5>
                <p class="text-secondary small mb-0">Kelola persetujuan laporan berkas unit sekolah dan sinkronisasi status penguncian input fisik.</p>
                <div class="mt-2">
                    <span class="badge bg-secondary">Mode Admin: Kontrol Penuh Akses</span>
                    <span class="badge bg-dark">Status Saat Ini: <?= e(strtoupper($current_status)); ?></span>
                </div>
            </div>
            <div class="d-flex gap-2">
                <?php if ($current_status === 'Menunggu Approval'): ?>
                    <button onclick="prosesAccLangsung(<?= $id_sekolah; ?>, <?= e_js($bulan_target); ?>, <?= e_js($nama_sekolah_raw); ?>)" class="btn btn-success btn-sm fw-bold px-3 text-white" style="border-radius: 10px;">
                        <i class="fa-solid fa-circle-check me-1"></i> ACC Laporan
                    </button>
                <?php endif; ?>
                
                <?php if ($current_status === 'Menunggu Approval' || $current_status === 'Disetujui'): ?>
                    <button onclick="prosesBukaEdit(<?= $id_sekolah; ?>, <?= e_js($bulan_target); ?>, <?= e_js($nama_sekolah_raw); ?>)" class="btn btn-warning btn-sm fw-bold px-3 text-dark" style="border-radius: 10px;">
                        <i class="fa-solid fa-unlock me-1"></i> Buka Kunci Edit User
                    </button>
                <?php endif; ?>

                <button onclick="tutupDetail()" class="btn btn-light btn-sm fw-semibold border text-secondary px-3" style="border-radius: 10px;">
                    <i class="fa-solid fa-xmark me-1"></i> Tutup
                </button>
            </div>
        </div>
    </div>

    <ul class="nav nav-pills mb-3 gap-2" id="detailTab" role="tablist">
        <li class="nav-item"><button class="nav-link active fw-bold px-4 sub-tab-btn" id="acuan-tab" data-bs-toggle="tab" data-bs-target="#acuan-pane" type="button" role="tab">REKENING ACUAN</button></li>
        <li class="nav-item"><button class="nav-link fw-bold px-4 sub-tab-btn" id="realisasi-tab" data-bs-toggle="tab" data-bs-target="#realisasi-pane" type="button" role="tab">INPUT REALISASI (LOG FISIK)</button></li>
    </ul>

    <div class="tab-content" id="detailTabContent">
        <div class="tab-pane fade show active" id="acuan-pane" role="tabpanel">
            <div class="card p-0 overflow-hidden bg-white shadow-sm mb-5 border-card-main">
                <div class="p-4 border-bottom bg-light bg-opacity-50 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-dark">Daftar Rekening Belanja Modal</h6>
                    <span class="badge bg-primary px-3 py-2 fw-semibold" style="border-radius: 8px;"><?= e(strtoupper($sekolah['satuan_pendidikan'])); ?></span>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0 text-dark">
                        <thead class="table-light text-secondary header-th-style">
                            <tr>
                                <th width="35%" class="ps-4 py-3">Kode Rekening</th>
                                <th width="20%" class="text-end py-3">Nilai Acuan</th>
                                <th width="20%" class="text-end py-3">Realisasi</th>
                                <th width="15%" class="text-end py-3">Kekurangan</th>
                                <th width="10%" class="text-center py-3 pe-4">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if (count($grouped_data) > 0):
                                $i = 0; 
                                foreach ($grouped_data as $kodering => $data): $i++; 
                                    $isMatch = ($data['kekurangan'] <= 0);
                                ?>
                                <tr class="main-row-clickable">
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center gap-2">
                                            <button class="btn p-0 border-0 btn-toggle-kotak" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?= $i; ?>" style="box-shadow: none;">
                                                <span class="box-icon-plusminus text-primary d-flex align-items-center justify-content-center" id="box-icon-<?= $i; ?>">
                                                    <i class="fa-solid fa-plus"></i>
                                                </span>
                                            </button>
                                            <span class="text-kodering-bold"><?= e($kodering); ?></span>
                                        </div>
                                    </td>
                                    <td class="text-end text-secondary fw-bold fs-15">Rp <?= number_format($data['acuan'], 0, ',', '.'); ?></td>
                                    <td class="text-end text-primary fw-bold fs-15">Rp <?= number_format($data['realisasi'], 0, ',', '.'); ?></td>
                                    <td class="text-end <?= $isMatch ? 'text-success':'text-danger'; ?> fw-bold fs-15">Rp <?= number_format($data['kekurangan'], 0, ',', '.'); ?></td>
                                    <td class="text-center pe-4">
                                        <span class="badge bg-opacity-10 text-uppercase px-2 py-1 border status-badge-style <?= $isMatch ? 'bg-success text-success border-success-subtle':'bg-danger text-danger border-danger-subtle'; ?>">
                                            <?= $isMatch ? 'SESUAI' : 'BELUM SESUAI'; ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr class="bg-light bg-opacity-10 border-0">
                                    <td colspan="5" class="p-0 border-0">
                                        <div class="collapse collapse-uraian-section" id="collapse-<?= $i; ?>" data-id-box="<?= $i; ?>">
                                            <div class="px-4 py-2" style="background-color: #f8fafc;">
                                                <div class="ps-3 border-start border-primary border-3 wrapper-list-uraian">
                                                    <div class="text-secondary fw-bold mb-1 header-uraian-title">Daftar Uraian Pekerjaan:</div>
                                                    <ol class="m-0 p-0 ps-3 list-ol-style">
                                                        <?php foreach ($data['uraian'] as $text): ?>
                                                            <li><?= e($text); ?></li>
                                                        <?php endforeach; ?>
                                                    </ol>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php 
                                endforeach;
                            else:
                                echo "<tr><td colspan='5' class='text-center text-secondary py-4'>Tidak ada data target belanja acuan untuk bulan ini.</td></tr>";
                            endif; 
                            ?>
                        </tbody>
                        <tfoot class="table-light fw-bold footer-tfoot-style">
                            <tr>
                                <td class="ps-4 py-3 text-dark">TOTAL</td>
                                <td class="text-end text-dark">Rp <?= number_format($totals['acuan'], 0, ',', '.'); ?></td>
                                <td class="text-end text-primary">Rp <?= number_format($totals['realisasi'], 0, ',', '.'); ?></td>
                                <td class="text-end <?= ($totals['kekurangan'] <= 0) ? 'text-success':'text-danger'; ?>">Rp <?= number_format($totals['kekurangan'], 0, ',', '.'); ?></td>
                                <td class="text-center text-muted pe-4 fs-11 font-normal"><?= $totals['valid']; ?> / <?= $totals['total_item']; ?> VALID</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="realisasi-pane" role="tabpanel">
            <div class="card p-0 overflow-hidden bg-white shadow-sm mb-5 border-card-main">
                <div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center">
                    <span class="text-muted small fw-bold"><i class="fa-solid fa-list-check me-1"></i> LOG FISIK REALISASI INPUT USER</span>
                    <?php if ($current_status === 'Menunggu Approval' || $current_status === 'Disetujui'): ?>
                        <button onclick="prosesBukaEdit(<?= $id_sekolah; ?>, <?= e_js($bulan_target); ?>, <?= e_js($nama_sekolah_raw); ?>)" class="btn btn-warning btn-xs fw-bold text-dark border-0" style="font-size:12px; border-radius:6px;">
                            <i class="fa-solid fa-unlock me-1"></i> Buka Kunci Edit Sekarang
                        </button>
                    <?php else: ?>
                        <span class="badge bg-success font-normal" style="font-size:11px;"><i class="fa-solid fa-lock-open me-1"></i> Status Pengisian: Terbuka (User Bisa Isi Data)</span>
                    <?php endif; ?>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 text-dark" style="min-width:1400px; font-size:14px;">
                        <thead class="table-light text-secondary fw-bold" style="font-size: 13px;">
                            <tr>
                                <th class="ps-3">ID</th><th>NO SP2D</th><th>TANGGAL</th><th>TAHUN</th><th>KODERING</th><th>BULAN REALISASI</th><th>KODE & NAMA BARANG</th><th>SPESIFIKASI MERK</th><th>VOL</th><th>HARGA</th><th>TOTAL NILAI</th><th class="text-center">STATUS KUNCI</th>
                            </tr>
                        </thead>
                        <tbody class="fw-bold">
                            <?php
                            $rows = [];
                            if (count($list_acuan_bulan) > 0) {
                                $stmt = $pdo->prepare("SELECT * FROM realisasi_barang_sekolah WHERE id_sekolah = ? AND id_uraian IN ($placeholders)");
                                $stmt->execute(array_merge([$id_sekolah], $ids_acuan));
                                $rows = $stmt->fetchAll();
                            }
                            
                            if (count($rows) > 0):
                                foreach ($rows as $rl):
                            ?>
                                <tr>
                                    <td class="ps-3 text-muted font-monospace font-normal">#<?= e($rl['id']); ?></td>
                                    <td><?= e($rl['no_sp2d']); ?></td>
                                    <td><?= !empty($rl['ba_tgl']) ? e(date('d-m-Y', strtotime($rl['ba_tgl']))) : '-'; ?></td>
                                    <td><?= !empty($rl['ba_tgl']) ? e(date('Y', strtotime($rl['ba_tgl']))) : '-'; ?></td>
                                    <td class="text-dark fw-bold fs-14"><?= e($rl['kodering_belanja']); ?></td>
                                    <td class="text-uppercase"><?= e($rl['bulan_realisasi']); ?></td>
                                    <td><strong><?= e($rl['zip_kode_barang'] ?? $rl['kode_barang']); ?></strong><br><span class="text-secondary text-uppercase small style-label-sub"><?= e(strtoupper($rl['nama_barang'])); ?></span></td>
                                    <td><?= e($rl['merk_tipe']); ?></td>
                                    <td><?= e($rl['volume'] . ' ' . $rl['satuan']); ?></td>
                                    <td class="text-end">Rp <?= number_format($rl['harga_satuan'], 0, ',', '.'); ?></td>
                                    <td class="text-end text-primary fw-bold">Rp <?= number_format($rl['nilai_perolehan'], 0, ',', '.'); ?></td>
                                    <td class="text-center">
                                        <?php if ($current_status === 'Menunggu Approval' || $current_status === 'Disetujui'): ?>
                                            <span class="badge bg-danger text-uppercase px-2 py-1" style="font-size:11px; border-radius:5px;"><i class="fa-solid fa-lock me-1"></i> Terkunci</span>
                                        <?php else: ?>
                                            <span class="badge bg-success text-uppercase px-2 py-1" style="font-size:11px; border-radius:5px;"><i class="fa-solid fa-lock-open me-1"></i> Terbuka</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php 
                                endforeach;
                            else: 
                                echo "<tr><td colspan='12' class='text-center text-secondary py-4'>Belum ada rincian log input fisik realisasi pada bulan acuan ini.</td></tr>";
                            endif; 
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.querySelectorAll('.collapse-uraian-section').forEach(function(el) {
            const id = el.getAttribute('data-id-box');
            const box = document.getElementById('box-icon-' + id);
            el.addEventListener('show.bs.collapse', function () {
                if(box) { box.innerHTML = '<i class="fa-solid fa-minus"></i>'; box.className = 'box-icon-plusminus open-style'; }
            });
            el.addEventListener('hide.bs.collapse', function () {
                if(box) { box.innerHTML = '<i class="fa-solid fa-plus"></i>'; box.className = 'box-icon-plusminus'; }
            });
        });
    </script>
    <?php
    exit;
}

// 4. CORE DATA ENGINE (DIPANGGIL SAAT HALAMAN DILOAD)
$bulan_target_admin = isset($_GET['bulan_target']) ? trim((string)$_GET['bulan_target']) : '4'; 
$nama_bulan_indonesia = konversiAngkaKeBulanIndo($bulan_target_admin);

$master_sekolah_data = [];
$counter = ['tuntas' => 0, 'belum' => 0];

// Tarik data acuan mentah
$raw_acuan = $pdo->query("SELECT id_sekolah, satuan_pendidikan, npsn, bulan FROM data_barang_acuan WHERE id_sekolah > 0")->fetchAll();

foreach ($raw_acuan as $row) {
    $id_sek = $row['id_sekolah'];
    $bulan_raw = $row['bulan'] ?: '-';
    $bulan_num = dapatkanBulanSistem($bulan_raw); 

    if ($bulan_num === dapatkanBulanSistem($bulan_target_admin)) {
        $key_unik = $id_sek . '_' . strtolower(trim($bulan_raw)); 

        if (!isset($master_sekolah_data[$key_unik])) {
            $master_sekolah_data[$key_unik] = [
                'id_sekolah' => $id_sek, 
                'nama' => strtoupper($row['satuan_pendidikan']), 
                'npsn' => $row['npsn'],
                'bulan_disp' => $bulan_raw, 
                'bulan_filter' => $bulan_num,
                'status_kirim' => 'Belum Kirim'
            ];
        }
    }
}

// Ambil data status pengiriman/approval sekaligus untuk dicocokkan ke baris tabel utama
$raw_approval = $pdo->query("SELECT id_sekolah, bulan, status FROM laporan_realisasi")->fetchAll();
$approval_map = [];
foreach ($raw_approval as $app) {
    $approval_map[$app['id_sekolah'] . '_' . dapatkanBulanSistem($app['bulan'])] = $app['status'];
}

// Prepared Statement reusable untuk target acuan
$stmt_a = $pdo->prepare("SELECT id, nominal, bulan, kodering FROM data_barang_acuan WHERE id_sekolah = ? AND kodering != ''");

// Hitung status Tuntas / Belum Tuntas data master sekolah
foreach ($master_sekolah_data as $key_unik => $val) {
    $id_l = $val['id_sekolah'];
    $bln_l = $val['bulan_disp'];
    $bln_f = $val['bulan_filter'];
    
    // Inject status kirim approval terbaru
    $master_sekolah_data[$key_unik]['status_kirim'] = $approval_map[$id_l . '_' . $bln_f] ?? 'Belum Kirim';

    $stmt_a->execute([$id_l]);
    $semua_targets = $stmt_a->fetchAll();

    $grup_target_admin = [];
    foreach ($semua_targets as $tgt) {
        if (dapatkanBulanSistem($tgt['bulan']) === dapatkanBulanSistem($bulan_target_admin)) {
            $kodering_key = trim($tgt['kodering']);
            if (!isset($grup_target_admin[$kodering_key])) {
                $grup_target_admin[$kodering_key] = [
                    'nominal_acuan' => 0,
                    'ids_uraian' => []
                ];
            }
            $grup_target_admin[$kodering_key]['nominal_acuan'] += (float)$tgt['nominal'];
            $grup_target_admin[$kodering_key]['ids_uraian'][] = $tgt['id'];
        }
    }

    $isTuntas = true;
    $totalKodering = count($grup_target_admin); 
    $matchKoderingCount = 0; 

    if ($totalKodering > 0) {
        $semua_ids_bulan = [];
        foreach ($grup_target_admin as $grup) {
            $semua_ids_bulan = array_merge($semua_ids_bulan, $grup['ids_uraian']);
        }

        $reals = [];
        if (!empty($semua_ids_bulan)) {
            $placeholders = implode(',', array_fill(0, count($semua_ids_bulan), '?'));
            $stmt_r = $pdo->prepare("SELECT id_uraian, SUM(nilai_perolehan) as total FROM realisasi_barang_sekolah WHERE id_sekolah = ? AND id_uraian IN ($placeholders) GROUP BY id_uraian");
            $stmt_r->execute(array_merge([$id_l], $semua_ids_bulan));
            
            foreach ($stmt_r->fetchAll() as $r) { 
                $reals[$r['id_uraian']] = (float)$r['total']; 
            }
        }

        foreach ($grup_target_admin as $kodering_key => $grup) {
            $total_realisasi_kodering = 0;
            foreach ($grup['ids_uraian'] as $id_uraian) {
                $total_realisasi_kodering += $reals[$id_uraian] ?? 0;
            }

            if ($total_realisasi_kodering >= $grup['nominal_acuan'] && $grup['nominal_acuan'] > 0) {
                $matchKoderingCount++;
            } else {
                $isTuntas = false;
            }
        }
    } else {
        $isTuntas = false;
    }

    // Jika sudah di-ACC (Disetujui) atau kalkulasi tuntas, beri status prioritas atas
    if (($master_sekolah_data[$key_unik]['status_kirim'] === 'Disetujui') || ($isTuntas && $totalKodering > 0)) {
        $counter['tuntas']++;
        $master_sekolah_data[$key_unik]['status'] = 'TUNTAS';
        $master_sekolah_data[$key_unik]['progres_html'] = '<span class="badge bg-success bg-opacity-10 text-success px-2 py-1 border border-success-subtle main-badge-bold">SELESAI</span>';
    } else {
        $counter['belum']++;
        $master_sekolah_data[$key_unik]['status'] = 'BELUM';
        $master_sekolah_data[$key_unik]['progres_html'] = '<span class="badge bg-primary bg-opacity-10 text-primary px-2 py-1 border border-primary-subtle main-badge-bold">'.$matchKoderingCount.' / '.$totalKodering.'</span>';
    }
}

// REALTIME SORTING ENGINE: Prioritaskan Disetujui/TUNTAS di bagian paling atas
uasort($master_sekolah_data, function($a, $b) {
    if ($a['status'] === $b['status']) {
        return strcasecmp($a['nama'], $b['nama']);
    }
    return ($a['status'] === 'TUNTAS') ? -1 : 1;
});
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="logolog.jpeg">
    <meta name="csrf-token" content="<?= e($_SESSION['csrf_token']); ?>">
    <title>SISTEM REKAPAN ADMIN</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #1e293b; }
        .navbar-custom { background-color: #ffffff; border-bottom: 1px solid #e2e8f0; padding: 15px 0; }
        .text-topbar-title { font-size: 20px; font-weight: 800; letter-spacing: -0.5px; color: #0f172a; }
        .text-header-title { font-size: 26px; font-weight: 800; letter-spacing: -0.6px; color: #0f172a; }
        
        .btn-kembali-panah { border-radius: 10px; font-weight: 700; font-size: 13px; color: #475569; background: #ffffff; border: 1px solid #cbd5e1; padding: 8px 16px; transition: all 0.2s; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .btn-kembali-panah:hover { background: #f1f5f9; color: #0f172a; border-color: #94a3b8; }

        .widget-rekapan-box { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 16px 20px; cursor: pointer; transition: all 0.2s ease; }
        .widget-rekapan-box:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(15, 23, 42, 0.05); }
        .widget-rekapan-box.active-filter-tuntas { border-color: #10b981; background-color: #f0fdf4; }
        .widget-rekapan-box.active-filter-belum { border-color: #ef4444; background-color: #fef2f2; }
        
        .search-filter-box { background-color: #ffffff; border: 1px solid #cbd5e1; border-radius: 12px; padding: 10px 16px; display: flex; align-items: center; }
        .search-filter-box input { border: none; outline: none; width: 100%; font-size: 14px; color: #0f172a; background: transparent; font-weight: 500; }
        .row-selected-highlight { background-color: #f1f5f9 !important; border-left: 4px solid #3b82f6; }
        .sticky-header-table { position: sticky; top: 0; z-index: 10; }
        
        .main-row-clickable:hover { background-color: #f8fafc !important; }
        .btn-toggle-kotak:hover .box-icon-plusminus { transform: scale(1.08); }
        .main-badge-bold { border-radius: 6px; font-size:11px; font-weight: 700; }
        
        .panel-detail-box { border-radius: 20px; border: 1px solid #e2e8f0; box-shadow: 0 10px 25px rgba(148, 163, 184, 0.05); }
        .sub-tab-btn { border-radius: 10px; font-size: 13px; }
        .border-card-main { border-radius: 20px; border: 1px solid #e2e8f0; }
        .header-th-style { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .box-icon-plusminus { width: 18px; height: 18px; border: 1px solid #3b82f6; border-radius: 3px; font-size: 9px; background: #f0f9ff; transition: all 0.2s; color: #3b82f6; }
        .box-icon-plusminus.open-style { background: #3b82f6; border-color: #3b82f6; color: #ffffff; }
        
        .text-kodering-bold { font-weight: 700 !important; color: #0f172a !important; font-size: 15px; letter-spacing: -0.2px; }
        .wrapper-list-uraian { max-height: 150px; overflow-y: auto; font-size: 11px; font-weight: 500; scrollbar-width: thin; }
        .header-uraian-title { font-size: 11px; letter-spacing: 0.1px; }
        .list-ol-style { line-height: 2; color: #64748b; }
        
        .fs-15 { font-size: 15px; } .fs-14 { font-size: 14px; } .fs-11 { font-size: 11px; }
        .font-normal { font-weight: normal !important; }
        .status-badge-style { border-radius: 6px; font-size:11px; font-weight: 700; }
        .footer-tfoot-style { font-size: 15px; border-top: 2px solid #cbd5e1; }
        .style-label-sub { font-weight: 700; }
    </style>
</head>
<body>

<nav class="navbar navbar-custom mb-4">
    <div class="container d-flex justify-content-between align-items-center">
        <span class="navbar-brand m-0 text-topbar-title"><i class="fa-solid fa-chart-pie text-primary me-2"></i>SISTEM KENDALI REALISASI</span>
        <a href="index_admin.php?p=pilih_bulan_rekapan.php" class="btn-kembali-panah">
            <i class="fa-solid fa-arrow-left"></i> Ganti Bulan 
        </a>
    </div>
</nav>

<div class="container mb-5">
    <div class="row align-items-center mb-4">
        <div class="col-lg-6">
            <h1 class="text-header-title m-0">Monitoring Kendali Realisasi</h1>
            <p class="text-secondary small m-0">Menampilkan Satuan Pendidikan Realisasi <span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle px-2 fw-bold">BULAN <?= e($nama_bulan_indonesia); ?></span>.</p>
        </div>
        <div class="col-lg-6">
            <div class="row g-3 justify-content-end">
                <div class="col-sm-5">
                    <div id="btn-filter-widget-tuntas" onclick="toggleWidgetFilter('TUNTAS')" class="widget-rekapan-box d-flex align-items-center justify-content-between">
                        <div>
                            <small class="text-muted d-block fw-bold mb-1" style="font-size: 11px;">SUDAH SELESAI</small>
                            <h3 class="m-0 text-success" style="font-size: 24px; font-weight:800;"><?= $counter['tuntas']; ?> <span style="font-size:14px; font-weight:600;">SEKOLAH</span></h3>
                        </div>
                        <div class="text-success opacity-75" style="font-size: 24px;"><i class="fa-solid fa-circle-check"></i></div>
                    </div>
                </div>
                <div class="col-sm-5">
                    <div id="btn-filter-widget-belum" onclick="toggleWidgetFilter('BELUM')" class="widget-rekapan-box d-flex align-items-center justify-content-between">
                        <div>
                            <small class="text-muted d-block fw-bold mb-1" style="font-size: 11px;">BELUM SELESAI</small>
                            <h3 class="m-0 text-danger" style="font-size: 24px; font-weight:800;"><?= $counter['belum']; ?> <span style="font-size:14px; font-weight:600;">SEKOLAH</span></h3>
                        </div>
                        <div class="text-danger opacity-75" style="font-size: 24px;"><i class="fa-solid fa-circle-exclamation"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-12">
            <div class="search-filter-box">
                <i class="fa-solid fa-magnifying-glass text-muted me-2" style="font-size: 13px;"></i>
                <input type="text" id="input-cari-sekolah" onkeyup="jalankanFilter()" placeholder="MASUKKAN NAMA SEKOLAH ATAU NPSN YANG INGIN DICARI...">
            </div>
        </div>
    </div>
    
    <div class="card p-0 overflow-hidden bg-white shadow-sm mb-5 border-card-main">
        <div class="table-responsive" style="max-height: 440px; overflow-y: auto;">
            <table class="table table-hover align-middle mb-0 text-dark" id="main-table-sekolah">
                <thead class="table-light text-secondary sticky-header-table header-th-style">
                    <tr>
                        <th width="5%" class="ps-4 py-3 text-center">No</th>
                        <th width="12%" class="py-3">NPSN</th>
                        <th width="33%" class="py-3">Satuan Pendidikan</th>
                        <th width="12%" class="text-center py-3">Bulan Acuan</th>
                        <th width="10%" class="text-center py-3">Progres</th>
                        <th width="13%" class="text-center py-3">Status Berkas</th>
                        <th width="15%" class="text-center py-3 pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody class="fw-bold fs-14">
                    <?php 
                    $no = 1; 
                    if (count($master_sekolah_data) > 0):
                        foreach ($master_sekolah_data as $key_baris => $sek): 
                            $stKirim = $sek['status_kirim'];
                            $nama_sekolah_raw = $sek['nama'];
                            
                            $badgeKirim = '<span class="badge bg-secondary opacity-75 rounded-pill font-normal" style="font-size:11px;">Belum Kirim</span>';
                            if ($stKirim === 'Menunggu Approval') {
                                $badgeKirim = '<span class="badge bg-warning text-dark rounded-pill" style="font-size:11px;"><i class="fa-solid fa-hourglass-half me-1"></i>Wait ACC</span>';
                            } elseif ($stKirim === 'Disetujui') {
                                $badgeKirim = '<span class="badge bg-success rounded-pill text-white" style="font-size:11px;"><i class="fa-solid fa-lock me-1"></i>Disetujui</span>';
                            }
                    ?>
                    <tr id="row-id-<?= e($key_baris); ?>" class="item-baris-sekolah" data-status-widget="<?= e($sek['status']); ?>">
                        <td class="text-center text-dark indeks-nomor ps-4"><?= $no++; ?></td>
                        <td class="cell-npsn text-dark"><?= e($sek['npsn'] ?: '-'); ?></td>
                        <td class="cell-nama-sekolah text-dark text-uppercase"><?= e($sek['nama']); ?></td>
                        <td class="text-center"><span class="kolom-bulan-acuan text-dark text-uppercase"><?= e($sek['bulan_disp']); ?></span></td>
                        <td class="text-center"><?= $sek['progres_html']; ?></td>
                        <td class="text-center cell-status-kirim"><?= $badgeKirim; ?></td>
                        <td class="text-center pe-4">
                            <div class="d-flex gap-1 justify-content-center align-items-center">
                                <button onclick="panggilDetailAJAX(<?= $sek['id_sekolah']; ?>, <?= e_js($sek['bulan_disp']); ?>, <?= e_js($key_baris); ?>)" class="btn btn-sm btn-primary fw-bold px-2 border-0 shadow-sm" style="border-radius: 8px; font-size: 12px;">
                                    <i class="fa-solid fa-eye"></i> Lihat
                                </button>
                                <?php if ($stKirim === 'Menunggu Approval'): ?>
                                    <button id="btn-acc-row-<?= e($key_baris); ?>" onclick="prosesAccLangsung(<?= $sek['id_sekolah']; ?>, <?= e_js($sek['bulan_disp']); ?>, <?= e_js($nama_sekolah_raw); ?>)" class="btn btn-sm btn-success fw-bold px-2 border-0 shadow-sm text-white" style="border-radius: 8px; font-size: 12px;" title="Setujui Laporan">
                                        <i class="fa-solid fa-check"></i> ACC
                                    </button>
                                <?php endif; ?>
                                <?php if ($stKirim === 'Menunggu Approval' || $stKirim === 'Disetujui'): ?>
                                    <button id="btn-unlock-row-<?= e($key_baris); ?>" onclick="prosesBukaEdit(<?= $sek['id_sekolah']; ?>, <?= e_js($sek['bulan_disp']); ?>, <?= e_js($nama_sekolah_raw); ?>)" class="btn btn-sm btn-warning fw-bold px-2 border-0 shadow-sm text-dark" style="border-radius: 8px; font-size: 12px;" title="Buka Kunci Edit User">
                                        <i class="fa-solid fa-unlock"></i> Buka Edit
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php 
                        endforeach; 
                    else:
                    ?>
                        <tr><td colspan="7" class="text-center text-secondary py-5">Tidak ada data target belanja acuan untuk kriteria bulan <?= e($nama_bulan_indonesia); ?>.</td></tr>
                    <?php endif; ?>
                    <tr id="tr-tidak-ditemukan" style="display: none;">
                        <td colspan="7" class="text-center text-secondary py-5">Tidak ada data unit sekolah yang cocok dengan pencarian.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div id="detail-ajax-container"></div>
</div>

<!-- ================= MODAL COMPONENTS ================= -->

<!-- MODAL KONFIRMASI ACC -->
<div class="modal fade" id="modalConfirmAcc" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-body text-center p-4">
                <div class="mb-3 text-success"><i class="fa-solid fa-circle-check fa-4x"></i></div>
                <h5 class="fw-bold text-dark mb-1">Konfirmasi ACC</h5>
                <div class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle px-3 py-2 mb-3 w-100" style="white-space: normal;" id="accTargetName">NAMA SEKOLAH</div>
                <p class="text-secondary small mb-4">Apakah Anda yakin ingin menyetujui (ACC) laporan realisasi unit sekolah ini?</p>
                <div class="d-flex justify-content-center gap-2">
                    <button type="button" class="btn btn-light fw-bold px-4" data-bs-dismiss="modal" style="border-radius:10px;">Batal</button>
                    <button type="button" class="btn btn-success fw-bold px-4" id="btnConfirmAccAction" style="border-radius:10px;">Ya, Setujui</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL KONFIRMASI BUKA EDIT -->
<div class="modal fade" id="modalConfirmBukaEdit" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-body text-center p-4">
                <div class="mb-3 text-warning"><i class="fa-solid fa-unlock-keyhole fa-4x"></i></div>
                <h5 class="fw-bold text-dark mb-1">Buka Kunci Edit?</h5>
                <div class="badge bg-warning bg-opacity-10 text-dark border border-warning-subtle px-3 py-2 mb-3 w-100" style="white-space: normal;" id="editTargetName">NAMA SEKOLAH</div>
                <p class="text-secondary small mb-4">Aksi ini membuat user bisa mengisi/mengedit kembali inputan realisasi fisik mereka (Tanpa menghapus data). Lanjutkan?</p>
                <div class="d-flex justify-content-center gap-2">
                    <button type="button" class="btn btn-light fw-bold px-4" data-bs-dismiss="modal" style="border-radius:10px;">Batal</button>
                    <button type="button" class="btn btn-warning fw-bold px-4 text-dark" id="btnConfirmBukaEditAction" style="border-radius:10px;">Ya, Buka Kunci</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL INFO / ALERT SUKSES & ERROR -->
<div class="modal fade" id="modalInfoMsg" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-body text-center p-4">
                <div id="infoIcon" class="mb-3"></div>
                <h5 class="fw-bold text-dark mb-2" id="infoTitle">Informasi</h5>
                <p class="text-secondary small mb-1" id="infoMessage"></p>
                <p class="text-muted mb-4" style="font-size: 11px;">(Otomatis menutup dalam 3 detik...)</p>
                <button type="button" class="btn btn-primary fw-bold px-5" id="btnInfoClose" style="border-radius:10px;">Tutup Cepat</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ==== VARIABEL GLOBAL TARGET AKSI ====
let targetAccData = null;
let targetBukaEditData = null;
let infoModalTimeout = null;
let filterWidgetAktif = "";

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function toggleWidgetFilter(tipe) {
    const boxTuntas = document.getElementById('btn-filter-widget-tuntas');
    const boxBelum = document.getElementById('btn-filter-widget-belum');

    if (boxTuntas && boxBelum) {
        if (filterWidgetAktif === tipe) {
            filterWidgetAktif = "";
            boxTuntas.classList.remove('active-filter-tuntas');
            boxBelum.classList.remove('active-filter-belum');
        } else {
            filterWidgetAktif = tipe;
            boxTuntas.classList.toggle('active-filter-tuntas', tipe === "TUNTAS");
            boxBelum.classList.toggle('active-filter-belum', tipe === "BELUM");
        }
        jalankanFilter();
    }
}

function jalankanFilter() {
    const inputTeks = document.getElementById('input-cari-sekolah').value.toLowerCase();
    const rows = document.getElementsByClassName('item-baris-sekolah');
    let adaData = false, nomor = 1;

    for (let i = 0; i < rows.length; i++) {
        const nama = rows[i].querySelector('.cell-nama-sekolah').innerText.toLowerCase();
        const npsn = rows[i].querySelector('.cell-npsn').innerText.toLowerCase();
        const statWidget = rows[i].getAttribute('data-status-widget');

        const cocokTeks = nama.includes(inputTeks) || npsn.includes(inputTeks);
        const cocokWidget = !filterWidgetAktif || (statWidget === filterWidgetAktif);

        if (cocokTeks && cocokWidget) {
            rows[i].style.display = "";
            rows[i].querySelector('.indeks-nomor').innerText = nomor++;
            adaData = true;
        } else {
            rows[i].style.display = "none";
        }
    }
    
    const trTidakDitemukan = document.getElementById('tr-tidak-ditemukan');
    if(trTidakDitemukan) {
        trTidakDitemukan.style.display = (adaData || rows.length === 0) ? "none" : "";
    }
}

function panggilDetailAJAX(id, bulanStr, keyBaris) {
    document.querySelectorAll('#main-table-sekolah tbody tr.item-baris-sekolah').forEach(r => r.classList.remove('row-selected-highlight'));
    if(keyBaris) {
        document.getElementById('row-id-' + keyBaris)?.classList.add('row-selected-highlight');
    }

    const container = document.getElementById('detail-ajax-container');
    container.innerHTML = `
        <div class="card p-5 text-center bg-white shadow-sm" style="border-radius:20px; border:1px solid #e2e8f0;">
            <div class="spinner-border text-primary spinner-border-sm mb-2" role="status"></div>
            <div class="text-muted small fw-bold">MENYINKRONKAN KODERING BULANAN...</div>
        </div>`;

    fetch(`rekapan_admin.php?ajax_id_sekolah=${encodeURIComponent(id)}&ajax_bulan=${encodeURIComponent(bulanStr)}`)
        .then(res => res.text())
        .then(html => {
            container.innerHTML = html;
            container.scrollIntoView({ behavior: 'smooth', block: 'start' });
        })
        .catch(() => {
            container.innerHTML = `<div class='alert alert-danger m-3'>Gagal memuat data detail.</div>`;
        });
}

// ==== LOGIK PENGGANTI CONFIRM() MENJADI MODAL ====

// 1. Fungsi Tampil Modal Konfirmasi ACC
function prosesAccLangsung(idSekolah, bulanStr, namaSekolah = 'Sekolah Terpilih') {
    targetAccData = { idSekolah, bulanStr };
    document.getElementById('accTargetName').innerText = namaSekolah;
    let modal = new bootstrap.Modal(document.getElementById('modalConfirmAcc'));
    modal.show();
}

// 2. Fungsi Tampil Modal Konfirmasi Buka Edit
function prosesBukaEdit(idSekolah, bulanStr, namaSekolah = 'Sekolah Terpilih') {
    targetBukaEditData = { idSekolah, bulanStr };
    document.getElementById('editTargetName').innerText = namaSekolah;
    let modal = new bootstrap.Modal(document.getElementById('modalConfirmBukaEdit'));
    modal.show();
}

// 3. Fungsi Tampil Pesan Success / Error (Dengan Auto-Close 3 Detik)
function showInfoModal(isSuccess, title, msg, reloadOnClose) {
    let modalEl = document.getElementById('modalInfoMsg');
    let modalObj = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
    
    document.getElementById('infoTitle').innerText = title;
    document.getElementById('infoMessage').innerText = msg;
    
    let iconHtml = isSuccess 
        ? '<i class="fa-solid fa-circle-check fa-4x text-success"></i>'
        : '<i class="fa-solid fa-circle-xmark fa-4x text-danger"></i>';
    document.getElementById('infoIcon').innerHTML = iconHtml;

    if(infoModalTimeout) clearTimeout(infoModalTimeout);

    let closeAction = function() {
        modalObj.hide();
        if (reloadOnClose && isSuccess) {
            window.location.reload();
        }
    };

    let btn = document.getElementById('btnInfoClose');
    btn.onclick = function() {
        if(infoModalTimeout) clearTimeout(infoModalTimeout);
        closeAction();
    };

    modalObj.show();

    infoModalTimeout = setTimeout(() => {
        closeAction();
    }, 3000);
}

// ==== EVENT LISTENER TOMBOL DI DALAM MODAL KONFIRMASI ====

// Eksekusi ACC saat Klik "Ya, Setujui"
document.getElementById('btnConfirmAccAction').addEventListener('click', function() {
    let modalEl = document.getElementById('modalConfirmAcc');
    let modalObj = bootstrap.Modal.getInstance(modalEl);
    modalObj.hide();

    if(!targetAccData) return;

    const formData = new FormData();
    formData.append('action', 'acc_realisasi');
    formData.append('id_sekolah', targetAccData.idSekolah);
    formData.append('bulan', targetAccData.bulanStr);
    formData.append('csrf_token', getCsrfToken());

    fetch('rekapan_admin.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        showInfoModal(data.success, data.success ? "Berhasil!" : "Gagal", data.message, true);
    })
    .catch(() => {
        showInfoModal(false, "Kesalahan Sistem", "Terjadi kesalahan jaringan sistem.", false);
    });
});

// Eksekusi Buka Kunci saat Klik "Ya, Buka Kunci"
document.getElementById('btnConfirmBukaEditAction').addEventListener('click', function() {
    let modalEl = document.getElementById('modalConfirmBukaEdit');
    let modalObj = bootstrap.Modal.getInstance(modalEl);
    modalObj.hide();

    if(!targetBukaEditData) return;

    const formData = new FormData();
    formData.append('action', 'buka_edit_realisasi');
    formData.append('id_sekolah', targetBukaEditData.idSekolah);
    formData.append('bulan', targetBukaEditData.bulanStr);
    formData.append('csrf_token', getCsrfToken());

    fetch('rekapan_admin.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        showInfoModal(data.success, data.success ? "Berhasil!" : "Gagal", data.message, true);
    })
    .catch(() => {
        showInfoModal(false, "Kesalahan Sistem", "Terjadi kesalahan jaringan sistem.", false);
    });
});

function tutupDetail() {
    document.getElementById('detail-ajax-container').innerHTML = '';
    document.querySelectorAll('#main-table-sekolah tbody tr.item-baris-sekolah').forEach(r => r.classList.remove('row-selected-highlight'));
}

window.onload = jalankanFilter;
</script>
</body>
</html>