<?php
// Set atribut keamanan session cookie sebelum session dimulai
if (session_status() === PHP_SESSION_NONE) {
    @ini_set('session.cookie_httponly', 1);
    @ini_set('session.use_only_cookies', 1);
    session_start();
}

// Set Header Keamanan HTTP
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

// Aktifkan mode exception MySQLi untuk penanganan error yang aman (try-catch)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// 1. Validasi Proteksi Sesi Login
if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    echo "<script>window.location.href='login.php';</script>";
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include "koneksi.php";

$id_sekolah = isset($_SESSION['id_sekolah']) ? trim((string)$_SESSION['id_sekolah']) : '';

// 2. Tangkap & Sanitasi Parameter URL / Sesi
$id_uraian_get = isset($_GET['id_uraian']) ? trim((string)$_GET['id_uraian']) : ''; 
$kodering_get  = isset($_GET['kodering']) ? trim((string)$_GET['kodering']) : '';
$bulan_get     = isset($_GET['bulan_realisasi']) ? (int)$_GET['bulan_realisasi'] : 0;

$id_uraian_sess = isset($_SESSION['last_id_uraian']) ? trim((string)$_SESSION['last_id_uraian']) : '';
$kodering_sess  = isset($_SESSION['last_kodering']) ? trim((string)$_SESSION['last_kodering']) : '';
$bulan_sess     = isset($_SESSION['last_bulan']) ? (int)$_SESSION['last_bulan'] : 0;

$id_uraian       = !empty($id_uraian_get) ? $id_uraian_get : $id_uraian_sess;
$kodering        = !empty($kodering_get) ? $kodering_get : $kodering_sess;

// Validasi rentang bulan_realisasi (1-12)
$bulan_realisasi = ($bulan_get >= 1 && $bulan_get <= 12) ? $bulan_get : (($bulan_sess >= 1 && $bulan_sess <= 12) ? $bulan_sess : 0); 

$sisa_awal = 0.0;
$realisasi_awal_db = 0.0;
$spk_groups = [];

try {
    // =========================================================================
    // 🔍 FITUR SINKRONISASI OTOMATIS (UPDATE & CLEAN) BERBASIS PREPARED STATEMENT
    // =========================================================================

    // 1. UPDATE: Sinkronisasi berdasarkan Dokumen SPK, Volume, dan Harga Satuan
    $q_sync_upd = "UPDATE realisasi_barang_sekolah r
                   INNER JOIN master_barang_sekolah m ON 
                      r.id_sekolah = m.id_sekolah 
                      AND r.bulan_realisasi = m.bulan_realisasi
                      AND r.no_spk = m.no_spk
                      AND r.volume = m.volume
                      AND r.harga_satuan = m.harga_satuan
                   SET r.kode_barang = m.kode_barang,
                       r.nama_barang = m.nama_barang,
                       r.jenis_aset = m.jenis_aset,
                       r.merk_tipe = m.merk_tipe,
                       r.satuan = m.satuan,
                       r.nilai_perolehan = m.nilai_perolehan
                   WHERE r.id_sekolah = ? 
                     AND r.kodering_belanja = ?
                     AND r.bulan_realisasi = ?";

    $stmt_sync = mysqli_prepare($conn, $q_sync_upd);
    if ($stmt_sync) {
        mysqli_stmt_bind_param($stmt_sync, "ssi", $id_sekolah, $kodering, $bulan_realisasi);
        mysqli_stmt_execute($stmt_sync);
        mysqli_stmt_close($stmt_sync);
    }

    // 2. DELETE (Auto-Clean): Jika nomor SPK tersebut sudah dihapus dari MASTER, bersihkan dari REALISASI
    $q_sync_del = "DELETE FROM realisasi_barang_sekolah 
                   WHERE id_sekolah = ? 
                     AND kodering_belanja = ?
                     AND bulan_realisasi = ?
                     AND no_spk NOT IN (
                         SELECT no_spk COLLATE utf8mb4_general_ci
                         FROM master_barang_sekolah 
                         WHERE id_sekolah = ?
                           AND bulan_realisasi = ?
                     )";

    $stmt_del = mysqli_prepare($conn, $q_sync_del);
    if ($stmt_del) {
        mysqli_stmt_bind_param($stmt_del, "ssisi", $id_sekolah, $kodering, $bulan_realisasi, $id_sekolah, $bulan_realisasi);
        mysqli_stmt_execute($stmt_del);
        mysqli_stmt_close($stmt_del);
    }


    // 🔍 1. AMBIL DATA ACUAN UTAMA: Menggunakan tabel data_barang_acuan & kolom nominal
    $q_acuan = "SELECT SUM(nominal) as total_acuan 
                FROM data_barang_acuan 
                WHERE id_sekolah = ? 
                  AND kodering = ?";

    $stmt_acuan = mysqli_prepare($conn, $q_acuan);
    if ($stmt_acuan) {
        mysqli_stmt_bind_param($stmt_acuan, "ss", $id_sekolah, $kodering);
        mysqli_stmt_execute($stmt_acuan);
        $res_acuan = mysqli_stmt_get_result($stmt_acuan);
        if ($res_acuan && $r_acuan = mysqli_fetch_assoc($res_acuan)) {
            $sisa_awal = (float)($r_acuan['total_acuan'] ?? 0);
        }
        mysqli_stmt_close($stmt_acuan);
    }


    // 🔍 2. AMBIL TOTAL REALISASI ASLI DARI DATABASE (BERDASARKAN KODERING & BULAN YANG DISINKRONKAN)
    $q_realisasi = "SELECT SUM(nilai_perolehan) as total_realisasi_db 
                    FROM realisasi_barang_sekolah 
                    WHERE id_sekolah = ? 
                      AND kodering_belanja = ? 
                      AND bulan_realisasi = ?
                      AND is_realisasi = 1";

    $stmt_realisasi = mysqli_prepare($conn, $q_realisasi);
    if ($stmt_realisasi) {
        mysqli_stmt_bind_param($stmt_realisasi, "ssi", $id_sekolah, $kodering, $bulan_realisasi);
        mysqli_stmt_execute($stmt_realisasi);
        $res_realisasi = mysqli_stmt_get_result($stmt_realisasi);
        if ($res_realisasi && $r_realisasi = mysqli_fetch_assoc($res_realisasi)) {
            $realisasi_awal_db = (float)($r_realisasi['total_realisasi_db'] ?? 0);
        }
        mysqli_stmt_close($stmt_realisasi);
    }

    // =========================================================================
    // 🔍 3. QUERY UTAMA: AMBIL DATA REALISASI BARANG UNTUK LIST TABEL
    // =========================================================================
    $q_barang = "SELECT id, id_sekolah, id_uraian, no_sp2d, sumber_perolehan, kodering_belanja, bulan_realisasi, no_spk, ba_no, ba_tgl, kode_barang, nama_barang, jenis_aset, merk_tipe, satuan, volume, harga_satuan, nilai_perolehan, is_realisasi
                 FROM realisasi_barang_sekolah 
                 WHERE id_sekolah = ? 
                   AND kodering_belanja = ?
                   AND bulan_realisasi = ?
                 ORDER BY no_spk ASC, nama_barang ASC";

    $stmt_barang = mysqli_prepare($conn, $q_barang);
    if ($stmt_barang) {
        mysqli_stmt_bind_param($stmt_barang, "ssi", $id_sekolah, $kodering, $bulan_realisasi);
        mysqli_stmt_execute($stmt_barang);
        $res_barang = mysqli_stmt_get_result($stmt_barang);

        if ($res_barang) {
            while ($row = mysqli_fetch_assoc($res_barang)) {
                $clean_spk = !empty($row['no_spk']) ? trim($row['no_spk']) : "TANPA_NOMOR_SPK";
                $clean_kodering = !empty($row['kodering_belanja']) ? trim($row['kodering_belanja']) : "Belum Ditentukan";

                $group_key = $clean_spk . "_" . $clean_kodering;

                if (!isset($spk_groups[$group_key])) {
                    $spk_groups[$group_key] = [
                        'no_spk' => $clean_spk,
                        'kodering_belanja' => $clean_kodering,
                        'sumber_perolehan' => $row['sumber_perolehan'] ?: 'BOS Reguler',
                        'items' => []
                    ];
                }
                $spk_groups[$group_key]['items'][] = $row;
            }
        }
        mysqli_stmt_close($stmt_barang);
    }

} catch (Throwable $e) {
    // Log detail eror ke server log (tidak ditampilkan ke user demi keamanan)
    error_log("Database Error [edit_realisasi]: " . $e->getMessage());
}

// Hitung sisa anggaran default awal
$sisa_anggaran_awal = $sisa_awal - $realisasi_awal_db;

if (!empty($kodering_get)) {
    $_SESSION['last_id_uraian'] = $id_uraian;
    $_SESSION['last_kodering']   = $kodering;
    $_SESSION['last_sisa']       = $sisa_awal;
    $_SESSION['last_bulan']      = $bulan_realisasi;
}

// Daftar nama bulan
$nama_bulan_arr = [
    1 => "Januari", 2 => "Februari", 3 => "Maret", 4 => "April",
    5 => "Mei", 6 => "Juni", 7 => "Juli", 8 => "Agustus",
    9 => "September", 10 => "Oktober", 11 => "November", 12 => "Desember"
];
$display_bulan = $nama_bulan_arr[$bulan_realisasi] ?? "Tidak Diketahui";
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
    .badge-kodering { background: #f0f9ff; color: #0369a1; font-weight: 700; padding: 4px 10px; border-radius: 8px; border: 1px solid #e0f2fe; font-size: 11.5px; }
    .badge-bulan { background: #fef3c7; color: #d97706; font-weight: 700; padding: 4px 10px; border-radius: 8px; border: 1px solid #fde68a; font-size: 11.5px; }
    .badge-acuan { background: #f1f5f9; color: #475569; font-weight: 700; padding: 4px 10px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 11.5px; }
    .badge-realisasi-top { background: #eff6ff; color: #1d4ed8; font-weight: 700; padding: 4px 10px; border-radius: 8px; border: 1px solid #dbeafe; font-size: 11.5px; }
    .badge-sisa { background: #f0fdf4; color: #166534; font-weight: 700; padding: 4px 10px; border-radius: 8px; border: 1px solid #dcfce7; font-size: 11.5px; }
    .badge-sisa.minus { background: #fef2f2 !important; color: #991b1b !important; border-color: #fecaca !important; }
    .grand-total-box { background: #f8fafc; border: 2px dashed #0ea5e9; border-radius: 20px; padding: 24px 28px; }
    
    .header-floating{
        position: fixed; top: 100px; right: 25px; left: 300px; z-index: 9999;
        background: rgba(255,255,255,0.95); backdrop-filter: blur(10px);
        padding: 12px 20px; border-radius: 18px; box-shadow: 0 8px 30px rgba(0,0,0,.12); border: 1px solid #e2e8f0;
    }
    .card-spj{ margin-top: 110px !important; }
    .checkbox-lg { width: 20px; height: 20px; cursor: pointer; accent-color: #0ea5e9; }
    .tr-selected { background-color: #f0f9ff !important; } 
    
    .table-custom-ui { width: 100%; border-collapse: collapse; border: 1px solid #cbd5e1; border-radius: 12px; overflow: hidden; }
    .table-custom-ui thead th { background-color: #1e3a8a; color: #ffffff; text-align: center; padding: 14px 10px; font-size: 13px; font-weight: 700; text-transform: uppercase; border: 1px solid #1e40af; }
    .table-custom-ui tbody td { padding: 12px; border: 1px solid #cbd5e1; vertical-align: middle; font-size: 13px; }
    .badge-bos-reguler { background-color: #3b82f6; color: white; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; display: inline-block; margin-bottom: 5px; }
    .text-spk-num { font-size: 12px; color: #334155; word-break: break-all; }

    .row-grup-start { border-top: 3px solid #64748b !important; background-color: #f8fafc !important; }
    .row-grup-end { border-bottom: 3px solid #64748b !important; }
</style>

<div class="row">
    <div class="col-12">
        <div class="card card-box bg-white card-spj p-4">
            
            <div class="border-bottom pb-3 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 header-floating">
                <div>
                    <h5 class="fw-bold text-dark m-0 d-inline-block me-3"><i class="bi bi-pencil-square text-primary"></i> Mode Edit Realisasi SPJ</h5>
                    <div class="d-flex flex-wrap gap-2 align-items-center d-inline-flex">
                        <span class="badge-bulan font-monospace">Bulan: <?= htmlspecialchars($display_bulan, ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="badge-kodering font-monospace">Utama: <?= htmlspecialchars($kodering, ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="badge-acuan font-monospace" id="acuan_display">Acuan: Rp <?= number_format($sisa_awal, 0, ',', '.'); ?></span>
                        <span class="badge-realisasi-top font-monospace" id="realisasi_top_display">Realisasi: Rp <?= number_format($realisasi_awal_db, 0, ',', '.'); ?></span>
                        <span class="badge-sisa font-monospace" id="sisa_anggaran_display">Sisa Anggaran: Rp <?= number_format($sisa_anggaran_awal, 0, ',', '.'); ?></span>
                    </div>
                </div>
                <div>
                    <button type="button" class="btn btn-white border px-3 py-2" style="border-radius: 12px; font-size: 13.5px; font-weight: 700;" onclick="kembaliKeMenu()">
                        <i class="bi bi-arrow-left me-1"></i> Kembali
                    </button>
                </div>
            </div>

            <form id="form_realisasi" onsubmit="return false;">
                <input type="hidden" id="payload_id_uraian" value="<?= htmlspecialchars($id_uraian, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" id="payload_kodering" value="<?= htmlspecialchars($kodering, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" id="payload_bulan" value="<?= (int)$bulan_realisasi; ?>">

                <div class="table-responsive mb-4">
                    <table class="table-custom-ui" id="tabel_master_spj">
                        <thead>
                            <tr>
                                <th width="20%">Informasi Dokumen Berkas (SPK)</th>
                                <th width="35%">Nama Barang / Uraian</th>
                                <th width="10%">Vol</th>
                                <th width="15%">Harga Satuan</th>
                                <th width="20%">Total Perolehan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($spk_groups)): ?>
                                <tr><td colspan="5" class="text-center p-4 text-secondary">Data tidak ditemukan atau silakan lakukan sinkronisasi realisasi barang sekolah.</td></tr>
                            <?php else: ?>
                                <?php 
                                $spk_index = 0;
                                foreach ($spk_groups as $key_id => $group): 
                                    $spk_index++;
                                    $total_items = count($group['items']);
                                ?>
                                    <?php foreach ($group['items'] as $index => $item): 
                                        
                                        $is_realisasi_db = isset($item['is_realisasi']) ? trim((string)$item['is_realisasi']) : '';
                                        
                                        if ($is_realisasi_db === '1' || strtolower($is_realisasi_db) === 'y') {
                                            $is_checked = true; 
                                        } else {
                                            $is_checked = false; 
                                        }
                                        
                                        $row_class = "spk-wrapper-row";
                                        if ($is_checked) { $row_class .= " tr-selected"; } 
                                        if ($index === 0) { $row_class .= " row-grup-start"; }
                                        if ($index === ($total_items - 1)) { $row_class .= " row-grup-end"; }
                                        
                                        $id_item = (int)$item['id'];
                                        $nilai_perolehan = (float)($item['nilai_perolehan'] ?? 0);
                                        $volume = (float)($item['volume'] ?? 0);
                                        $harga_satuan = (float)($item['harga_satuan'] ?? 0);
                                    ?>
                                        <tr id="tr_item_<?= $id_item; ?>" class="<?= htmlspecialchars($row_class, ENT_QUOTES, 'UTF-8'); ?>">
                                            
                                            <?php if ($index === 0): ?>
                                                <td rowspan="<?= $total_items ?>" valign="top" class="bg-light bg-opacity-50 cell-spk-group">
                                                    <span class="badge-bos-reguler"><?= htmlspecialchars($group['sumber_perolehan'], ENT_QUOTES, 'UTF-8') ?></span>
                                                    <div class="text-spk-num font-monospace fw-bold mt-1"><?= htmlspecialchars($group['no_spk'], ENT_QUOTES, 'UTF-8') ?></div>
                                                </td>
                                            <?php endif; ?>

                                            <td>
                                                <div class="d-flex align-items-start gap-2">
                                                    <input type="checkbox" 
                                                           class="checkbox-lg chk-realisasi sub-chk-group-<?= $spk_index ?>" 
                                                           name="item_pilihan[]" 
                                                           value="<?= $id_item; ?>"
                                                           data-total="<?= $nilai_perolehan; ?>"
                                                           <?= $is_checked ? 'checked' : ''; ?>
                                                           onchange="toggleRowSelection(this, <?= $id_item; ?>)">
                                                    <div>
                                                        <div class="fw-bold text-dark"><?= htmlspecialchars($item['nama_barang'], ENT_QUOTES, 'UTF-8') ?></div>
                                                        <div class="text-muted small" style="font-size:11px;">
                                                            <?= htmlspecialchars($item['jenis_aset'], ENT_QUOTES, 'UTF-8') ?> <?= !empty($item['merk_tipe']) ? ' - '.htmlspecialchars($item['merk_tipe'], ENT_QUOTES, 'UTF-8') : '' ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            
                                            <td class="text-center font-monospace">
                                                <?= number_format($volume, 0, ',', '.') ?> <?= htmlspecialchars($item['satuan'], ENT_QUOTES, 'UTF-8') ?>
                                            </td>
                                            
                                            <td class="text-end font-monospace">
                                                Rp <?= number_format($harga_satuan, 0, ',', '.') ?>
                                            </td>
                                            
                                            <td class="text-end font-monospace fw-bold">
                                                Rp <?= number_format($nilai_perolehan, 0, ',', '.') ?>
                                            </td>

                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="grand-total-box mb-4 shadow-sm">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span style="font-size: 13px; font-weight: 800; color: #0369a1;">TOTAL REALISASI PILIHAN AKTIF:</span>
                        <h2 class="font-monospace mb-0" id="grand_total_display" style="font-weight: 900; color: #0284c7; font-size: 2rem;">Rp <?= number_format($realisasi_awal_db, 0, ',', '.'); ?></h2>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                    <button type="button" class="btn btn-white px-4 py-2 border text-secondary" style="border-radius: 12px;" onclick="kembaliKeMenu()">Batal</button>
                    <button type="button" id="btn_simpan_realisasi" class="btn btn-primary px-4 py-2" style="border-radius: 12px; background: #0ea5e9; font-weight: 700; border:none;" onclick="eksekusiSimpanSPJ()">Simpan Perubahan Realisasi</button>
                </div>
            </form>

        </div>
    </div>
</div>

<script>
    var sisaAwalAnggaran = <?= (float)$sisa_awal; ?>;

    document.addEventListener("DOMContentLoaded", function() {
        hitungGrandTotal();
    });

    function hitungGrandTotal() {
        let checkboxes = document.querySelectorAll('.chk-realisasi:checked');
        let totalAkumulasi = 0;

        checkboxes.forEach(chk => {
            totalAkumulasi += parseFloat(chk.getAttribute('data-total')) || 0;
        });

        document.getElementById('grand_total_display').innerText = "Rp " + new Intl.NumberFormat('id-ID').format(totalAkumulasi);
        document.getElementById('realisasi_top_display').innerText = "Realisasi: Rp " + new Intl.NumberFormat('id-ID').format(totalAkumulasi);

        let sisaKini = sisaAwalAnggaran - totalAkumulasi;
        let displaySisa = document.getElementById('sisa_anggaran_display');
        displaySisa.innerText = "Sisa Anggaran: Rp " + new Intl.NumberFormat('id-ID').format(sisaKini);

        if (sisaKini < 0) {
            displaySisa.className = "badge-sisa font-monospace minus";
        } else {
            displaySisa.className = "badge-sisa font-monospace";
        }
    }

    function toggleRowSelection(checkbox, itemId) {
        let row = document.getElementById(`tr_item_${itemId}`);
        if(row) {
            if(checkbox.checked) {
                row.classList.add('tr-selected');
            } else {
                row.classList.remove('tr-selected');
            }
        }
        hitungGrandTotal(); 
    }

    function eksekusiSimpanSPJ() {
        let semuaCheckbox = document.querySelectorAll('.chk-realisasi');
        let listDaftarBarang = [];

        semuaCheckbox.forEach(chk => {
            listDaftarBarang.push({
                id_barang: chk.value,
                is_aktif: chk.checked ? 1 : 0
            });
        });

        let formData = new FormData();
        formData.append('id_uraian', document.getElementById('payload_id_uraian').value);
        formData.append('kodering', document.getElementById('payload_kodering').value);
        formData.append('bulan_realisasi', document.getElementById('payload_bulan').value);
        formData.append('csrf_token', <?= json_encode($_SESSION['csrf_token']); ?>);
        formData.append('paket_data_edit_json', JSON.stringify(listDaftarBarang));

        let btn = document.getElementById('btn_simpan_realisasi');
        btn.disabled = true;
        btn.innerText = "Menyimpan...";

        fetch('simpan_realisasi.php', {
            method: 'POST',
            body: formData
        })
        .then(res => {
            return res.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error(text);
                }
            });
        })
        .then(response => {
            if (response.status === 'success') {
                kembaliKeMenu();
            } else {
                alert("Gagal Menyimpan: " + (response.message || "Ada kendala pada simpan_realisasi.php"));
                btn.disabled = false;
                btn.innerText = "Simpan Perubahan Realisasi";
            }
        })
        .catch(err => {
            console.error("Detail Error:", err);
            alert("Terjadi kesalahan sistem!\n\nDetail: " + err.message);
            btn.disabled = false;
            btn.innerText = "Simpan Perubahan Realisasi";
        });
    }

    function kembaliKeMenu() {
        window.location.href = 'index.php?p=input_realisasi.php&bulan_realisasi=<?= (int)$bulan_realisasi; ?>';
    }
</script>
