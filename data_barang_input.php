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

// Generate Anti-CSRF Token jika belum tersedia
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include "koneksi.php";

$id_sekolah = $_SESSION['id_sekolah'] ?? '';

// Tangkap & Whitelist kategori dari URL
$raw_kategori = $_GET['kategori'] ?? 'Peralatan & Mesin';
$kategori_belanja = (stripos($raw_kategori, 'buku') !== false) ? 'Buku' : 'Peralatan & Mesin';

$no_spk_edit = isset($_GET['no_spk_edit']) ? trim($_GET['no_spk_edit']) : '';

// KEAMANAN 3: Validasi Parameter Bulan (Bounds Checking 1 - 12)
$bulan_aktif_session = isset($_SESSION['bulan_aktif_spj']) ? (int)$_SESSION['bulan_aktif_spj'] : (int)date('n');
if ($bulan_aktif_session < 1 || $bulan_aktif_session > 12) {
    $bulan_aktif_session = (int)date('n');
}

$bulan_pilihan = isset($_GET['bulan_realisasi']) ? (int)$_GET['bulan_realisasi'] : null;
if ($bulan_pilihan !== null && ($bulan_pilihan < 1 || $bulan_pilihan > 12)) {
    $bulan_pilihan = $bulan_aktif_session;
}

$bulan_terpilih = !empty($bulan_pilihan) ? $bulan_pilihan : $bulan_aktif_session;
$is_edit_mode = !empty($no_spk_edit);

// KEAMANAN 4: Cek Status Laporan Database (Gembok Backend Protection)
$status_laporan = 'Belum Dikirim';
$query_status = "SELECT `status` FROM `laporan_realisasi` WHERE `id_sekolah` = ? AND `bulan` = ? LIMIT 1";
$stmt_status = mysqli_prepare($conn, $query_status);
if ($stmt_status) {
    mysqli_stmt_bind_param($stmt_status, "si", $id_sekolah, $bulan_terpilih);
    mysqli_stmt_execute($stmt_status);
    $res_status = mysqli_stmt_get_result($stmt_status);
    if ($res_status && mysqli_num_rows($res_status) > 0) {
        $row_status = mysqli_fetch_assoc($res_status);
        $status_laporan = $row_status['status'];
    }
    mysqli_stmt_close($stmt_status);
}

if ($status_laporan === 'Menunggu Approval' || $status_laporan === 'Disetujui') {
    echo "<script>alert('Akses Ditolak! Laporan bulan ini sudah dikirim/disetujui, data terkunci.'); window.location.href='index.php?p=data_barang.php&bulan_realisasi=$bulan_terpilih';</script>";
    exit;
}

$data_edit_arr = [];
$data_induk = [];

if ($is_edit_mode) {
    $spk_bersih = urldecode($no_spk_edit);
    
    // KEAMANAN: Menggunakan Prepared Statements untuk mencegah SQL Injection
    $stmt_edit = mysqli_prepare($conn, "SELECT * FROM `master_barang_sekolah` WHERE `no_spk` = ? AND `id_sekolah` = ? AND `bulan_realisasi` = ? ORDER BY `id` ASC");
    if ($stmt_edit) {
        mysqli_stmt_bind_param($stmt_edit, "ssi", $spk_bersih, $id_sekolah, $bulan_terpilih);
        mysqli_stmt_execute($stmt_edit);
        $q_edit = mysqli_stmt_get_result($stmt_edit);
        
        while ($r = mysqli_fetch_assoc($q_edit)) {
            $data_edit_arr[] = $r;
        }
        mysqli_stmt_close($stmt_edit);
    }
    
    if (!empty($data_edit_arr)) {
        $data_induk = $data_edit_arr[0];
        $kategori_belanja = 'Peralatan & Mesin'; // Default ke mesin
        
        // 1. Cek langsung dari kolom database (kalau kamu punya kolom khusus kategori)
        if (isset($data_induk['kategori_belanja']) && stripos($data_induk['kategori_belanja'], 'buku') !== false) {
            $kategori_belanja = 'Buku';
        } elseif (isset($data_induk['kategori']) && stripos($data_induk['kategori'], 'buku') !== false) {
            $kategori_belanja = 'Buku';
        } else {
            // 2. Kalau kolom ga ada, pindai SELURUH barang di dalam SPK ini
            foreach ($data_edit_arr as $cek_item) {
                if (stripos($cek_item['jenis_aset'], 'buku') !== false || stripos($cek_item['nama_barang'], 'buku') !== false) {
                    $kategori_belanja = 'Buku';
                    break; // Langsung kunci jadi 'Buku' kalau nemu 1 aja barang buku
                }
            }
        }
    }
}
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght=400;500;600;700;800&display=swap');

.spj-container {
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    background: #fdfdfd;
}

/* Header UI Style */
.header-section-blue {
    background-color: #0b3c7c !important;
    color: white !important;
    font-weight: 700;
    font-size: 13px;
    letter-spacing: 0.5px;
    border-radius: 6px;
    padding: 12px 16px;
}

.header-section-green {
    background-color: #008080 !important;
    color: white !important;
    font-weight: 700;
    font-size: 13px;
    border-radius: 6px;
    padding: 12px 16px;
}

/* Form Controls Custom Match */
.spj-container .form-label {
    font-weight: 700;
    font-size: 11px;
    color: #334155;
    text-transform: uppercase;
    margin-bottom: 6px;
}

.spj-container .form-control, .spj-container .form-select {
    border: 1px solid #7dd3fc !important;
    border-radius: 8px !important;
    padding: 10px 14px;
    font-size: 13px;
    color: #334155;
    background-color: #fff;
    box-shadow: none !important;
}

/* Form Readonly Warna Abu-abu Jelas/Solid */
.spj-container .bg-disabled-readonly {
    background-color: #e2e8f0 !important; 
    color: #475569 !important; 
    border: 1px solid #cbd5e1 !important;
    cursor: not-allowed;
    font-weight: 600;
}

.spj-container .form-control::placeholder {
    color: #94a3b8;
    font-size: 13px;
}

/* Dynamic Accordion Row Item & SMOOTH TRANSITION STYLE */
.accordion-item-barang {
    border: 1px solid #7dd3fc !important;
    border-radius: 12px !important;
    background: #fff;
    margin-bottom: 20px;
    overflow: hidden;
}

.accordion-header-custom {
    background-color: #f0f9ff;
    padding: 14px 20px;
    border-bottom: 1px solid #7dd3fc;
    cursor: pointer;
}

/* Wrapper Khusus Transisi Halus Buka-Tutup */
.accordion-collapse-wrapper {
    max-height: 2000px; 
    opacity: 1;
    overflow: hidden;
    transition: max-height 0.4s ease-in-out, opacity 0.3s ease-in-out;
}

/* Default state ditutup untuk menghemat ruang scroll */
.accordion-collapse-wrapper.is-collapsed {
    max-height: 0 !important;
    opacity: 0 !important;
}

.accordion-body-custom {
    padding: 20px;
}

/* Suggestion Box Pencarian */
.wrapper-cari {
    position: relative;
}
.dropdown-saran-barang {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    z-index: 999;
    background: white;
    border: 1px solid #cbd5e1;
    border-top: none;
    max-height: 280px;
    overflow-y: auto;
    border-radius: 0 0 8px 8px;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
}
.dropdown-saran-barang .saran-item {
    padding: 12px 16px;
    font-size: 13px;
    cursor: pointer;
    border-bottom: 1px solid #f1f5f9;
}
.dropdown-saran-barang .saran-item:hover {
    background-color: #f0f9ff;
}
.dropdown-saran-barang .saran-nama-barang {
    font-size: 14.5px !important;
    font-weight: 600;
    color: #0f172a;
}

.dropdown-saran-barang .saran-jenis-aset-badge {
    background-color: #e2e8f0 !important;
    color: #475569 !important;
    font-weight: 700 !important;
    font-size: 11.5px;
    padding: 4px 10px;
    border-radius: 4px;
    display: inline-block;
}

/* Total Bar Area Tengah */
.total-akumulasi-box {
    background-color: #f1f5f9; 
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    padding: 20px;
}

/* Teks Label Bold Hitam */
.lbl-total-hitam {
    font-weight: 800 !important;
    color: #000000 !important;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Nilai Rp Hitam Proporsional */
.txt-grand-total-hitam {
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    font-weight: 800 !important;
    color: #000000 !important;
    font-size: 24px; 
    margin-top: 4px;
    margin-bottom: 0;
}

.alert-validasi-item {
    font-size: 12px;
    font-weight: 600;
    color: #b91c1c;
    background-color: #fef2f2;
    border: 1px dashed #fca5a5;
    border-radius: 6px;
    padding: 8px 12px;
    margin-top: 10px;
    text-transform: none !important;
}
</style>

<div class="container-fluid spj-container py-3">

    <?php 
    // ---- [TAMBAHAN BARU] LABEL STICKY KATEGORI ----
    $isBukuStyle = (strtolower($kategori_belanja) == 'buku');
    $bgColor     = $isBukuStyle ? '#dcfce7' : '#e0f2fe';
    $borderColor = $isBukuStyle ? '#22c55e' : '#0ea5e9';
    $textColor   = $isBukuStyle ? '#166534' : '#075985';
    ?>
    <div style="position: sticky; top: 10px; z-index: 1050; display: inline-block; background-color: <?= $bgColor ?>; border: 2px solid <?= $borderColor ?>; border-radius: 8px; padding: 10px 20px; margin-bottom: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <span style="font-weight: 800; color: <?= $textColor ?>;">
           KATEGORI SPJ: <?= strtoupper(htmlspecialchars($kategori_belanja, ENT_QUOTES, 'UTF-8')) ?>
        </span>
    </div>
    <!-- ---- END LABEL STICKY ---- -->

    <form id="form_realisasi_barang" method="POST" action="proses_simpan_barang.php" onsubmit="return bersihkanFormatSebelumSubmit()">
        <!-- KEAMANAN 5: Token CSRF Perlindungan Form -->
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
        <input type="hidden" name="is_edit" value="<?= $is_edit_mode ? '1' : '0'; ?>">
        <!-- INPUT HIDDEN KATEGORI YANG AKAN DIKIRIM KE PROSES_SIMPAN_BARANG.PHP -->
        <input type="hidden" name="kategori_belanja" id="kategori_belanja_input" value="<?= htmlspecialchars($kategori_belanja, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="bulan_realisasi" value="<?= $bulan_terpilih; ?>">

        <div class="header-section-blue mb-3 d-flex align-items-center">
            <i class="bi bi-file-earmark-text-fill me-2 fs-5"></i> I. DOKUMEN & ADMINISTRASI KEUANGAN SPJ
        </div>
        
        <div class="row g-3 mb-4 px-2">
            <div class="col-md-4">
                <label class="form-label">No. SP2D</label>
                <input type="text" name="no_sp2d" id="adm_no_sp2d" class="form-control" placeholder="Boleh dikosongkan (Opsional)" value="<?= htmlspecialchars($data_induk['no_sp2d'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" oninput="simpanKeLocalStorage()">
            </div>
            <div class="col-md-4">
                <label class="form-label">Sumber Perolehan *</label>
                <select name="sumber_perolehan" id="adm_sumber_perolehan" class="form-select" required onchange="simpanKeLocalStorage()">
                    <option value="BOS Reguler" <?= ($data_induk['sumber_perolehan'] ?? '') === 'BOS Reguler' ? 'selected' : ''; ?>>BOS Reguler</option>
                    <option value="BOS Kinerja" <?= ($data_induk['sumber_perolehan'] ?? '') === 'BOS Kinerja' ? 'selected' : ''; ?>>BOS Kinerja</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">No. SPK / Kwitansi *</label>
                <input type="text" name="no_spk" id="adm_no_spk" class="form-control" placeholder="Nomor Kwitansi/Nota Belanja" required value="<?= htmlspecialchars($data_induk['no_spk'] ?? $no_spk_edit, ENT_QUOTES, 'UTF-8'); ?>" oninput="simpanKeLocalStorage()">
            </div>
            <div class="col-md-8">
                <label class="form-label">Nomor Berita Acara Penerimaan (BA NO) *</label>
                <input type="text" name="ba_no" id="adm_ba_no" class="form-control" placeholder="Masukkan nomor berita acara..." value="<?= htmlspecialchars($data_induk['ba_no'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" oninput="simpanKeLocalStorage()">
            </div>
            <div class="col-md-4">
                <label class="form-label">Tanggal BA (BA TGL) *</label>
                <input type="date" name="ba_tgl" id="adm_ba_tgl" class="form-control" value="<?= htmlspecialchars($data_induk['ba_tgl'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" oninput="simpanKeLocalStorage()">
            </div>
        </div>

        <div class="row g-2 align-items-center mb-3 px-1">
            <div class="col">
                <div class="header-section-green m-0">
                    <i class="bi bi-box-seam-fill me-2"></i> II & III. DETAIL ITEM BARANG UNTUK SPJ INI
                </div>
            </div>
            <div class="col-auto">
                <button type="button" id="btn_tambah_atas" class="btn fw-bold text-white px-4 d-flex align-items-center shadow-sm" style="background-color: #0284c7; border: none; border-radius: 6px; padding: 12px; font-size: 13px;" onclick="pemicuValidasiBarisTerakhir()">
                    <i class="bi bi-plus-circle-fill me-1.5"></i> Tambah Item Barang
                </button>
            </div>
        </div>

        <div id="wrapper_container_items">
            <?php 
            $index = 0;
            if ($is_edit_mode && !empty($data_edit_arr)): 
                foreach($data_edit_arr as $item): 
                    $harga_raw = $item['harga_satuan'];
                    $subtotal_awal = (float)$item['volume'] * (float)$harga_raw;
                    $is_buku = ($kategori_belanja === 'Buku');
            ?>
                <div class="accordion-item-barang" id="item_card_<?= $index; ?>">
                    <div class="accordion-header-custom d-flex justify-content-between align-items-center" onclick="toggleAccordionItem(<?= $index; ?>)">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width:24px; height:24px; font-size:11px; flex-shrink:0;"><?= ($index + 1); ?></span>
                            <div class="d-flex flex-column">
                                <span class="fw-bold text-secondary text-uppercase small head-label-nama">ITEM: <?= htmlspecialchars($item['nama_barang'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="text-muted" style="font-size: 11px; font-weight: 600;">Merk/Tipe: <span id="display_merk_<?= $index; ?>" class="text-primary"><?= htmlspecialchars($item['merk_tipe'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></span></span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <span class="fw-bold text-dark fs-6 text-display-subtotal" id="label_subtotal_<?= $index; ?>">Rp <?= number_format($subtotal_awal, 0, ',', '.'); ?></span>
                            <button type="button" class="btn btn-sm btn-outline-danger p-1 border-0" onclick="hapasSpesifikCard(<?= $index; ?>, event)">
                                <i class="bi bi-trash3-fill fs-6"></i>
                            </button>
                        </div>
                    </div>
                    <div class="accordion-collapse-wrapper is-collapsed" id="item_body_<?= $index; ?>">
                        <div class="accordion-body-custom">
                            <input type="hidden" name="items[<?= $index; ?>][id]" value="<?= htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8'); ?>">
                            
                            <div class="row g-3">
                                <div class="col-12 wrapper-cari">
                                    <label class="form-label"><i class="bi bi-search"></i> Cari Nama Barang / Kode Aset Dari Katalog Pagu</label>
                                    <input type="text" class="form-control field-cari-katalog" placeholder="Ketik nama barang yang dicari..." oninput="cariKatalogPaguAjax(this, <?= $index; ?>)">
                                    <div class="dropdown-saran-barang d-none" id="saran_box_<?= $index; ?>"></div>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Kode Barang (Asset)</label>
                                    <input type="text" name="items[<?= $index; ?>][kode_barang]" id="kode_barang_<?= $index; ?>" class="form-control bg-disabled-readonly" readonly value="<?= htmlspecialchars($item['kode_barang'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">Nama Barang / Uraian</label>
                                    <input type="text" name="items[<?= $index; ?>][nama_barang]" id="nama_barang_<?= $index; ?>" class="form-control bg-disabled-readonly" readonly required value="<?= htmlspecialchars($item['nama_barang'], ENT_QUOTES, 'UTF-8'); ?>">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Jenis Aset</label>
                                    <input type="text" name="items[<?= $index; ?>][jenis_aset]" id="jenis_aset_<?= $index; ?>" class="form-control bg-disabled-readonly" readonly required value="<?= htmlspecialchars($item['jenis_aset'], ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Merk / Tipe *</label>
                                    <!-- [TAMBAHAN] list="history_merk" autocomplete="off" -->
                                    <input type="text" name="items[<?= $index; ?>][merk_tipe]" id="merk_<?= $index; ?>" class="form-control" list="history_merk" autocomplete="off" placeholder="Contoh: Lenovo Core i3" value="<?= htmlspecialchars($item['merk_tipe'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" oninput="document.getElementById('display_merk_<?= $index; ?>').innerText = this.value || '-'; simpanKeLocalStorage();">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">No. Sertifikat / Pabrik / Penerbit <span class="label-wajib-buku"><?= $is_buku ? '*' : ''; ?></span></label>
                                    <!-- [TAMBAHAN] list="history_sertifikat" autocomplete="off" -->
                                    <input type="text" name="items[<?= $index; ?>][no_sertifikat]" id="sertifikat_<?= $index; ?>" class="form-control" list="history_sertifikat" autocomplete="off" placeholder="<?= $is_buku ? 'Wajib Diisi (Kategori Buku)' : 'Wajib jika kategori Buku / Pabrik'; ?>" <?= $is_buku ? 'required' : ''; ?> value="<?= htmlspecialchars($item['no_sertifikat'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" oninput="simpanKeLocalStorage()">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Ukuran / Dimensi Bangunan</label>
                                    <input type="text" name="items[<?= $index; ?>][ukuran_bangunan]" class="form-control" value="<?= htmlspecialchars($item['ukuran_bangunan'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>" oninput="simpanKeLocalStorage()">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Satuan *</label>
                                    <!-- [TAMBAHAN] list="history_satuan" autocomplete="off" -->
                                    <input type="text" name="items[<?= $index; ?>][satuan]" id="satuan_<?= $index; ?>" class="form-control" list="history_satuan" autocomplete="off" placeholder="Pcs / Unit / Rim" required value="<?= htmlspecialchars($item['satuan'], ENT_QUOTES, 'UTF-8'); ?>" oninput="simpanKeLocalStorage()">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Volume (QTY) *</label>
                                    <input type="number" step="any" min="0" name="items[<?= $index; ?>][volume]" id="vol_<?= $index; ?>" class="form-control text-center" placeholder="Qty" required value="<?= (float)$item['volume']; ?>" oninput="if(this.value < 0) this.value = ''; kalkulasiOtomatisRow(<?= $index; ?>); simpanKeLocalStorage();">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Harga Satuan (RP) *</label>
                                    <input type="text" name="items[<?= $index; ?>][harga_satuan]" id="harga_<?= $index; ?>" class="form-control text-end input-rupiah-mask" placeholder="Rp 0" required value="<?= number_format((float)$harga_raw, 0, ',', '.'); ?>" oninput="formatInputKeRupiahMask(this); kalkulasiOtomatisRow(<?= $index; ?>); simpanKeLocalStorage();">
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                                <div class="id-msg-validasi text-danger" id="error_msg_<?= $index; ?>"></div>
                                <div class="text-end">
                                    <span class="text-secondary fw-bold small text-uppercase me-2">Subtotal Item:</span>
                                    <span class="fw-extrabold text-dark fs-5" id="subtotal_bawah_<?= $index; ?>" style="font-weight:800;">Rp <?= number_format($subtotal_awal, 0, ',', '.'); ?></span>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-end mt-2">
                                <button type="button" class="btn fw-bold px-3 py-2 d-flex align-items-center shadow-sm text-white btn-tambah-dinamis" style="background-color: #0284c7; border: none; border-radius: 6px; font-size: 13px;" onclick="validasiDanTambahBaru(<?= $index; ?>)">
                                    <i class="bi bi-plus-circle-fill me-1.5"></i> Tambah Item Barang
                                </button>
                            </div>

                        </div>
                    </div>
                </div>
            <?php 
                $index++;
                endforeach; 
            endif; 
            ?>
        </div>

        <div class="total-akumulasi-box text-center mb-4">
            <div class="lbl-total-hitam">Total Akumulasi Realisasi</div>
            <?php
            $grand_total_awal = 0;
            if ($is_edit_mode && !empty($data_edit_arr)) {
                foreach ($data_edit_arr as $sub_item) {
                    $grand_total_awal += (float)$sub_item['volume'] * (float)$sub_item['harga_satuan'];
                }
            }
            ?>
            <h3 class="txt-grand-total-hitam" id="grand_total_display">Rp <?= number_format($grand_total_awal, 0, ',', '.'); ?></h3>
        </div>

        <div class="d-flex justify-content-end gap-2 border-top pt-3">
            <a href="index.php?p=data_barang.php&bulan_realisasi=<?= $bulan_terpilih; ?>" class="btn btn-light px-4 fw-bold" style="border-radius:8px; border:1px solid #cbd5e1; font-size:13px; color:#64748b;">
                <i class="bi bi-arrow-left-short fs-6"></i> Kembali 
            </a>
            <button type="submit" class="btn btn-primary px-4 fw-bold" style="background-color:#0284c7; border:none; border-radius:8px; font-size:13px;">Simpan Realisasi</button>
        </div>

        <!-- [TAMBAHAN BARU] DATALIST UNTUK GLOBAL HISTORY -->
        <datalist id="history_merk"></datalist>
        <datalist id="history_sertifikat"></datalist>
        <datalist id="history_satuan"></datalist>
        <!-- END DATALIST -->
    </form>
</div>

<script>
let globalBarisIndex = <?= $is_edit_mode ? $index : 0; ?>;
const kategoriBelanjaUtama = document.getElementById('kategori_belanja_input').value;

const spkSuffix = "<?= $is_edit_mode ? '_edit_' . preg_replace('/[^a-zA-Z0-9]/', '', $no_spk_edit) : ''; ?>";
const localStorageKey = "draft_spj_barang_" + "<?= htmlspecialchars($id_sekolah, ENT_QUOTES, 'UTF-8'); ?>" + "_" + "<?= $bulan_terpilih; ?>" + spkSuffix;

function formatInputKeRupiahMask(el) {
    let value = el.value.replace(/[^0-9]/g, '');
    if (value === "") { value = ""; }
    else { value = new Intl.NumberFormat('id-ID').format(parseInt(value)); }
    el.value = value;
}

function formatMataUangRupiah(angka) {
    return 'Rp ' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(angka);
}

function hapusFormatRupiah(str) {
    if(!str) return 0;
    let val = str.replace(/[^0-9]/g, '');
    return val ? parseInt(val) : 0;
}

function toggleAccordionItem(idx) {
    let bodyWrapper = document.getElementById(`item_body_${idx}`);
    if(bodyWrapper) bodyWrapper.classList.toggle('is-collapsed');
}

function minimizeSemauItemCards() {
    let allWrappers = document.querySelectorAll('.accordion-collapse-wrapper');
    allWrappers.forEach(wrapper => wrapper.classList.add('is-collapsed'));
    
    let allAddButtons = document.querySelectorAll('.btn-tambah-dinamis');
    allAddButtons.forEach(btn => btn.classList.add('d-none'));
}

function pemicuValidasiBarisTerakhir() {
    let container = document.getElementById('wrapper_container_items');
    let cards = container.querySelectorAll('.accordion-item-barang');
    if(cards.length > 0) {
        let lastCard = cards[cards.length - 1];
        let lastIdx = lastCard.id.replace('item_card_', '');
        
        let lastBody = document.getElementById(`item_body_${lastIdx}`);
        if(lastBody && lastBody.classList.contains('is-collapsed')) {
            lastBody.classList.remove('is-collapsed');
        }
        
        validasiDanTambahBaru(parseInt(lastIdx));
    } else {
        tambahItemFormBaru(null, false); 
    }
}

function validasiDanTambahBaru(currentIdx) {
    let merk = document.getElementById(`merk_${currentIdx}`)?.value.trim();
    let sertifikat = document.getElementById(`sertifikat_${currentIdx}`)?.value.trim();
    let satuan = document.getElementById(`satuan_${currentIdx}`)?.value.trim();
    let vol = document.getElementById(`vol_${currentIdx}`)?.value.trim();
    let harga = document.getElementById(`harga_${currentIdx}`)?.value.trim();
    let errorBox = document.getElementById(`error_msg_${currentIdx}`);
    
    if(errorBox) errorBox.innerHTML = ""; 

    let errors = [];
    if (!merk) errors.push("Merk/Tipe");
    
    if (kategoriBelanjaUtama === 'Buku' && !sertifikat) {
        errors.push("No. Sertifikat/Penerbit (Wajib untuk Buku)");
    }
    
    if (!satuan) errors.push("Satuan");
    if (!vol || parseFloat(vol) <= 0) errors.push("Volume (QTY)");
    if (!harga || harga === "0" || harga === "") errors.push("Harga Satuan");

    if (errors.length > 0) {
        if(errorBox) {
            errorBox.innerHTML = `<div class="alert-validasi-item"><i class="bi bi-exclamation-triangle-fill"></i> Gagal tambah! Mohon lengkapi field berikut: <b>${errors.join(', ')}</b></div>`;
        }
        return false;
    }

    tambahItemFormBaru(null, true); 
}

function tambahItemFormBaru(savedData = null, startOpen = false) {
    minimizeSemauItemCards();

    let container = document.getElementById('wrapper_container_items');
    let nomorUrut = container.children.length + 1;
    
    let defaultAset = (kategoriBelanjaUtama === 'Buku' ? 'Buku' : 'PERSONAL KOMPUTER');
    let isBuku = (kategoriBelanjaUtama === 'Buku');

    let id_val = savedData ? savedData.id : '';
    let kode_barang = savedData ? savedData.kode_barang : '';
    let nama_barang = savedData ? savedData.nama_barang : '';
    let jenis_aset = savedData ? savedData.jenis_aset : defaultAset;
    let merk_tipe = savedData ? savedData.merk_tipe : '';
    let no_sertifikat = savedData ? savedData.no_sertifikat : '';
    let ukuran_bangunan = savedData ? savedData.ukuran_bangunan : '-';
    let satuan = savedData ? savedData.satuan : ''; 
    let volume = savedData ? savedData.volume : ''; 
    let harga_satuan = savedData ? savedData.harga_satuan : '';

    let headLabel = nama_barang ? `ITEM: ${nama_barang}` : 'ITEM BARANG BARU';
    let collapseClass = startOpen ? "" : " is-collapsed";

    let rowHtml = `
    <div class="accordion-item-barang" id="item_card_${globalBarisIndex}">
        <div class="accordion-header-custom d-flex justify-content-between align-items-center" onclick="toggleAccordionItem(${globalBarisIndex})">
            <div class="d-flex align-items-center gap-2">
                <span class="badge rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width:24px; height:24px; font-size:11px; flex-shrink:0;">${nomorUrut}</span>
                <div class="d-flex flex-column">
                    <span class="fw-bold text-secondary text-uppercase small head-label-nama">${headLabel}</span>
                    <span class="text-muted" style="font-size: 11px; font-weight: 600;">Merk/Tipe: <span id="display_merk_${globalBarisIndex}" class="text-primary">${merk_tipe || '-'}</span></span>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="fw-bold text-dark fs-6 text-display-subtotal" id="label_subtotal_${globalBarisIndex}">Rp 0</span>
                <button type="button" class="btn btn-sm btn-outline-danger p-1 border-0" onclick="hapasSpesifikCard(${globalBarisIndex}, event)">
                    <i class="bi bi-trash3-fill fs-6"></i>
                </button>
            </div>
        </div>
        <div class="accordion-collapse-wrapper${collapseClass}" id="item_body_${globalBarisIndex}">
            <div class="accordion-body-custom">
                <input type="hidden" name="items[${globalBarisIndex}][id]" value="${id_val}">
                
                <div class="row g-3">
                    <div class="col-12 wrapper-cari">
                        <label class="form-label"><i class="bi bi-search"></i> Cari Nama Barang / Kode Aset Dari Katalog Pagu</label>
                        <input type="text" class="form-control field-cari-katalog" placeholder="Ketik nama barang yang dicari..." oninput="cariKatalogPaguAjax(this, ${globalBarisIndex})">
                        <div class="dropdown-saran-barang d-none" id="saran_box_${globalBarisIndex}"></div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Kode Barang (Asset)</label>
                        <input type="text" name="items[${globalBarisIndex}][kode_barang]" id="kode_barang_${globalBarisIndex}" class="form-control bg-disabled-readonly" readonly value="${kode_barang}">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Nama Barang / Uraian</label>
                        <input type="text" name="items[${globalBarisIndex}][nama_barang]" id="nama_barang_${globalBarisIndex}" class="form-control bg-disabled-readonly" readonly required value="${nama_barang}">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Jenis Aset</label>
                        <input type="text" name="items[${globalBarisIndex}][jenis_aset]" id="jenis_aset_${globalBarisIndex}" class="form-control bg-disabled-readonly" readonly required value="${jenis_aset}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Merk / Tipe *</label>
                        <!-- [TAMBAHAN] list="history_merk" autocomplete="off" -->
                        <input type="text" name="items[${globalBarisIndex}][merk_tipe]" id="merk_${globalBarisIndex}" class="form-control" list="history_merk" autocomplete="off" placeholder="Contoh: Lenovo Core i3" value="${merk_tipe}" oninput="document.getElementById('display_merk_${globalBarisIndex}').innerText = this.value || '-'; simpanKeLocalStorage();">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">No. Sertifikat / Pabrik / Penerbit <span class="label-wajib-buku">${isBuku ? '*' : ''}</span></label>
                        <!-- [TAMBAHAN] list="history_sertifikat" autocomplete="off" -->
                        <input type="text" name="items[${globalBarisIndex}][no_sertifikat]" id="sertifikat_${globalBarisIndex}" class="form-control" list="history_sertifikat" autocomplete="off" placeholder="${isBuku ? 'Wajib Diisi (Kategori Buku)' : 'Wajib jika kategori Buku / Pabrik'}" ${isBuku ? 'required' : ''} value="${no_sertifikat}" oninput="simpanKeLocalStorage()">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Ukuran / Dimensi Bangunan</label>
                        <input type="text" name="items[${globalBarisIndex}][ukuran_bangunan]" class="form-control" value="${ukuran_bangunan}" oninput="simpanKeLocalStorage()">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Satuan *</label>
                        <!-- [TAMBAHAN] list="history_satuan" autocomplete="off" -->
                        <input type="text" name="items[${globalBarisIndex}][satuan]" id="satuan_${globalBarisIndex}" class="form-control" list="history_satuan" autocomplete="off" placeholder="Pcs / Unit / Rim" required value="${satuan}" oninput="simpanKeLocalStorage()">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Volume (QTY) *</label>
                        <input type="number" step="any" min="0" name="items[${globalBarisIndex}][volume]" id="vol_${globalBarisIndex}" class="form-control text-center" placeholder="Qty" required value="${volume}" oninput="if(this.value < 0) this.value = ''; kalkulasiOtomatisRow(${globalBarisIndex}); simpanKeLocalStorage();">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Harga Satuan (RP) *</label>
                        <input type="text" name="items[${globalBarisIndex}][harga_satuan]" id="harga_${globalBarisIndex}" class="form-control text-end input-rupiah-mask" placeholder="Rp 0" required value="${harga_satuan}" oninput="formatInputKeRupiahMask(this); kalkulasiOtomatisRow(${globalBarisIndex}); simpanKeLocalStorage();">
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                    <div class="id-msg-validasi text-danger" id="error_msg_${globalBarisIndex}"></div>
                    <div class="text-end">
                        <span class="text-secondary fw-bold small text-uppercase me-2">Subtotal Item:</span>
                        <span class="fw-extrabold text-dark fs-5" id="subtotal_bawah_${globalBarisIndex}" style="font-weight:800;">Rp 0</span>
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-2">
                    <button type="button" class="btn fw-bold px-3 py-2 d-flex align-items-center shadow-sm text-white btn-tambah-dinamis" style="background-color: #0284c7; border: none; border-radius: 6px; font-size: 13px;" onclick="validasiDanTambahBaru(${globalBarisIndex})">
                        <i class="bi bi-plus-circle-fill me-1.5"></i> Tambah Item Barang
                    </button>
                </div>

            </div>
        </div>
    </div>`;

    container.insertAdjacentHTML('beforeend', rowHtml);
    
    let currentHargaEl = document.getElementById(`harga_${globalBarisIndex}`);
    if(currentHargaEl && savedData) {
        formatInputKeRupiahMask(currentHargaEl);
    }

    kalkulasiOtomatisRow(globalBarisIndex);
    globalBarisIndex++;
    updateNomorUrutDanGrandTotal();
}

function cariKatalogPaguAjax(inputElement, idx) {
    let keyword = inputElement.value.trim();
    let saranBox = document.getElementById(`saran_box_${idx}`);
    
    if (keyword.length < 2) {
        saranBox.classList.add('d-none');
        return;
    }

    fetch(`ajax_cari_barang.php?q=${encodeURIComponent(keyword)}&kategori=${encodeURIComponent(kategoriBelanjaUtama)}`)
        .then(response => response.json())
        .then(data => {
            saranBox.innerHTML = '';
            if (data.length > 0) {
                saranBox.classList.remove('d-none');
                data.forEach(item => {
                    let itemDiv = document.createElement('div');
                    itemDiv.className = 'saran-item';
                    itemDiv.innerHTML = `
                        <div class="saran-nama-barang">${item.nama_barang}</div>
                        <div class="mt-2 d-flex align-items-center gap-2">
                            <span class="badge bg-light text-dark border" style="font-size:11px;">${item.kode_barang}</span>
                            <span class="saran-jenis-aset-badge">${item.jenis_aset}</span>
                        </div>
                    `;
                    
                    itemDiv.onclick = function() {
                        document.getElementById(`kode_barang_${idx}`).value = item.kode_barang;
                        document.getElementById(`nama_barang_${idx}`).value = item.nama_barang;
                        document.getElementById(`jenis_aset_${idx}`).value = item.jenis_aset;
                        
                        let parentCard = document.getElementById(`item_card_${idx}`);
                        parentCard.querySelector('.head-label-nama').innerText = `ITEM: ${item.nama_barang}`;
                        
                        saranBox.classList.add('d-none');
                        inputElement.value = '';
                        
                        simpanKeLocalStorage();
                    };
                    saranBox.appendChild(itemDiv);
                });
            } else {
                saranBox.classList.add('d-none');
            }
        })
        .catch(err => console.error("Error fetching data:", err));
}

function kalkulasiOtomatisRow(idx) {
    let volEl = document.getElementById(`vol_${idx}`);
    let hargaEl = document.getElementById(`harga_${idx}`);
    
    if(!volEl || !hargaEl) return;
    
    let vol = parseFloat(volEl.value) || 0;
    let harga = hapusFormatRupiah(hargaEl.value);
    
    let subtotal = vol * harga;
    let formatted = 'Rp ' + new Intl.NumberFormat('id-ID').format(subtotal);
    
    document.getElementById(`label_subtotal_${idx}`).innerText = formatted;
    document.getElementById(`subtotal_bawah_${idx}`).innerText = formatted;
    
    updateNomorUrutDanGrandTotal();
}

function updateNomorUrutDanGrandTotal() {
    let container = document.getElementById('wrapper_container_items');
    let cards = container.querySelectorAll('.accordion-item-barang');
    let grandTotal = 0;
    
    cards.forEach((card, i) => {
        let badge = card.querySelector('.badge');
        if(badge) badge.innerText = i + 1;
        
        let idx = card.id.replace('item_card_', '');
        let vol = parseFloat(document.getElementById(`vol_${idx}`)?.value) || 0;
        let harga = hapusFormatRupiah(document.getElementById(`harga_${idx}`)?.value);
        grandTotal += (vol * harga);
    });
    
    document.getElementById('grand_total_display').innerText = 'Rp ' + new Intl.NumberFormat('id-ID').format(grandTotal);
}

function hapasSpesifikCard(idx, event) {
    event.stopPropagation();
    let card = document.getElementById(`item_card_${idx}`);
    if(card) {
        card.remove();
        updateNomorUrutDanGrandTotal();
        simpanKeLocalStorage();
        
        let container = document.getElementById('wrapper_container_items');
        if(container.children.length > 0) {
            let lastCard = container.lastElementChild;
            let lastBtn = lastCard.querySelector('.btn-tambah-dinamis');
            if(lastBtn) lastBtn.classList.remove('d-none');
        } else {
            tambahItemFormBaru(null, true);
        }
    }
}

function bersihkanFormatSebelumSubmit() {
    // FITUR BARU: Validasi BA_NO dan BA_TGL
    let inputBaNo = document.getElementById('adm_ba_no');
    let inputBaTgl = document.getElementById('adm_ba_tgl');

    if (!inputBaNo.value.trim()) {
        alert("Nomor Berita Acara (BA NO) belum diisi!");
        inputBaNo.focus();
        inputBaNo.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return false;
    }

    if (!inputBaTgl.value.trim()) {
        alert("Tanggal Berita Acara (BA TGL) belum diisi!");
        inputBaTgl.focus();
        inputBaTgl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return false;
    }

    // LOGIKA ASLI
    let container = document.getElementById('wrapper_container_items');
    if(container.children.length === 0) {
        alert("Minimal harus ada 1 barang untuk direalisasikan!");
        return false;
    }
    
    let allHarga = document.querySelectorAll('.input-rupiah-mask');
    allHarga.forEach(el => {
        el.value = hapusFormatRupiah(el.value);
    });
    
    localStorage.removeItem(localStorageKey);
    return true;
}

function simpanKeLocalStorage() {
    let formData = {
        adm_no_sp2d: document.getElementById('adm_no_sp2d')?.value || '',
        adm_sumber_perolehan: document.getElementById('adm_sumber_perolehan')?.value || '',
        adm_no_spk: document.getElementById('adm_no_spk')?.value || '',
        adm_ba_no: document.getElementById('adm_ba_no')?.value || '',
        adm_ba_tgl: document.getElementById('adm_ba_tgl')?.value || '',
        items: []
    };

    let cards = document.querySelectorAll('.accordion-item-barang');
    cards.forEach(card => {
        let idx = card.id.replace('item_card_', '');
        let itemData = {
            id: document.querySelector(`input[name="items[${idx}][id]"]`)?.value || '',
            kode_barang: document.getElementById(`kode_barang_${idx}`)?.value || '',
            nama_barang: document.getElementById(`nama_barang_${idx}`)?.value || '',
            jenis_aset: document.getElementById(`jenis_aset_${idx}`)?.value || '',
            merk_tipe: document.getElementById(`merk_${idx}`)?.value || '',
            no_sertifikat: document.getElementById(`sertifikat_${idx}`)?.value || '',
            ukuran_bangunan: document.querySelector(`input[name="items[${idx}][ukuran_bangunan]"]`)?.value || '',
            satuan: document.getElementById(`satuan_${idx}`)?.value || '',
            volume: document.getElementById(`vol_${idx}`)?.value || '',
            harga_satuan: document.getElementById(`harga_${idx}`)?.value || ''
        };
        formData.items.push(itemData);
    });

    localStorage.setItem(localStorageKey, JSON.stringify(formData));
}

function loadDraftDariLocalStorage() {
    let savedDataRaw = localStorage.getItem(localStorageKey);
    if(savedDataRaw) {
        try {
            let savedData = JSON.parse(savedDataRaw);
            
            if(document.getElementById('adm_no_sp2d')) document.getElementById('adm_no_sp2d').value = savedData.adm_no_sp2d || '';
            if(document.getElementById('adm_sumber_perolehan')) document.getElementById('adm_sumber_perolehan').value = savedData.adm_sumber_perolehan || 'BOS Reguler';
            if(document.getElementById('adm_no_spk')) document.getElementById('adm_no_spk').value = savedData.adm_no_spk || '';
            if(document.getElementById('adm_ba_no')) document.getElementById('adm_ba_no').value = savedData.adm_ba_no || '';
            if(document.getElementById('adm_ba_tgl')) document.getElementById('adm_ba_tgl').value = savedData.adm_ba_tgl || '';

            let container = document.getElementById('wrapper_container_items');
            container.innerHTML = ''; 
            globalBarisIndex = 0;

            if(savedData.items && savedData.items.length > 0) {
                savedData.items.forEach(item => {
                    tambahItemFormBaru(item, false);
                });
                
                let lastIdx = globalBarisIndex - 1;
                let lastBody = document.getElementById(`item_body_${lastIdx}`);
                if(lastBody) lastBody.classList.remove('is-collapsed');
                
            } else {
                tambahItemFormBaru(null, true);
            }

        } catch (e) {
            console.error("Gagal load draft:", e);
            tambahItemFormBaru(null, true);
        }
    } else {
        let container = document.getElementById('wrapper_container_items');
        if(container.children.length === 0) {
            tambahItemFormBaru(null, true);
        }
    }
}

// ---- [TAMBAHAN BARU] FUNGSI UPDATE GLOBAL HISTORY DATALIST ----
function updateGlobalHistory() {
    const populateDatalist = (listId, inputNameSelector) => {
        let datalist = document.getElementById(listId);
        if (!datalist) return;
        
        let uniqueValues = new Set();
        document.querySelectorAll('input[name*="[' + inputNameSelector + ']"]').forEach(input => {
            let val = input.value.trim();
            if (val !== '') uniqueValues.add(val);
        });

        datalist.innerHTML = '';
        uniqueValues.forEach(val => {
            let option = document.createElement('option');
            option.value = val;
            datalist.appendChild(option);
        });
    }

    populateDatalist('history_merk', 'merk_tipe');
    populateDatalist('history_sertifikat', 'no_sertifikat');
    populateDatalist('history_satuan', 'satuan');
}

// Deteksi saat user selesai mengetik agar history langsung update otomatis
document.addEventListener('change', function(e) {
    if (e.target && e.target.tagName === 'INPUT') {
        updateGlobalHistory();
    }
});
// ---- END FUNGSI GLOBAL HISTORY ----

loadDraftDariLocalStorage();

window.addEventListener('DOMContentLoaded', (event) => {
    let isEdit = document.querySelector('input[name="is_edit"]').value === '1';
    let savedDataRaw = localStorage.getItem(localStorageKey);
    if(isEdit && !savedDataRaw) {
        simpanKeLocalStorage();
    }
    
    // Panggil satu kali saat halaman selesai di load untuk sinkron data edit awal
    updateGlobalHistory();
});
</script>