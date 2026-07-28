<?php
// KEAMANAN 1: Proteksi Session & Security Headers (Anti-Clickjacking, Sniffing, XSS)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Strict');
    if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === '1')) {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

if (!headers_sent()) {
    header("X-Frame-Options: SAMEORIGIN");
    header("X-Content-Type-Options: nosniff");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
}

// KEAMANAN 2: Validasi Sesi Ketat & Anti Session Hijacking
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';

if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'user') {
    session_unset();
    session_destroy();
    if (!headers_sent()) {
        header("Location: login.php");
    } else {
        echo "<script>window.location.replace('login.php');</script>";
    }
    exit;
}

if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== md5($user_agent)) {
    session_unset();
    session_destroy();
    if (!headers_sent()) {
        header("Location: login.php");
    } else {
        echo "<script>window.location.replace('login.php');</script>";
    }
    exit;
}

// Generate CSRF Token jika belum ada
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include "koneksi.php";

$id_sekolah = $_SESSION['id_sekolah'] ?? '';

// KEAMANAN 3: Casting ke (int) dan Validasi Rentang Bulan (1 - 12)
if (isset($_GET['bulan_realisasi'])) {
    $bulan_aktif = (int)$_GET['bulan_realisasi'];
    if ($bulan_aktif < 1 || $bulan_aktif > 12) {
        $bulan_aktif = (int)date('n');
    }
    $_SESSION['bulan_aktif_spj'] = $bulan_aktif;
} else {
    $bulan_aktif = isset($_SESSION['bulan_aktif_spj']) ? (int)$_SESSION['bulan_aktif_spj'] : (int)date('n');
    if ($bulan_aktif < 1 || $bulan_aktif > 12) {
        $bulan_aktif = (int)date('n');
    }
    $_SESSION['bulan_aktif_spj'] = $bulan_aktif;
}

$nama_bulan = [
    1 => "Januari", 2 => "Februari", 3 => "Maret", 4 => "April", 
    5 => "Mei", 6 => "Juni", 7 => "Juli", 8 => "Agustus", 
    9 => "September", 10 => "Oktober", 11 => "November", 12 => "Desember"
];
$bulan_teks = $nama_bulan[$bulan_aktif] ?? date('F');

// =========================================================================================
// 🔒 CEK STATUS LAPORAN REALISASI DARI DATABASE (ACUAN TUNGGAL GEMBOK)
// =========================================================================================
$status_laporan = 'Belum Dikirim';
$query_status = "SELECT `status` FROM `laporan_realisasi` WHERE `id_sekolah` = ? AND `bulan` = ? LIMIT 1";
$stmt_status = mysqli_prepare($conn, $query_status);
mysqli_stmt_bind_param($stmt_status, "si", $id_sekolah, $bulan_aktif);
mysqli_stmt_execute($stmt_status);
$res_status = mysqli_stmt_get_result($stmt_status);

if ($res_status && mysqli_num_rows($res_status) > 0) {
    $row_status = mysqli_fetch_assoc($res_status);
    $status_laporan = $row_status['status'];
}
mysqli_stmt_close($stmt_status);

// Flag Gembok: Bernilai true jika laporan sedang diproses/disetujui admin
$is_readonly = ($status_laporan === 'Menunggu Approval' || $status_laporan === 'Disetujui');

// =========================================================================================
// ✅ PROSES AKSI HAPUS SPJ PER ITEM BARANG (WITH CSRF & IDOR PROTECTION)
// =========================================================================================
if (isset($_GET['aksi']) && $_GET['aksi'] === 'hapus' && isset($_GET['id_spj'])) {
    
    // Validasi Token CSRF
    if (!isset($_GET['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
        echo "<script>alert('Akses Ditolak: Token Keamanan (CSRF) Tidak Valid!'); window.location.href='index.php?p=data_barang.php&bulan_realisasi=$bulan_aktif';</script>";
        exit;
    }

    // Proteksi Backend: Jika laporan dikirim, batalkan eksekusi hapus
    if ($is_readonly) {
        echo "<script>alert('Akses Ditolak! Laporan bulan ini sudah dikirim/disetujui, data terkunci.'); window.location.href='index.php?p=data_barang.php&bulan_realisasi=$bulan_aktif';</script>";
        exit;
    }

    $id_hapus = (int)$_GET['id_spj'];
    
    // Mulai transaksi terisolasi
    mysqli_begin_transaction($conn);
    try {
        // Matikan pengecekan Foreign Key khusus untuk sesi koneksi ini
        mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0");

        // Hapus data turunan di realisasi_barang_sekolah (Gunakan IDOR Check via Subquery)
        $stmt_del_rel = mysqli_prepare($conn, "DELETE FROM `realisasi_barang_sekolah` WHERE `id_master_barang` IN (SELECT `id` FROM `master_barang_sekolah` WHERE `id` = ? AND `id_sekolah` = ?)");
        mysqli_stmt_bind_param($stmt_del_rel, "is", $id_hapus, $id_sekolah);
        mysqli_stmt_execute($stmt_del_rel);
        mysqli_stmt_close($stmt_del_rel);

        // Hapus data utama di master_barang_sekolah
        $stmt_del_mas = mysqli_prepare($conn, "DELETE FROM `master_barang_sekolah` WHERE `id` = ? AND `id_sekolah` = ?");
        mysqli_stmt_bind_param($stmt_del_mas, "is", $id_hapus, $id_sekolah);
        mysqli_stmt_execute($stmt_del_mas);
        mysqli_stmt_close($stmt_del_mas);

        // Hidupkan kembali pengecekan Foreign Key
        mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");

        // Commit transaksi
        mysqli_commit($conn);

        echo "<script>alert('Item Barang Berhasil Dihapus dari SPK!'); window.location.href='index.php?p=data_barang.php&bulan_realisasi=$bulan_aktif';</script>";
        exit;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo "<script>alert('Gagal menghapus data. Terjadi kesalahan pada sistem.'); window.location.href='index.php?p=data_barang.php&bulan_realisasi=$bulan_aktif';</script>";
        exit;
    }
}

// =========================================================================================
// ✅ PROSES AKSI HAPUS MASSAL SATU DOKUMEN SPK LENGKAP (WITH CSRF & IDOR PROTECTION)
// =========================================================================================
if (isset($_GET['aksi']) && $_GET['aksi'] === 'hapus_spk' && isset($_GET['no_spk'])) {
    
    // Validasi Token CSRF
    if (!isset($_GET['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
        echo "<script>alert('Akses Ditolak: Token Keamanan (CSRF) Tidak Valid!'); window.location.href='index.php?p=data_barang.php&bulan_realisasi=$bulan_aktif';</script>";
        exit;
    }

    // Proteksi Backend: Jika laporan dikirim, batalkan eksekusi hapus
    if ($is_readonly) {
        echo "<script>alert('Akses Ditolak! Laporan bulan ini sudah dikirim/disetujui, data terkunci.'); window.location.href='index.php?p=data_barang.php&bulan_realisasi=$bulan_aktif';</script>";
        exit;
    }

    $spk_hapus = $_GET['no_spk'];
    
    // Mulai transaksi terisolasi
    mysqli_begin_transaction($conn);
    try {
        // Matikan pengecekan Foreign Key khusus untuk sesi koneksi ini
        mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0");

        // Hapus seluruh data anak/turunan terkait SPK ini
        $stmt_del_spk_rel = mysqli_prepare($conn, "DELETE FROM `realisasi_barang_sekolah` WHERE `id_master_barang` IN (SELECT `id` FROM `master_barang_sekolah` WHERE `no_spk` = ? AND `id_sekolah` = ? AND `bulan_realisasi` = ?)");
        mysqli_stmt_bind_param($stmt_del_spk_rel, "ssi", $spk_hapus, $id_sekolah, $bulan_aktif);
        mysqli_stmt_execute($stmt_del_spk_rel);
        mysqli_stmt_close($stmt_del_spk_rel);

        // Hapus data dokumen di master_barang_sekolah
        $stmt_del_spk_mas = mysqli_prepare($conn, "DELETE FROM `master_barang_sekolah` WHERE `no_spk` = ? AND `id_sekolah` = ? AND `bulan_realisasi` = ?");
        mysqli_stmt_bind_param($stmt_del_spk_mas, "ssi", $spk_hapus, $id_sekolah, $bulan_aktif);
        mysqli_stmt_execute($stmt_del_spk_mas);
        mysqli_stmt_close($stmt_del_spk_mas);

        // Hidupkan kembali pengecekan Foreign Key
        mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");

        // Commit transaksi
        mysqli_commit($conn);

        echo "<script>alert('Seluruh Data Dokumen SPK Berhasil Dihapus!'); window.location.href='index.php?p=data_barang.php&bulan_realisasi=$bulan_aktif';</script>";
        exit;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo "<script>alert('Gagal menghapus dokumen SPK. Terjadi kesalahan pada sistem.'); window.location.href='index.php?p=data_barang.php&bulan_realisasi=$bulan_aktif';</script>";
        exit;
    }
}

// KEAMANAN 5: Prepared Statement untuk Fetching Data Master Barang
$list_barang_sekolah = [];
$stmt_get_master = mysqli_prepare($conn, "SELECT * FROM `master_barang_sekolah` WHERE `id_sekolah` = ? AND `bulan_realisasi` = ? ORDER BY `id` DESC");
mysqli_stmt_bind_param($stmt_get_master, "si", $id_sekolah, $bulan_aktif);
mysqli_stmt_execute($stmt_get_master);
$query_master = mysqli_stmt_get_result($stmt_get_master);

while ($row = mysqli_fetch_assoc($query_master)) {
    $list_barang_sekolah[] = $row;
}
mysqli_stmt_close($stmt_get_master);

$grouped_spj = [];
$grand_total_seluruh_spj = 0; 

foreach ($list_barang_sekolah as $brg) {
    $spk_key = $brg['no_spk'] ?: 'TANPA_SPK';
    if (!isset($grouped_spj[$spk_key])) {
        $grouped_spj[$spk_key] = [
            'no_spk' => $brg['no_spk'],
            'sumber_perolehan' => $brg['sumber_perolehan'],
            'total_nilai_spk' => 0,
            'items' => []
        ];
    }
    $grouped_spj[$spk_key]['items'][] = $brg;
    $grouped_spj[$spk_key]['total_nilai_spk'] += (float)$brg['nilai_perolehan'];
    $grand_total_seluruh_spj += (float)$brg['nilai_perolehan']; 
}
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght=400;500;600;700;800&display=swap');

.card-spj, .card-card-box, .card-spj * {
    font-family:'Plus Jakarta Sans',sans-serif !important;
}
.card-spj {
    border:1px solid #e2e8f0 !important;
    box-shadow:0 10px 30px -5px rgba(0,0,0,.05)!important;
    border-radius:24px !important;
    background:#fff;
    margin-top:110px !important;
}
.header-floating {
    position:fixed; top:100px; right:25px; left:300px; z-index:9999;
    background:rgba(255,255,255,.95); backdrop-filter:blur(10px);
    padding:15px 20px; border-radius:18px;
    box-shadow:0 8px 30px rgba(0,0,0,.08); border:1px solid #e2e8f0;
}
.spk-row-group-master { border-bottom: 1px solid #e2e8f0 !important; }

/* Class Pembatas Tebal Antar Dokumen SPK */
.spk-group-separator {
    border-bottom: 3px solid #94a3b8 !important;
}

.spk-group-header { border-left: 5px solid #1e3a8a !important; }
.live-search-box-main { background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 12px; padding: 12px; }
.modal-kategori-overlay {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px);
    z-index: 10000; display: flex; align-items: center; justify-content: center;
}
.modal-kategori-card {
    background: #ffffff; border-radius: 20px; width: 100%; max-width: 450px; padding: 28px;
    box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); border: 1px solid #e2e8f0;
}
.btn-select-kategori {
    display: flex; align-items: center; gap: 15px; width: 100%; padding: 16px;
    border: 2px solid #e2e8f0; border-radius: 14px; background: #fff; text-align: left;
    transition: all 0.2s ease; margin-bottom: 12px;
}
.btn-select-kategori:hover { border-color: #1e3a8a; background: #f0f9ff; }
.footer-grand-total { background: #f1f5f9 !important; font-size: 14px; font-weight: 800; color: #1e3a8a !important; border-top: 3px solid #cbd5e1 !important; }

/* Class untuk memperkecil tombol aksi di SPK */
.btn-xs {
    padding: 2px 6px !important;
    font-size: 11px !important;
    line-height: 1.2 !important;
}

/* ── PERBAIKAN TIMING & SCROLL FREEZE HEADER ── */
.table-wrapper-scroll {
    max-height: 70vh; /* Menggunakan 70% dari tinggi layar user agar auto menyesuaikan monitor */
    overflow-y: auto;
    overflow-x: auto;
    position: relative;
}
#tabel_master_spj_grouped thead th {
    position: sticky;
    top: 0;
    z-index: 99; /* Menaikkan z-index agar tidak tertutup row body */
    box-shadow: inset 0 -2px 0 #cbd5e1;
}
</style>

<div id="modal_pilihan_kategori" class="modal-kategori-overlay" style="display: none;">
    <div class="modal-kategori-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0"><i class="bi bi-tag-fill text-primary me-2"></i>Pilih Kategori Belanja</h5>
            <button type="button" class="btn-close" onclick="tutupModalKategori()"></button>
        </div>
        <p class="text-secondary small mb-4">Silakan pilih jenis kategori belanja terlebih dahulu untuk menyesuaikan aturan dokumen.</p>
        
        <button type="button" class="btn-select-kategori" onclick="pilihKategoriDanBukaForm('Peralatan & Mesin')">
            <div class="bg-primary text-white rounded-3 p-2 d-flex align-items-center justify-content-center" style="width:40px; height:40px; background:#1e3a8a !important;">
                <i class="bi bi-tools fs-5"></i>
            </div>
            <div>
                <div class="fw-bold text-dark" style="font-size:14.5px;">Peralatan & Mesin</div>
                <div class="text-muted small" style="font-size:11.5px;">Komputer, Mebel, Alat Lab, dll.</div>
            </div>
        </button>

        <button type="button" class="btn-select-kategori" onclick="pilihKategoriDanBukaForm('Buku')">
            <div class="bg-success text-white rounded-3 p-2 d-flex align-items-center justify-content-center" style="width:40px; height:40px; background:#0d9488 !important;">
                <i class="bi bi-book-half fs-5"></i>
            </div>
            <div>
                <div class="fw-bold text-dark" style="font-size:14.5px;">Buku Perpustakaan / Umum</div>
                <div class="text-muted small" style="font-size:11.5px;">Wajib Isi No. Sertifikat/Pabrik saat input.</div>
            </div>
        </button>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card card-box bg-white card-spj p-4">
            
            <div class="border-bottom pb-3 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 header-floating">
                <div>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <span class="badge bg-primary px-3 py-2 rounded-3 fw-bold font-monospace" style="background:#1e3a8a !important; font-size:13px;"><i class="bi bi-box-seam"></i> Manajemen Data Barang</span>
                        <span class="badge bg-light text-dark border font-monospace px-3 py-2 rounded-3 fw-bold" style="font-size:13px;">Total SPJ Bulan <?= htmlspecialchars($bulan_teks, ENT_QUOTES, 'UTF-8'); ?>: <?= count($grouped_spj); ?> Berkas</span>
                        <span class="badge bg-light text-dark border font-monospace px-3 py-2 rounded-3 fw-bold" style="font-size:13px;">Total Barang: <?= count($list_barang_sekolah); ?> Item</span>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <?php if ($is_readonly): ?>
                        <button type="button" class="btn btn-secondary px-3 py-2 opacity-75" style="border-radius:12px; font-size: 13.5px; font-weight:700; cursor: not-allowed;" disabled title="Laporan sudah dikirim, data barang terkunci">
                            <i class="bi bi-lock-fill me-1"></i> Input SPJ Baru
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn btn-primary px-3 py-2 shadow-sm" style="border-radius:12px; font-size: 13.5px; font-weight:700; background:#1e3a8a; border:none;" onclick="bukaModalKategori()">
                            <i class="bi bi-plus-circle-fill me-1"></i> Input SPJ Baru
                        </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-white border px-3 py-2" style="border-radius: 12px; font-size: 13.5px; font-weight: 700; color:#64748b;" onclick="window.location.href='index.php?p=pilih_bulan_data_barang.php'">
                        <i class="bi bi-arrow-left me-1"></i> Ganti Bulan
                    </button>
                </div>
            </div>

            <div id="interface_daftar_barang">
                <div class="d-flex justify-content-between align-items-center mb-3 mt-2">
                    <h5 class="mb-0 text-dark" style="font-weight:700;"><i class="bi bi-list-task text-primary me-2"></i>Katalog Belanja Sekolah (Grup per Dokumen SPJ)</h5>
                </div>

                <div class="live-search-box-main mb-4 d-flex align-items-center gap-2">
                    <i class="bi bi-search text-secondary fs-5 ms-1"></i>
                    <input type="text" id="main_live_filter_input" class="form-control bg-white border-0 py-2" style="border-radius:8px; font-size:13px;" placeholder="Cari Instan berdasarkan No SPK, Nama Barang, atau Merk/Tipe..." oninput="jalankanLiveSearchTabelMaster()">
                </div>

                <?php if(empty($grouped_spj)): ?>
                    <div class="text-center p-5 border border-dashed rounded-3 bg-light">
                        <i class="bi bi-box text-muted" style="font-size: 3rem;"></i>
                        <p class="text-secondary mt-3 mb-0 fw-semibold">Belum ada daftar realisasi barang belanja untuk bulan <?= htmlspecialchars($bulan_teks, ENT_QUOTES, 'UTF-8'); ?>.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper-scroll" style="border-radius:12px; border: 1px solid #cbd5e1;">
                        <table class="table table-bordered align-middle mb-0" id="tabel_master_spj_grouped">
                            <thead>
                                <tr class="small text-uppercase fw-bold text-center" style="font-size:12px; color:#ffffff !important; background:#1e3a8a !important;">
                                    <th width="23%" style="color:#ffffff !important; background:#1e3a8a !important; padding:12px; vertical-align:middle; text-align:center;">Informasi Dokumen Berkas (SPJ)</th>
                                    <th width="24%" style="color:#ffffff !important; background:#1e3a8a !important; padding:12px; vertical-align:middle; text-align:center;">Nama Barang / Uraian</th>
                                    <th width="8%" style="color:#ffffff !important; background:#1e3a8a !important; padding:12px; vertical-align:middle; text-align:center;">Vol</th>
                                    <th width="14%" style="color:#ffffff !important; background:#1e3a8a !important; padding:12px; vertical-align:middle; text-align:center;">Harga Satuan</th>
                                    <th width="14%" style="color:#ffffff !important; background:#1e3a8a !important; padding:12px; vertical-align:middle; text-align:center;">Sub Perolehan</th>
                                    <th width="5%" style="color:#ffffff !important; background:#1e3a8a !important; padding:12px; vertical-align:middle; text-align:center;">Aksi</th>
                                    <th width="12%" style="color:#ffffff !important; background:#1e3a8a !important; padding:12px; vertical-align:middle; text-align:center;">Total Belanja</th>
                                </tr>
                            </thead>
                            
                            <tbody>
                                <?php foreach($grouped_spj as $spk_id => $grup): 
                                    $all_nama_barang_in_group = '';
                                    foreach($grup['items'] as $it) { $all_nama_barang_in_group .= strtolower($it['nama_barang']).' '; }
                                    $jumlah_item = count($grup['items']);
                                ?>
                                    <?php foreach($grup['items'] as $index_item => $item_brg): 
                                        $is_last_item = ($index_item === $jumlah_item - 1);
                                        $class_pembatas = $is_last_item ? 'spk-row-group-master spk-group-separator' : 'spk-row-group-master';
                                    ?>
                                        <tr class="<?= $class_pembatas; ?>" data-spk-id="<?= htmlspecialchars($spk_id, ENT_QUOTES, 'UTF-8'); ?>" data-index="<?= $index_item; ?>" data-search-spk="<?= htmlspecialchars(strtolower($grup['no_spk']), ENT_QUOTES, 'UTF-8'); ?>" data-search-barang="<?= htmlspecialchars(trim(strtolower($item_brg['nama_barang'])), ENT_QUOTES, 'UTF-8'); ?>" data-search-merktipe="<?= htmlspecialchars(trim(strtolower($item_brg['merk_tipe'])), ENT_QUOTES, 'UTF-8'); ?>">
                                            
                                            <?php if($index_item === 0): ?>
                                                <td rowspan="<?= $jumlah_item; ?>" valign="middle" class="spk-group-header p-3 text-center master-dokumen-td" data-origin-rowspan="<?= $jumlah_item; ?>" style="background:#f8fafc;">
                                                    <div class="mb-2">
                                                        <span class="badge bg-primary text-uppercase" style="font-size:10px;"><?= htmlspecialchars($grup['sumber_perolehan'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                    </div>
                                                    
                                                    <div class="text-dark font-monospace mb-2" style="font-size:13px; line-height: 1.3; font-weight: bold;">
                                                        <i class="bi bi-file-earmark-text me-1"></i> SPK:<br>
                                                        <span class="text-secondary" style="font-size:11.5px;"><?= htmlspecialchars($grup['no_spk'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                    </div>

                                                    <div class="d-flex justify-content-center gap-1 mt-2">
                                                        <?php if ($is_readonly): ?>
                                                            <button type="button" class="btn btn-xs btn-secondary text-white-50 opacity-75 d-inline-flex align-items-center gap-1" disabled title="Laporan sudah dikirim, dokumen SPK terkunci" style="border-radius:6px; padding: 4px 8px !important; cursor: not-allowed;">
                                                                <i class="bi bi-lock-fill" style="font-size: 11px;"></i> Terkunci
                                                            </button>
                                                        <?php else: ?>
                                                            <a href="index.php?p=data_barang_input.php&no_spk_edit=<?= urlencode($grup['no_spk']); ?>&bulan_realisasi=<?= $bulan_aktif; ?>" class="btn btn-xs btn-primary d-inline-flex align-items-center gap-1" title="Edit Dokumen SPK" style="border-radius:6px; padding: 4px 8px !important;">
                                                                <i class="bi bi-pencil-square" style="font-size: 11px;"></i> Edit
                                                            </a>
                                                            <a href="index.php?p=data_barang.php&bulan_realisasi=<?= $bulan_aktif; ?>&aksi=hapus_spk&no_spk=<?= urlencode($grup['no_spk']); ?>&csrf_token=<?= $_SESSION['csrf_token']; ?>" class="btn btn-xs btn-danger d-inline-flex align-items-center gap-1" title="Hapus Dokumen SPK" onclick="return confirm('Peringatan Keras! Apakah Anda yakin ingin menghapus SELURUH ITEM BARANG di dalam Dokumen SPK [<?= htmlspecialchars($grup['no_spk'], ENT_QUOTES, 'UTF-8'); ?>] ini?')" style="border-radius:6px; padding: 4px 8px !important;">
                                                                <i class="bi bi-trash3-fill" style="font-size: 11px;"></i> Hapus
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            <?php endif; ?>
                                            
                                            <td class="px-3 py-2">
                                                <div style="font-size:13px; color: #000000 !important; font-weight: bold;">
                                                    <?= htmlspecialchars($item_brg['nama_barang'], ENT_QUOTES, 'UTF-8'); ?>
                                                </div>
                                                <div class="font-monospace text-uppercase" style="font-size:11px; color: #64748b !important; font-weight: normal;">
                                                    <?= htmlspecialchars($item_brg['jenis_aset'], ENT_QUOTES, 'UTF-8'); ?> 
                                                    <?= !empty($item_brg['merk_tipe']) ? '('.htmlspecialchars($item_brg['merk_tipe'], ENT_QUOTES, 'UTF-8').')' : ''; ?>
                                                </div>
                                            </td>

                                            <td class="text-center" style="font-size:12.5px;"><?= (float)$item_brg['volume']; ?> <small class="text-muted"><?= htmlspecialchars($item_brg['satuan'], ENT_QUOTES, 'UTF-8'); ?></small></td>
                                            <td class="text-end font-monospace" style="font-size:12.5px;">Rp <?= number_format($item_brg['harga_satuan'], 0, ',', '.'); ?></td>
                                            <td class="text-end font-monospace fw-bold" style="font-size:12.5px; color: #000000 !important;">Rp <?= number_format($item_brg['nilai_perolehan'], 0, ',', '.'); ?></td>
                                            
                                            <td class="text-center">
                                                <?php if ($is_readonly): ?>
                                                    <button type="button" class="btn btn-sm btn-link text-secondary p-1 border-0 opacity-50" disabled title="Terkunci - Laporan sudah dikirim">
                                                        <i class="bi bi-lock-fill"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <a href="index.php?p=data_barang.php&bulan_realisasi=<?= $bulan_aktif; ?>&aksi=hapus&id_spj=<?= (int)$item_brg['id']; ?>&csrf_token=<?= $_SESSION['csrf_token']; ?>" class="btn btn-sm btn-outline-danger p-1 border-0" onclick="return confirm('Apakah anda yakin ingin menghapus barang [<?= htmlspecialchars($item_brg['nama_barang'], ENT_QUOTES, 'UTF-8'); ?>] ini dari SPK?')">
                                                        <i class="bi bi-trash3-fill"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </td>

                                            <?php if($index_item === 0): ?>
                                                <td rowspan="<?= $jumlah_item; ?>" class="text-end p-3 font-monospace fw-bold text-dark master-total-td" data-origin-rowspan="<?= $jumlah_item; ?>" valign="middle" style="font-size:13.5px; background: #f8fafc; border-left: 1px solid #cbd5e1;">
                                                    Rp <?= number_format($grup['total_nilai_spk'], 0, ',', '.'); ?>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>

                            <tfoot>
                                <tr class="footer-grand-total">
                                    <td colspan="6" class="text-end py-3 px-4 text-uppercase">Total Keseluruhan SPJ Bulan <?= htmlspecialchars($bulan_teks, ENT_QUOTES, 'UTF-8'); ?> :</td>
                                    <td class="text-end py-3 px-3 font-monospace text-primary" style="font-size: 15px; background: #e0f2fe;">
                                        Rp <?= number_format($grand_total_seluruh_spj, 0, ',', '.'); ?>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    function bukaModalKategori() { document.getElementById('modal_pilihan_kategori').style.display = 'flex'; }
    function tutupModalKategori() { document.getElementById('modal_pilihan_kategori').style.display = 'none'; }

    function pilihKategoriDanBukaForm(kategori) {
        tutupModalKategori();
        window.location.href = `index.php?p=data_barang_input.php&kategori=${encodeURIComponent(kategori)}`;
    }

    function jalankanLiveSearchTabelMaster() {
        let inputKeyword = document.getElementById('main_live_filter_input').value.toLowerCase().trim();
        let barisGrupSpk = document.querySelectorAll('#tabel_master_spj_grouped tbody .spk-row-group-master');
        
        let allMasterCells = document.querySelectorAll('.master-dokumen-td');
        let allTotalCells = document.querySelectorAll('.master-total-td');
        
        if (inputKeyword === "") {
            barisGrupSpk.forEach(row => {
                row.style.display = '';
                let index = parseInt(row.getAttribute('data-index'));
                if (index === 0) {
                    let cellDokumen = row.querySelector('.master-dokumen-td');
                    let cellTotal = row.querySelector('.master-total-td');
                    if (cellDokumen) {
                        cellDokumen.style.display = '';
                        cellDokumen.setAttribute('rowspan', cellDokumen.getAttribute('data-origin-rowspan'));
                    }
                    if (cellTotal) {
                        cellTotal.style.display = '';
                        cellTotal.setAttribute('rowspan', cellTotal.getAttribute('data-origin-rowspan'));
                    }
                } else {
                    let movedDokumen = row.querySelector('.master-dokumen-td');
                    let movedTotal = row.querySelector('.master-total-td');
                    if (movedDokumen) movedDokumen.remove();
                    if (movedTotal) movedTotal.remove();
                }
            });
            return;
        }

        let groups = {};
        barisGrupSpk.forEach(row => {
            let spkId = row.getAttribute('data-spk-id');
            if (!groups[spkId]) groups[spkId] = [];
            groups[spkId].push(row);
            
            row.style.display = 'none';
            
            let movedDokumen = row.querySelector('.master-dokumen-td');
            let movedTotal = row.querySelector('.master-total-td');
            if (movedDokumen && parseInt(row.getAttribute('data-index')) !== 0) movedDokumen.remove();
            if (movedTotal && parseInt(row.getAttribute('data-index')) !== 0) movedTotal.remove();
        });

        for (let spkId in groups) {
            let rowsInGroup = groups[spkId];
            let matchingRows = [];
            let cellDokumenAsli = null;
            let cellTotalAsli = null;

            rowsInGroup.forEach(row => {
                let index = parseInt(row.getAttribute('data-index'));
                if (index === 0) {
                    cellDokumenAsli = row.querySelector('.master-dokumen-td');
                    cellTotalAsli = row.querySelector('.master-total-td');
                }

                let infoSpk = row.getAttribute('data-search-spk') || '';
                let infoBarang = row.getAttribute('data-search-barang') || '';
                let infoMerkTipe = row.getAttribute('data-search-merktipe') || '';

                if (infoSpk.includes(inputKeyword) || infoBarang.includes(inputKeyword) || infoMerkTipe.includes(inputKeyword)) {
                    matchingRows.push(row);
                }
            });

            if (matchingRows.length > 0) {
                matchingRows.forEach(row => row.style.display = '');

                let firstVisibleRow = matchingRows[0];
                let firstVisibleIndex = parseInt(firstVisibleRow.getAttribute('data-index'));

                if (firstVisibleIndex === 0) {
                    if (cellDokumenAsli) {
                        cellDokumenAsli.style.display = '';
                        cellDokumenAsli.setAttribute('rowspan', matchingRows.length);
                    }
                    if (cellTotalAsli) {
                        cellTotalAsli.style.display = '';
                        cellTotalAsli.setAttribute('rowspan', matchingRows.length);
                    }
                } else {
                    if (cellDokumenAsli) {
                        cellDokumenAsli.style.display = 'none';
                        let clonedDokumen = cellDokumenAsli.cloneNode(true);
                        clonedDokumen.style.display = '';
                        clonedDokumen.setAttribute('rowspan', matchingRows.length);
                        firstVisibleRow.insertBefore(clonedDokumen, firstVisibleRow.firstChild);
                    }
                    if (cellTotalAsli) {
                        cellTotalAsli.style.display = 'none';
                        let clonedTotal = cellTotalAsli.cloneNode(true);
                        clonedTotal.style.display = '';
                        clonedTotal.setAttribute('rowspan', matchingRows.length);
                        firstVisibleRow.appendChild(clonedTotal);
                    }
                }
            } else {
                rowsInGroup.forEach(row => row.style.display = 'none');
            }
        }
    }
</script>