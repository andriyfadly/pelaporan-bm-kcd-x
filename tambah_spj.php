<?php
// Set pengerasan cookie sesi sebelum session_start
if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_samesite', 'Lax');
    }
    session_start();
}

// 1. Validasi Proteksi Sesi Login
if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'user' || empty($_SESSION['id_sekolah'])) {
    echo "<script>window.location.href='login.php';</script>";
    exit;
}

// Generasi Token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include "koneksi.php";

$id_sekolah = $_SESSION['id_sekolah'];

// 2. Tangkap Parameter URL secara ketat & Sanitize
$id_uraian       = isset($_GET['id_uraian']) ? trim(strip_tags($_GET['id_uraian'])) : ''; 
$kodering        = isset($_GET['kodering']) ? trim(strip_tags($_GET['kodering'])) : ''; 
$sisa_awal       = isset($_GET['sisa']) ? filter_var($_GET['sisa'], FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) ?? 0.0 : 0.0;
$pagu_acuan_awal = isset($_GET['acuan']) ? filter_var($_GET['acuan'], FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) ?? 0.0 : 0.0; 
$bulan_realisasi = isset($_GET['bulan_realisasi']) ? filter_var($_GET['bulan_realisasi'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) ?? 0 : 0;

if (empty($kodering) || empty($id_sekolah) || $bulan_realisasi < 1 || $bulan_realisasi > 12) {
    echo "<div class='alert alert-danger m-3'>Parameter tidak lengkap atau tidak valid. Kembali ke menu utama.</div>";
    exit;
}

// Helper XSS Escaping
if (!function_exists('e')) {
    function e($str) {
        return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
    }
}

// DAFTAR NAMA BULAN
$nama_bulan_arr = [
    1 => "Januari", 2 => "Februari", 3 => "Maret", 4 => "April",
    5 => "Mei", 6 => "Juni", 7 => "Juli", 8 => "Agustus",
    9 => "September", 10 => "Oktober", 11 => "November", 12 => "Desember"
];
$display_bulan = $nama_bulan_arr[$bulan_realisasi] ?? "Tidak Diketahui";

// =========================================================================
// AMBIL DATA BARANG DARI DB (KEAMANAN SEKUAT BAJA DENGAN PREPARED STATEMENT)
// =========================================================================
$spk_groups = [];

$query_barang = "SELECT id, id_sekolah, id_uraian, no_sp2d, sumber_perolehan, 
                        bulan_realisasi, no_spk, ba_no, ba_tgl, kode_barang, 
                        nama_barang, jenis_aset, merk_tipe, satuan, volume, 
                        harga_satuan, nilai_perolehan, is_realisasi 
                 FROM master_barang_sekolah 
                 WHERE id_sekolah = ? 
                   AND bulan_realisasi = ?
                 ORDER BY no_spk ASC, nama_barang ASC";

$stmt_barang = mysqli_prepare($conn, $query_barang);
mysqli_stmt_bind_param($stmt_barang, "si", $id_sekolah, $bulan_realisasi);
mysqli_stmt_execute($stmt_barang);
$q_barang = mysqli_stmt_get_result($stmt_barang);

if (!$q_barang) {
    error_log("Query Error: " . mysqli_error($conn));
    die("Terjadi kesalahan sistem saat mengambil data barang.");
}

while ($row = mysqli_fetch_assoc($q_barang)) {
    $clean_spk = !empty($row['no_spk']) ? $row['no_spk'] : "TANPA_NOMOR_SPK";
    $group_key = $clean_spk;

    if (!isset($spk_groups[$group_key])) {
        $spk_groups[$group_key] = [
            'no_spk' => $clean_spk,
            'no_sp2d' => empty($row['no_sp2d']) ? '-' : $row['no_sp2d'],
            'sumber_perolehan' => empty($row['sumber_perolehan']) ? 'BOS Reguler' : $row['sumber_perolehan'],
            'ba_no' => $row['ba_no'] ?: '-',
            'ba_tgl' => $row['ba_tgl'] ?: '-',
            'total_belanja_spk' => 0,
            'items' => []
        ];
    }
    
    $sub_total = ($row['nilai_perolehan'] > 0) ? $row['nilai_perolehan'] : ($row['volume'] * $row['harga_satuan']);
    $spk_groups[$group_key]['total_belanja_spk'] += $sub_total;
    $spk_groups[$group_key]['items'][] = $row;
}
mysqli_stmt_close($stmt_barang);

// 🛠️ HITUNG REALISASI AKTUAL BERDASARKAN KODERING & BULAN YANG SEDANG DIAKSES
$q_realisasi_aktual = "SELECT SUM(nilai_perolehan) AS total_terinput 
                       FROM `realisasi_barang_sekolah` 
                       WHERE `kodering_belanja` = ? 
                       AND `id_sekolah` = ?
                       AND `bulan_realisasi` = ?";
$stmt_real_aktual = mysqli_prepare($conn, $q_realisasi_aktual);
mysqli_stmt_bind_param($stmt_real_aktual, "ssi", $kodering, $id_sekolah, $bulan_realisasi);
mysqli_stmt_execute($stmt_real_aktual);
$res_realisasi_aktual = mysqli_stmt_get_result($stmt_real_aktual);
$data_realisasi_aktual = mysqli_fetch_assoc($res_realisasi_aktual);
$total_sudah_realisasi_db = (float)($data_realisasi_aktual['total_terinput'] ?? 0);
mysqli_stmt_close($stmt_real_aktual);

// Sisa anggaran disesuaikan secara real-time berdasarkan data murni database
$sisa_awal = $pagu_acuan_awal - $total_sudah_realisasi_db;

// =========================================================================
// FITUR TAMBAHAN: AMBIL NAMA SEKOLAH & DAFTAR URAIAN UNTUK KLIK KODERING
// =========================================================================
$satuan_pendidikan_tampil = "Sekolah Tidak Diketahui";

$query_master_sekolah = "SELECT * FROM `kode_sekolah` WHERE `id_sekolah` = ? OR `id` = ? LIMIT 1";
$stmt_master = mysqli_prepare($conn, $query_master_sekolah);
mysqli_stmt_bind_param($stmt_master, "ss", $id_sekolah, $id_sekolah);
mysqli_stmt_execute($stmt_master);
$result_master = mysqli_stmt_get_result($stmt_master);

if ($result_master && mysqli_num_rows($result_master) > 0) {
    $row_master = mysqli_fetch_assoc($result_master);
    $satuan_pendidikan_tampil = $row_master['nama_sekolah'] ?? ($row_master['satuan_pendidikan'] ?? 'Sekolah Tidak Diketahui');
} else {
    $query_sekolah_alt = "SELECT `satuan_pendidikan` FROM `data_barang_acuan` WHERE `id_sekolah` = ? LIMIT 1";
    $stmt_alt = mysqli_prepare($conn, $query_sekolah_alt);
    mysqli_stmt_bind_param($stmt_alt, "s", $id_sekolah);
    mysqli_stmt_execute($stmt_alt);
    $result_sekolah_alt = mysqli_stmt_get_result($stmt_alt);
    if ($result_sekolah_alt && mysqli_num_rows($result_sekolah_alt) > 0) {
        $row_sekolah_alt = mysqli_fetch_assoc($result_sekolah_alt);
        $satuan_pendidikan_tampil = $row_sekolah_alt['satuan_pendidikan'];
    }
    mysqli_stmt_close($stmt_alt);
}
mysqli_stmt_close($stmt_master);

$list_uraian = [];
$query_uraian = "SELECT uraian FROM `data_barang_acuan` 
                 WHERE (
                     `id_sekolah` = ? 
                     OR TRIM(`satuan_pendidikan`) = TRIM(?)
                     OR `satuan_pendidikan` LIKE ?
                 )
                 AND `bulan` = ? 
                 AND `kodering` = ?";
$stmt_uraian = mysqli_prepare($conn, $query_uraian);
$like_satuan = "%" . trim($satuan_pendidikan_tampil) . "%";
mysqli_stmt_bind_param($stmt_uraian, "sssis", $id_sekolah, $satuan_pendidikan_tampil, $like_satuan, $bulan_realisasi, $kodering);
mysqli_stmt_execute($stmt_uraian);
$result_uraian = mysqli_stmt_get_result($stmt_uraian);

if ($result_uraian) {
    while ($ru = mysqli_fetch_assoc($result_uraian)) {
        $list_uraian[] = $ru['uraian'];
    }
}
mysqli_stmt_close($stmt_uraian);
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
    
    .card-spj, .card-spj *, #form_realisasi, #form_realisasi * { 
        font-family: 'Plus Jakarta Sans', sans-serif !important; 
    }
    .card-spj { 
        border: 1px solid #e0f2fe !important; 
        box-shadow: 0 10px 30px -5px rgba(14, 165, 233, 0.05)!important; 
        border-radius: 24px !important; 
        background: #ffffff; 
    }
    .badge-kodering { 
        background: #f0f9ff; 
        color: #0369a1; 
        font-weight: 700; 
        padding: 4px 10px; 
        border-radius: 8px; 
        border: 1px solid #e0f2fe; 
        font-size: 11.5px; 
    }
    .badge-bulan {
        background: #fef3c7;
        color: #d97706;
        font-weight: 700;
        padding: 4px 10px;
        border-radius: 8px;
        border: 1px solid #fde68a;
        font-size: 11.5px;
    }
    .badge-acuan {
        background: #f1f5f9;
        color: #475569;
        font-weight: 700;
        padding: 4px 10px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        font-size: 11.5px;
    }
    .badge-realisasi-top {
        background: #eff6ff;
        color: #1d4ed8;
        font-weight: 700;
        padding: 4px 10px;
        border-radius: 8px;
        border: 1px solid #dbeafe;
        font-size: 11.5px;
    }
    .badge-sisa { 
        background: #f0fdf4; 
        color: #166534; 
        font-weight: 700; 
        padding: 4px 10px; 
        border-radius: 8px; 
        border: 1px solid #dcfce7; 
        font-size: 11.5px; 
    }
    .badge-sisa.minus { 
        background: #fef2f2 !important; 
        color: #991b1b !important; 
        border-color: #fecaca !important; 
    }
    .grand-total-box { 
        background: #f8fafc; 
        border: 2px dashed #bae6fd; 
        border-radius: 20px; 
        padding: 24px 28px; 
    }
    .header-floating{
        position: fixed; top: 100px; right: 25px; left: 300px; z-index: 9999;
        background: rgba(255,255,255,0.95); backdrop-filter: blur(10px);
        padding: 12px 20px; border-radius: 18px; box-shadow: 0 8px 30px rgba(0,0,0,.12); border: 1px solid #e2e8f0;
    }
    .card-spj{ margin-top: 110px !important; }
    .checkbox-lg { width: 20px; height: 20px; cursor: pointer; accent-color: #0ea5e9; }
    .tr-selected { background-color: #f0f9ff !important; }
    .tr-already-realized { background-color: #f8fafc !important; color: #94a3b8 !important; }
    
    /* ─── KUNCI FIX: WRAPPER UTAMA AGAR SEJAJAR DAN MENYATU RAPI ─── */
    .spj-table-wrapper {
        border: 1px solid #cbd5e1;
        border-radius: 12px;
        overflow: hidden; /* Mengunci sudut lengkung luar agar tidak bocor */
        background: #ffffff;
    }

    /* Kotak pencarian di luar area scroll, diam statis di atas tabel */
    .search-top-box {
        background-color: #ffffff;
        padding: 16px;
        border-bottom: 1px solid #cbd5e1;
    }

    /* Hanya tabel yang bisa di-scroll ke bawah */
    .table-responsive {
        max-height: 55vh; 
        overflow-y: auto;
        overflow-x: auto;
        position: relative;
    }

    .table-custom-ui {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }
    
    /* Header Biru Mutlak Diam di paling atas (`top: 0`) area scroll tabel */
    .table-custom-ui thead th {
        position: sticky;
        top: 0; 
        z-index: 30;
        background-color: #1e3a8a !important;
        color: #ffffff !important;
        text-align: center;
        padding: 14px 10px;
        font-size: 13px;
        font-weight: 700;
        text-transform: uppercase;
        border-bottom: 2px solid #172554;
        background-clip: padding-box;
    }
    
    .table-custom-ui tbody td {
        padding: 12px;
        border-bottom: 1px solid #cbd5e1;
        border-right: 1px solid #cbd5e1;
        vertical-align: middle;
        font-size: 13px;
    }
    
    .table-custom-ui tbody tr:last-child td {
        border-bottom: none;
    }

    .table-custom-ui tbody td:last-child,
    .table-custom-ui thead th:last-child {
        border-right: none;
    }
    
    .badge-sumber {
        color: white;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        display: inline-block;
        margin-bottom: 5px;
    }
    .badge-sumber.merah { background-color: #ef4444; } 
    .badge-sumber.hijau { background-color: #10b981; } 

    .text-spk-num { font-size: 12px; color: #334155; word-break: break-all; }
    .text-total-belanja { font-weight: 800; color: #1e293b; font-size: 14px; }
    .row-grup-start { border-top: 3px solid #64748b !important; background-color: #f8fafc !important; }
    .row-grup-end { border-bottom: 3px solid #64748b !important; }
    .search-wrapper { position: relative; max-width: 400px; }
    .search-input-custom { padding: 10px 16px 10px 40px; border-radius: 12px; border: 1px solid #cbd5e1; font-size: 13.5px; transition: all 0.2s ease; }
    .search-input-custom:focus { border-color: #0ea5e9; box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15); outline: none; }
    .search-icon-inside { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 16px; }
</style>

<div class="row">
    <div class="col-12">
        <div class="card card-box bg-white card-spj p-4">
            
            <div class="border-bottom pb-3 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 header-floating">
                <div>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <span class="badge-bulan font-monospace"><i class="bi bi-calendar-event"></i> Bulan: <?= e($display_bulan); ?></span>
                        
                        <!-- FITUR TAMBAHAN: URAIAN COLLAPSE PADA KODERING -->
                        <div style="position: relative;">
                            <span class="badge-kodering font-monospace" style="cursor: pointer; display: inline-block;" data-bs-toggle="collapse" data-bs-target="#collapseUraian">
                                <i class="bi bi-plus-square text-primary me-1"></i> Kode Rek: <?= e($kodering); ?>
                            </span>
                            <div class="collapse position-absolute mt-2" id="collapseUraian" style="z-index: 9999; min-width: 250px; left: 0;">
                                <div class="p-2 bg-white border-start border-primary border-3 rounded shadow" style="font-size: 11px; font-weight: 500; max-height: 150px; overflow-y: auto;">
                                    <span class="text-muted d-block mb-1 fw-bold">Daftar Uraian Pekerjaan:</span>
                                    <?php if (empty($list_uraian)): ?>
                                        <div class="text-secondary fst-italic">Uraian tidak ditemukan.</div>
                                    <?php else: ?>
                                        <?php foreach ($list_uraian as $idx => $uraian_text): ?>
                                            <div class="text-secondary text-truncate mb-1" title="<?= e($uraian_text); ?>">
                                                <?= ($idx + 1) . ". " . e($uraian_text); ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <span class="badge-acuan font-monospace" id="acuan_display">Acuan: Rp <?= number_format($pagu_acuan_awal, 0, ',', '.'); ?></span>
                        <span class="badge-realisasi-top font-monospace" id="realisasi_top_display">Realisasi: Rp <?= number_format($total_sudah_realisasi_db, 0, ',', '.'); ?></span>
                        <span class="badge-sisa font-monospace" id="sisa_anggaran_display">Sisa Anggaran: Rp <?= number_format($sisa_awal, 0, ',', '.'); ?></span>
                    </div>
                </div>
                <div>
                    <button type="button" class="btn btn-white border px-3 py-2" style="border-radius: 12px; font-size: 13.5px; font-weight: 700;" onclick="kembaliKeMenu()">
                        <i class="bi bi-arrow-left me-1"></i> Kembali
                    </button>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div class="search-wrapper w-100">
                    <i class="bi bi-search search-icon-inside"></i>
                    <input type="text" id="live_search_input" class="form-control search-input-custom" placeholder="Cari nomor SPK atau nama barang..." onkeyup="filterTabelLive()">
                </div>
            </div>

            <form id="form_realisasi" onsubmit="return false;">
                <input type="hidden" id="payload_id_uraian" value="<?= e($id_uraian); ?>">
                <input type="hidden" id="payload_kodering" value="<?= e($kodering); ?>">
                <input type="hidden" id="payload_bulan" value="<?= (int)$bulan_realisasi; ?>">
                <input type="hidden" id="payload_csrf_token" value="<?= e($_SESSION['csrf_token']); ?>">

                <div class="table-responsive mb-4">
                    <table class="table-custom-ui" id="tabel_master_spj">
                        <thead>
                            <tr>
                                <th width="25%">Informasi Dokumen Berkas (SPK)</th>
                                <th width="30%">Nama Barang / Uraian</th>
                                <th width="10%">Vol</th>
                                <th width="15%">Harga Satuan</th>
                                <th width="15%">Total Perolehan</th>
                                <th width="12%">Total Belanja</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($spk_groups)): ?>
                                <tr class="row-no-data">
                                    <td colspan="6" class="text-center p-5 text-secondary">
                                        <i class="bi bi-exclamation-triangle text-warning fs-2 d-block mb-2"></i>
                                        Tidak ada data barang di Bulan <?= e($display_bulan); ?> untuk Sekolah ini.
                                    </td>
                                </tr>
                            <?php 
                            else: 
                                $spk_index = 0;
                                foreach ($spk_groups as $key_spk => $group): 
                                    $spk_index++;
                                    $total_items = count($group['items']);

                                    $all_realisasi = true;
                                    foreach ($group['items'] as $cek) {
                                        if ((int)$cek['is_realisasi'] !== 1) {
                                            $all_realisasi = false;
                                            break;
                                        }
                                    }

                                    foreach ($group['items'] as $index => $item): 
                                        $is_done = ((int)$item['is_realisasi'] === 1); 
                                        
                                        $row_class = "spk-wrapper-row";
                                        if ($is_done) { $row_class .= " tr-already-realized"; }
                                        if ($index === 0) { $row_class .= " row-grup-start"; }
                                        if ($index === ($total_items - 1)) { $row_class .= " row-grup-end"; }
                                        
                                        $item_total = ($item['nilai_perolehan'] > 0) ? (float)$item['nilai_perolehan'] : ((float)$item['volume'] * (float)$item['harga_satuan']);
                                    ?>
                                        <tr id="tr_item_<?= (int)$item['id'] ?>" class="<?= e($row_class); ?>" 
                                            data-spk="<?= e($group['no_spk']) ?>"
                                            data-sp2d="<?= e($group['no_sp2d']) ?>"
                                            data-sumber="<?= e($group['sumber_perolehan']) ?>"
                                            data-ba-no="<?= e($group['ba_no']) ?>"
                                            data-ba-tgl="<?= e($group['ba_tgl']) ?>">
                                            
                                            <?php if ($index === 0): ?>
                                                <td rowspan="<?= $total_items ?>" valign="top" class="bg-light bg-opacity-50 cell-spk-group">
                                                    <div class="d-flex align-items-start gap-2 mb-2">
                                                       <input type="checkbox"
                                                              class="checkbox-lg chk-master-spk"
                                                              data-group="<?= $spk_index ?>"
                                                              <?= $all_realisasi ? 'checked disabled' : '' ?>
                                                              onchange="toggleSelectAllGroup(this, <?= $spk_index ?>)">
                                                        <span class="badge-sumber badge-group-<?= $spk_index ?> <?= $all_realisasi ? 'hijau' : 'merah' ?>"><?= e($group['sumber_perolehan']) ?></span>
                                                    </div>
                                                    <div class="text-xs text-muted fw-bold mb-1"><i class="bi bi-file-earmark-text"></i> SPK:</div>
                                                    <div class="text-spk-num font-monospace fw-bold target-search-spk" style="font-size:11px;"><?= e($group['no_spk']) ?></div>
                                                </td>
                                            <?php endif; ?>

                                            <td>
                                                <div class="d-flex align-items-start gap-2">
                                                    <input type="checkbox" 
                                                           class="checkbox-lg <?= $is_done ? 'chk-done' : 'chk-realisasi' ?> sub-chk-group-<?= $spk_index ?>" 
                                                           name="item_pilihan[]" 
                                                           value="<?= (int)$item['id'] ?>"
                                                           data-total="<?= $item_total ?>"
                                                           <?= $is_done ? 'checked disabled' : '' ?>
                                                           onchange="toggleRowSelection(this, <?= (int)$item['id'] ?>, <?= $spk_index ?>)">
                                                    <div>
                                                        <div class="fw-bold <?= $is_done ? 'text-muted text-decoration-line-through' : 'text-dark' ?> target-search-nama">
                                                            <?= e($item['nama_barang']) ?>
                                                            <?= $is_done ? ' <span class="badge bg-secondary text-white ms-1" style="font-size:10px;">Sudah Realisasi</span>' : '' ?>
                                                        </div>
                                                        <div class="text-muted small mt-1 target-search-merk" style="font-size:11px; text-transform: uppercase;">
                                                            <?= e($item['jenis_aset']) ?> <?= $item['merk_tipe'] ? '('.e($item['merk_tipe']).')' : '' ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            
                                            <td class="text-center font-monospace text-uppercase fw-semibold">
                                                <?= number_format($item['volume'], 0, ',', '.') ?> <?= e($item['satuan']) ?>
                                            </td>
                                            
                                            <td class="text-end font-monospace">
                                                Rp <?= number_format($item['harga_satuan'], 0, ',', '.') ?>
                                            </td>
                                            
                                            <td class="text-end font-monospace fw-bold">
                                                Rp <?= number_format($item_total, 0, ',', '.') ?>
                                            </td>

                                            <?php if ($index === 0): ?>
                                                <td rowspan="<?= $total_items ?>" class="text-end bg-light bg-opacity-25 cell-total-group" valign="middle">
                                                    <div class="text-muted small mb-1" style="font-size: 10px;">Rp</div>
                                                    <div class="text-total-belanja font-monospace">
                                                        <?= number_format($group['total_belanja_spk'], 0, ',', '.') ?>
                                                    </div>
                                                </td>
                                            <?php endif; ?>

                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            
                            <tr id="row_no_match" style="display: none;">
                                <td colspan="6" class="text-center p-5 text-secondary">
                                    <i class="bi bi-search text-muted fs-2 d-block mb-2"></i>
                                    Tidak ada data yang cocok dengan kata kunci pencarian.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="grand-total-box mb-4 shadow-sm">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span style="font-size: 13px; font-weight: 800; color: #0369a1; letter-spacing: 0.5px;">TOTAL PILIHAN BARU:</span>
                        <h2 class="font-monospace mb-0" id="grand_total_display" style="font-weight: 900; color: #0284c7; font-size: 2rem;">Rp 0</h2>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                    <button type="button" class="btn btn-white px-4 py-2 border text-secondary" style="border-radius: 12px; font-size: 13.5px; font-weight: 700; background:#fff;" onclick="kembaliKeMenu()">Batal</button>
                    <button type="button" id="btn_simpan_realisasi" class="btn btn-primary px-4 py-2 shadow-sm" style="border-radius: 12px; font-size: 13.5px; background: #0ea5e9; font-weight: 700; border:none;" <?= empty($spk_groups) ? 'disabled' : '' ?> onclick="eksekusiSimpanSPJ()">Simpan Realisasi SPJ</button>
                </div>
            </form>

        </div>
    </div>
</div>

<div class="modal fade" id="modalSuksesSPJ" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-body text-center p-4">
                <div class="d-inline-flex align-items-center justify-content-center bg-success bg-opacity-10 text-success rounded-circle mb-3" style="width: 72px; height: 72px;">
                    <i class="bi bi-check-circle-fill" style="font-size: 36px; color: #10b981 !important;"></i>
                </div>
                <h5 class="text-dark mb-2" style="font-weight: 800;">Penyimpanan Berhasil!</h5>
                <p class="text-secondary small px-2 mb-4">Item barang pilihan Anda beserta kelengkapan dokumen SPK & BA telah sukses dimasukkan ke rincian Berkas SPJ.</p>
                <button type="button" class="btn btn-success w-100 py-2" id="btnModalMengerti" style="border-radius: 12px; font-weight: 700; background: #10b981; border: none;">Selesai</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalOverBudget" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-body text-center p-4">
                <div class="d-inline-flex align-items-center justify-content-center bg-danger bg-opacity-10 text-danger rounded-circle mb-3" style="width: 72px; height: 72px;">
                    <i class="bi bi-exclamation-triangle-fill" style="font-size: 36px; color: #ef4444 !important;"></i>
                </div>
                <h5 class="text-dark mb-2" style="font-weight: 800;">Anggaran Melebihi Batas!</h5>
                <p class="text-secondary small px-2 mb-4">Gagal menyimpan. Total realisasi yang Anda centang melebihi sisa acuan anggaran saat ini (Sisa Anggaran Minus).</p>
                <button type="button" class="btn btn-danger w-100 py-2" data-bs-dismiss="modal" style="border-radius: 12px; font-weight: 700; background: #ef4444; border: none;">Perbaiki Pilihan</button>
            </div>
        </div>
    </div>
</div>

<script>
    var totalPaguAcuan       = parseFloat(<?= (float)$pagu_acuan_awal; ?>) || 0; 
    var sudahRealisasiDiAwal = parseFloat(<?= (float)$total_sudah_realisasi_db; ?>) || 0; 

    document.addEventListener("DOMContentLoaded", function() {
        let masterCheckboxes = document.querySelectorAll('.chk-master-spk');
        masterCheckboxes.forEach(master => {
            let groupIndex = master.getAttribute('data-group');
            updateMasterCheckboxState(groupIndex);
        });
        hitungGrandTotal();
    });

    function filterTabelLive() {
        let input = document.getElementById('live_search_input');
        let filter = input.value.toLowerCase().trim();
        let rows = document.querySelectorAll('#tabel_master_spj tbody .spk-wrapper-row');
        let matchCount = 0;
        
        rows.forEach(row => {
            let textSpk = row.getAttribute('data-spk') ? row.getAttribute('data-spk').toLowerCase() : '';
            let textNama = row.querySelector('.target-search-nama') ? row.querySelector('.target-search-nama').innerText.toLowerCase() : '';
            let textMerk = row.querySelector('.target-search-merk') ? row.querySelector('.target-search-merk').innerText.toLowerCase() : '';
            
            if (textSpk.includes(filter) || textNama.includes(filter) || textMerk.includes(filter)) {
                row.style.display = ""; 
                matchCount++;
            } else {
                row.style.display = "none";
            }
        });

        let noMatchRow = document.getElementById('row_no_match');
        if (noMatchRow) {
            noMatchRow.style.display = (matchCount === 0 && rows.length > 0) ? "" : "none";
        }
    }

    function toggleRowSelection(checkbox, itemId, groupIndex) {
        let row = document.getElementById(`tr_item_${itemId}`);
        if(checkbox.checked) {
            row.classList.add('tr-selected');
        } else {
            row.classList.remove('tr-selected');
        }
        updateMasterCheckboxState(groupIndex);
        hitungGrandTotal();
    }

    function toggleSelectAllGroup(masterCheckbox, groupIndex) {
        let subCheckboxes = document.querySelectorAll(`.sub-chk-group-${groupIndex}.chk-realisasi`);
        subCheckboxes.forEach(chk => {
            chk.checked = masterCheckbox.checked;
            let row = document.getElementById(`tr_item_${chk.value}`);
            if(masterCheckbox.checked) {
                row.classList.add('tr-selected');
            } else {
                row.classList.remove('tr-selected');
            }
        });
        updateMasterCheckboxState(groupIndex);
        hitungGrandTotal();
    }

    function updateMasterCheckboxState(groupIndex) {
        let masterCheckbox = document.querySelector(`.chk-master-spk[data-group="${groupIndex}"]`);
        if (!masterCheckbox) return;

        let allSub = document.querySelectorAll(`.sub-chk-group-${groupIndex}`);
        let allSelectable = document.querySelectorAll(`.sub-chk-group-${groupIndex}.chk-realisasi`);
        let allChecked = document.querySelectorAll(`.sub-chk-group-${groupIndex}:checked`);
        let badgeSumber = document.querySelector(`.badge-group-${groupIndex}`);
        
        if (allSelectable.length === 0) {
            masterCheckbox.checked = true;
            masterCheckbox.disabled = true; 
            if(badgeSumber){ badgeSumber.classList.remove('merah'); badgeSumber.classList.add('hijau'); }
            return;
        }

        if (allChecked.length === allSub.length) {
            masterCheckbox.checked = true;
            masterCheckbox.indeterminate = false;
            if(badgeSumber){ badgeSumber.classList.remove('merah'); badgeSumber.classList.add('hijau'); }
        } else if (allChecked.length === (allSub.length - allSelectable.length)) {
            masterCheckbox.checked = false;
            masterCheckbox.indeterminate = false;
            if(badgeSumber){ badgeSumber.classList.remove('hijau'); badgeSumber.classList.add('merah'); }
        } else {
            masterCheckbox.checked = false;
            masterCheckbox.indeterminate = true; 
            if(badgeSumber){ badgeSumber.classList.remove('hijau'); badgeSumber.classList.add('merah'); }
        }
    }

    function hitungGrandTotal() {
        let checkboxesBaru = document.querySelectorAll('.chk-realisasi:checked');
        let totalPilihanBaru = 0;

        checkboxesBaru.forEach(chk => {
            totalPilihanBaru += parseFloat(chk.getAttribute('data-total')) || 0;
        });

        let totalRealisasiBerjalan = sudahRealisasiDiAwal + totalPilihanBaru;
        let sisaAnggaranKini = totalPaguAcuan - totalRealisasiBerjalan;

        document.getElementById('grand_total_display').innerText = "Rp " + new Intl.NumberFormat('id-ID').format(totalPilihanBaru);
        document.getElementById('realisasi_top_display').innerText = "Realisasi: Rp " + new Intl.NumberFormat('id-ID').format(totalRealisasiBerjalan);
        
        let displaySisa = document.getElementById('sisa_anggaran_display');
        displaySisa.innerText = "Sisa Anggaran: Rp " + new Intl.NumberFormat('id-ID').format(sisaAnggaranKini);

        if (sisaAnggaranKini < 0) {
            displaySisa.className = "badge-sisa font-monospace minus";
        } else {
            displaySisa.className = "badge-sisa font-monospace";
        }
    }

    function eksekusiSimpanSPJ() {
        let checkboxesBaru = document.querySelectorAll('.chk-realisasi:checked');
        let totalPilihanBaru = 0;

        checkboxesBaru.forEach(chk => {
            totalPilihanBaru += parseFloat(chk.getAttribute('data-total')) || 0;
        });

        let totalRealisasiBerjalan = sudahRealisasiDiAwal + totalPilihanBaru;
        
        if ((totalPaguAcuan - totalRealisasiBerjalan) < 0) {
            let modalOver = new bootstrap.Modal(document.getElementById('modalOverBudget'));
            modalOver.show();
            return; 
        }

        if (checkboxesBaru.length === 0) {
            alert("Gagal: Anda belum memilih item barang baru untuk direalisasikan!");
            return;
        }

        let rows = document.querySelectorAll('.spk-wrapper-row');
        let paketDataSimpan = [];

        rows.forEach(row => {
            let chkBaru = row.querySelector('.chk-realisasi:checked');
            if (chkBaru) {
                paketDataSimpan.push({
                    id_barang: chkBaru.value,
                    no_spk: row.getAttribute('data-spk'),
                    no_sp2d: row.getAttribute('data-sp2d'),
                    sumber_perolehan: row.getAttribute('data-sumber'),
                    ba_no: row.getAttribute('data-ba-no'),
                    ba_tgl: row.getAttribute('data-ba-tgl'),
                    nominal_pilihan: parseFloat(chkBaru.getAttribute('data-total')) || 0
                });
            }
        });

        let formData = new FormData();
        formData.append('id_uraian', document.getElementById('payload_id_uraian').value);
        formData.append('kodering', document.getElementById('payload_kodering').value);
        formData.append('bulan_realisasi', document.getElementById('payload_bulan').value);
        formData.append('csrf_token', document.getElementById('payload_csrf_token').value);
        formData.append('paket_data_json', JSON.stringify(paketDataSimpan));

        let btn = document.getElementById('btn_simpan_realisasi');
        btn.disabled = true;
        btn.innerText = "Memproses...";

        fetch('simpan_realisasi.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json()) 
        .then(data => {
            let modal = new bootstrap.Modal(document.getElementById('modalSuksesSPJ'));
            modal.show();
            
            document.getElementById('btnModalMengerti').onclick = function() {
                modal.hide();
                kembaliKeMenu(); 
            };
        })
        .catch(err => {
            console.error(err);
            alert("Terjadi kesalahan sistem respon.");
            btn.disabled = false;
            btn.innerText = "Simpan Realisasi SPJ";
        });
    }

    function kembaliKeMenu() {
        window.location.href = 'index.php?p=input_realisasi.php&bulan_realisasi=<?= (int)$bulan_realisasi; ?>';
    }
</script>