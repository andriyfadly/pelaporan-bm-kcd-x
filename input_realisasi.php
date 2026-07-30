<?php
// KEAMANAN 1: Proteksi Session Cookie & Security Headers
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

// KEAMANAN 2: Proteksi Session Hijacking & Hak Akses
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';

if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit;
}

if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== md5($user_agent)) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}

// KEAMANAN 3: Generasi Anti-CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include "koneksi.php";

// [KEAMANAN BAJA]: Tipe data dan deklarasi awal
$id_sekolah = isset($_SESSION['id_sekolah']) ? trim($_SESSION['id_sekolah']) : '';
$bulan_realisasi = isset($_GET['bulan_realisasi']) ? (int)$_GET['bulan_realisasi'] : 0;

if ($bulan_realisasi <= 0 || $bulan_realisasi > 12) {
    echo "<div class='alert alert-danger m-3'><i class='bi bi-exclamation-triangle-fill me-2'></i>Parameter Bulan Realisasi tidak valid atau tidak ditemukan. Silakan pilih bulan terlebih dahulu pada menu utama.</div>";
    exit;
}

$daftar_bulan = [
    1 => "Januari", 2 => "Februari", 3 => "Maret", 4 => "April",
    5 => "Mei", 6 => "Juni", 7 => "Juli", 8 => "Agustus",
    9 => "September", 10 => "Oktober", 11 => "November", 12 => "Desember"
];

$nama_bulan_teks = $daftar_bulan[$bulan_realisasi] ?? '';

// ================= AMBIL NAMA SEKOLAH UNTUK ACUAN DATA =================
$satuan_pendidikan_tampil = "Sekolah Tidak Diketahui";

// [KEAMANAN BAJA]: 1. Prepared Statement untuk master sekolah
$query_master_sekolah = "SELECT * FROM `kode_sekolah` WHERE `id_sekolah` = ? OR `id` = ? LIMIT 1";
$stmt_master = mysqli_prepare($conn, $query_master_sekolah);
if ($stmt_master) {
    mysqli_stmt_bind_param($stmt_master, "ss", $id_sekolah, $id_sekolah);
    mysqli_stmt_execute($stmt_master);
    $result_master = mysqli_stmt_get_result($stmt_master);

    if ($result_master && mysqli_num_rows($result_master) > 0) {
        $row_master = mysqli_fetch_assoc($result_master);
        $satuan_pendidikan_tampil = $row_master['nama_sekolah'] ?? ($row_master['satuan_pendidikan'] ?? 'Sekolah Tidak Diketahui');
    } else {
        // [KEAMANAN BAJA]: 2. Prepared Statement untuk alternatif pencarian sekolah
        $query_sekolah_alt = "SELECT `satuan_pendidikan` FROM `data_barang_acuan` WHERE `id_sekolah` = ? LIMIT 1";
        $stmt_alt = mysqli_prepare($conn, $query_sekolah_alt);
        if ($stmt_alt) {
            mysqli_stmt_bind_param($stmt_alt, "s", $id_sekolah);
            mysqli_stmt_execute($stmt_alt);
            $result_sekolah_alt = mysqli_stmt_get_result($stmt_alt);
            
            if ($result_sekolah_alt && mysqli_num_rows($result_sekolah_alt) > 0) {
                $row_sekolah_alt = mysqli_fetch_assoc($result_sekolah_alt);
                $satuan_pendidikan_tampil = $row_sekolah_alt['satuan_pendidikan'];
            }
            mysqli_stmt_close($stmt_alt);
        }
    }
    mysqli_stmt_close($stmt_master);
}

// ================= COCOKAN STATUS DARI DATABASE =================
$status_laporan = 'Belum Dikirim';

// [KEAMANAN BAJA]: 3. Prepared Statement untuk cek status laporan
$query_status = "SELECT `status` FROM `laporan_realisasi` WHERE `id_sekolah` = ? AND `bulan` = ? LIMIT 1";
$stmt_status = mysqli_prepare($conn, $query_status);
if ($stmt_status) {
    mysqli_stmt_bind_param($stmt_status, "si", $id_sekolah, $bulan_realisasi);
    mysqli_stmt_execute($stmt_status);
    $res_status = mysqli_stmt_get_result($stmt_status);

    if ($res_status && mysqli_num_rows($res_status) > 0) {
        $row_status = mysqli_fetch_assoc($res_status);
        $status_laporan = $row_status['status'];
    }
    mysqli_stmt_close($stmt_status);
}

// Flag pembantu untuk mengunci tombol aksi kerja
$is_readonly = ($status_laporan === 'Menunggu Approval' || $status_laporan === 'Disetujui');
?>

<div class="row">
    <div class="col-12">
        <div class="card card-box p-4 mb-4 bg-white" style="border-radius: 20px; border: 1px solid #e2e8f0; box-shadow: 0 10px 25px rgba(148, 163, 184, 0.05);">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div>
                    <h5 class="fw-bold text-dark mb-1">
                        <i class="bi bi-file-earmark-spreadsheet text-primary me-2"></i>
                        Target Acuan Kerja Realisasi
                    </h5>
                    <p class="text-secondary small mb-0">
                        Menampilkan daftar kodering belanja modal untuk periode <strong>Bulan <?= htmlspecialchars($nama_bulan_teks, ENT_QUOTES, 'UTF-8'); ?> (<?= (int)$bulan_realisasi; ?>)</strong>. Klik Kode Rekening untuk melihat rincian uraiannya.
                    </p>
                </div>
                <div>
                    <a href="pilih_bulan.php" class="btn btn-light btn-sm fw-semibold border text-secondary px-3 ajax-link" data-page="pilih_bulan.php" data-title="Pilih Bulan Realisasi" style="border-radius: 10px;">
                        <i class="bi bi-arrow-left me-1"></i> Ganti Bulan
                    </a>
                </div>
            </div>
        </div>

        <div class="card card-box p-0 overflow-hidden bg-white shadow-sm" style="border-radius: 20px; border: 1px solid #e2e8f0;">
            <div class="p-4 border-bottom bg-light bg-opacity-50">
                <div class="row align-items-center">
                    <div class="col">
                        <h6 class="fw-bold mb-0 text-dark">Daftar Rekening Belanja Modal</h6>
                    </div>
                    <div class="col-auto">
                        <span class="badge bg-primary px-3 py-2 fw-semibold" style="border-radius: 8px;">
                            <?= htmlspecialchars($satuan_pendidikan_tampil, ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-dark">
                    <thead class="table-light text-secondary" style="font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                        <tr>
                            <th width="35%" class="ps-4 py-3">Kode Rekening</th>
                            <th width="20%" class="text-end py-3">Nilai Acuan</th>
                            <th width="20%" class="text-end py-3">Realisasi</th>
                            <th width="15%" class="text-end py-3">Kekurangan</th>
                            <th width="10%" class="text-center py-3 pe-4">Aksi Kerja</th>
                        </tr>
                    </thead>
                    <tbody style="font-size: 14px; font-weight: 600;">
                        <?php
                        $total_acuan = 0;
                        $total_realisasi = 0;
                        $total_kekurangan = 0;

                        // [KEAMANAN BAJA]: 4. Prepared Statement untuk pencarian target data acuan kerja
                        $query_acuan = "SELECT * FROM `data_barang_acuan` 
                                        WHERE (
                                            `id_sekolah` = ? 
                                            OR TRIM(`satuan_pendidikan`) = TRIM(?)
                                            OR `satuan_pendidikan` LIKE ?
                                        ) 
                                        AND `bulan` = ?";
                        
                        $stmt_acuan = mysqli_prepare($conn, $query_acuan);
                        $res_acuan = false;

                        if ($stmt_acuan) {
                            $like_satuan = "%" . trim($satuan_pendidikan_tampil) . "%";
                            
                            mysqli_stmt_bind_param($stmt_acuan, "sssi", $id_sekolah, $satuan_pendidikan_tampil, $like_satuan, $bulan_realisasi);
                            mysqli_stmt_execute($stmt_acuan);
                            $res_acuan = mysqli_stmt_get_result($stmt_acuan);
                        }

                        if ($res_acuan && mysqli_num_rows($res_acuan) > 0) {
                            
                            $grouped_rows = [];
                            
                            // LANGKAH 1: Kelompokkan semua target data acuan kerja terlebih dahulu
                            while ($row = mysqli_fetch_assoc($res_acuan)) {
                                $id_acuan = (int)$row['id']; 
                                $kodering = trim($row['kodering']);
                                $uraian_acuan = $row['uraian'];
                                $nominal_acuan = (float)$row['nominal'];

                                if (!isset($grouped_rows[$kodering])) {
                                    $grouped_rows[$kodering] = [
                                        'id_acuan_terakhir' => $id_acuan, 
                                        'nominal_acuan' => 0,
                                        'nominal_realisasi' => 0,
                                        'kekurangan' => 0,
                                        'list_uraian' => []
                                    ];
                                }

                                $grouped_rows[$kodering]['nominal_acuan'] += $nominal_acuan;
                                $grouped_rows[$kodering]['list_uraian'][] = $uraian_acuan;
                            }

                            // [KEAMANAN BAJA]: 5. Prepared Statement untuk perhitungan realisasi (Disiapkan 1x di luar loop agar server cepat)
                            $query_realisasi = "SELECT SUM(nilai_perolehan) AS total_terinput 
                                                FROM `realisasi_barang_sekolah` 
                                                WHERE `kodering_belanja` = ? 
                                                AND `id_sekolah` = ?
                                                AND `bulan_realisasi` = ?";
                            $stmt_realisasi = mysqli_prepare($conn, $query_realisasi);

                            // LANGKAH 2: Hitung realisasi secara mandiri per KODERING (Mencegah kalkulasi ganda)
                            if ($stmt_realisasi) {
                                foreach ($grouped_rows as $kodering => $data) {
                                    // Bind parameter menggunakan prepared statement di dalam loop
                                    mysqli_stmt_bind_param($stmt_realisasi, "ssi", $kodering, $id_sekolah, $bulan_realisasi);
                                    mysqli_stmt_execute($stmt_realisasi);
                                    $res_realisasi = mysqli_stmt_get_result($stmt_realisasi);
                                    $data_realisasi = mysqli_fetch_assoc($res_realisasi);
                                    
                                    $nominal_realisasi = (float)($data_realisasi['total_terinput'] ?? 0);
                                    
                                    // Pasang data murni realisasi ke ringkasan grup
                                    $grouped_rows[$kodering]['nominal_realisasi'] = $nominal_realisasi;
                                    $grouped_rows[$kodering]['kekurangan'] = $grouped_rows[$kodering]['nominal_acuan'] - $nominal_realisasi;
                                }
                                mysqli_stmt_close($stmt_realisasi); // Bebaskan memori
                            }

                            // LANGKAH 3: Cetak baris tabel berdasarkan mapping data yang sudah stabil
                            $id_increment = 0;
                            foreach ($grouped_rows as $kodering => $data) {
                                $id_increment++;
                                $collapse_id = "detail_uraian_" . $id_increment;

                                $total_acuan += $data['nominal_acuan'];
                                $total_realisasi += $data['nominal_realisasi'];
                                $total_kekurangan += $data['kekurangan'];

                                $text_class = "text-danger";
                                if ($data['kekurangan'] <= 0) {
                                    $text_class = "text-success"; 
                                } elseif ($data['kekurangan'] < $data['nominal_acuan']) {
                                    $text_class = "text-warning"; 
                                }
                                ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold text-dark" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#<?= htmlspecialchars($collapse_id, ENT_QUOTES, 'UTF-8'); ?>">
                                            <i class="bi bi-plus-square text-primary me-1 small"></i> 
                                            <?= htmlspecialchars($kodering, ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                        
                                        <div id="<?= htmlspecialchars($collapse_id, ENT_QUOTES, 'UTF-8'); ?>" class="collapse mt-1">
                                            <div class="p-2 bg-light border-start border-primary border-3 rounded-end" style="font-size: 11px; font-weight: 500; max-height: 150px; overflow-y: auto;">
                                                <span class="text-muted d-block mb-1 fw-bold">Daftar Uraian Pekerjaan:</span>
                                                <?php foreach ($data['list_uraian'] as $index => $item_uraian): ?>
                                                    <div class="text-secondary text-truncate mb-1" title="<?= htmlspecialchars($item_uraian, ENT_QUOTES, 'UTF-8'); ?>">
                                                        <?= ($index + 1) . ". " . htmlspecialchars($item_uraian, ENT_QUOTES, 'UTF-8'); ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-end text-secondary">
                                        Rp <?= number_format($data['nominal_acuan'], 0, ',', '.'); ?>
                                    </td>
                                    <td class="text-end text-primary">
                                        Rp <?= number_format($data['nominal_realisasi'], 0, ',', '.'); ?>
                                    </td>
                                    <td class="text-end <?= $text_class; ?>">
                                        Rp <?= number_format($data['kekurangan'], 0, ',', '.'); ?>
                                    </td>
                                    <td class="text-center pe-4">
                                        <div class="d-flex justify-content-center gap-1">
                                            <?php 
                                            $url_tambah = "tambah_spj.php?id_uraian=" . (int)$data['id_acuan_terakhir'] . "&kodering=" . urlencode($kodering) . "&acuan=" . (float)$data['nominal_acuan'] . "&sisa=" . (float)$data['kekurangan'] . "&bulan_realisasi=" . (int)$bulan_realisasi;
                                            $url_edit = "edit_spj.php?id_uraian=" . (int)$data['id_acuan_terakhir'] . "&kodering=" . urlencode($kodering) . "&bulan_realisasi=" . (int)$bulan_realisasi;
                                            ?>

                                            <?php if ($data['kekurangan'] <= 0): ?>
                                                <button class="btn btn-success btn-sm fw-bold border-0 px-2 disabled d-inline-flex align-items-center justify-content-center" style="border-radius: 8px; height: 32px; font-size: 12px;" title="Anggaran Selesai Terealisasi">
                                                    <i class="bi bi-check-circle-fill me-1"></i> Selesai
                                                </button>
                                                
                                                <?php if ($is_readonly): ?>
                                                    <button type="button" class="btn btn-secondary btn-sm fw-bold border-0 px-2 text-white-50 d-inline-flex align-items-center justify-content-center" style="border-radius: 8px; height: 32px; font-size: 12px; cursor: not-allowed;" disabled title="Laporan sudah dikirim, tidak bisa diedit">
                                                        <i class="bi bi-lock-fill me-1"></i> Edit
                                                    </button>
                                                <?php else: ?>
                                                    <a href="<?= htmlspecialchars($url_edit, ENT_QUOTES, 'UTF-8'); ?>" 
                                                       class="btn btn-warning btn-sm fw-bold border-0 px-2 text-white shadow-sm ajax-link d-inline-flex align-items-center justify-content-center" 
                                                       data-page="<?= htmlspecialchars($url_edit, ENT_QUOTES, 'UTF-8'); ?>" 
                                                       data-title="Edit Realisasi SPJ"
                                                       style="border-radius: 8px; height: 32px; font-size: 12px;" title="Ubah Data Realisasi">
                                                        <i class="bi bi-pencil-square me-1"></i> Edit
                                                    </a>
                                                <?php endif; ?>

                                            <?php else: ?>
                                                
                                                <?php if ($is_readonly): ?>
                                                    <button type="button" class="btn btn-secondary btn-sm fw-bold border-0 text-white-50 d-inline-flex align-items-center justify-content-center" style="border-radius: 8px; width: 32px; height: 32px; font-size: 14px; cursor: not-allowed;" disabled title="Laporan sudah dikirim, tidak bisa ditambah">
                                                        <i class="bi bi-lock-fill"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <a href="<?= htmlspecialchars($url_tambah, ENT_QUOTES, 'UTF-8'); ?>" 
                                                       class="btn btn-primary btn-sm fw-bold border-0 shadow-sm ajax-link text-center d-inline-flex align-items-center justify-content-center" 
                                                       data-page="<?= htmlspecialchars($url_tambah, ENT_QUOTES, 'UTF-8'); ?>"
                                                       data-title="Tambah Realisasi SPJ"
                                                       style="border-radius: 8px; width: 32px; height: 32px; font-size: 14px; transition: all 0.3s ease;" title="Input Realisasi Baru">
                                                        <i class="bi bi-plus-lg"></i>
                                                    </a>
                                                <?php endif; ?>

                                                <?php if ($data['nominal_realisasi'] > 0): ?>
                                                    <?php if ($is_readonly): ?>
                                                        <button type="button" class="btn btn-secondary btn-sm fw-bold border-0 px-2 text-white-50 d-inline-flex align-items-center justify-content-center" style="border-radius: 8px; height: 32px; font-size: 12px; cursor: not-allowed;" disabled title="Laporan sudah dikirim, tidak bisa diedit">
                                                            <i class="bi bi-lock-fill me-1"></i> Edit
                                                        </button>
                                                    <?php else: ?>
                                                        <a href="<?= htmlspecialchars($url_edit, ENT_QUOTES, 'UTF-8'); ?>" 
                                                           class="btn btn-warning btn-sm fw-bold border-0 px-2 text-white shadow-sm ajax-link d-inline-flex align-items-center justify-content-center" 
                                                           data-page="<?= htmlspecialchars($url_edit, ENT_QUOTES, 'UTF-8'); ?>" 
                                                           data-title="Edit Realisasi SPJ"
                                                           style="border-radius: 8px; height: 32px; font-size: 12px;" title="Ubah Data Realisasi">
                                                            <i class="bi bi-pencil-square me-1"></i> Edit
                                                        </a>
                                                    <?php endif; ?>
                                                <?php endif; ?>

                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php
                            }
                        } else {
                            ?>
                            <tr>
                                <td colspan="6" class="text-center text-secondary py-5">
                                    <i class="bi bi-folder-x text-muted d-block mb-2" style="font-size: 40px;"></i>
                                    Tidak ada target acuan kodering anggaran pada bulan ini.
                                </td>
                            </tr>
                            <?php
                        }
                        if ($stmt_acuan) {
                            mysqli_stmt_close($stmt_acuan);
                        }
                        ?>
                    </tbody>

                    <?php if ($res_acuan && mysqli_num_rows($res_acuan) > 0): 
                        $total_text_class = "text-danger";
                        if ($total_kekurangan <= 0) {
                            $total_text_class = "text-success";
                        } elseif ($total_kekurangan < $total_acuan) {
                            $total_text_class = "text-warning";
                        }
                    ?>
                    <tfoot class="table-light fw-bold" style="font-size: 14px; border-top: 2px solid #cbd5e1;">
                        <tr>
                            <td class="ps-4 py-3 text-dark fw-bold">TOTAL</td>
                            <td class="text-end text-dark fw-bold">
                                Rp <?= number_format($total_acuan, 0, ',', '.'); ?>
                            </td>
                            <td class="text-end text-primary fw-bold">
                                Rp <?= number_format($total_realisasi, 0, ',', '.'); ?>
                            </td>
                            <td class="text-end <?= $total_text_class; ?> fw-bold">
                                Rp <?= number_format($total_kekurangan, 0, ',', '.'); ?>
                            </td>
                            <td class="text-center pe-4"></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>

                </table>
            </div>
        </div>

        <div class="card card-box p-4 mt-4 bg-white shadow-sm" style="border-radius: 20px; border: 1px solid #e2e8f0;">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
                <div>
                    <h6 class="fw-bold text-dark mb-1">Status Pengajuan Laporan</h6>
                    <p class="text-secondary small mb-0">Pastikan kekurangan bernilai Rp 0 pada semua kodering sebelum mengirimkan laporan ke Admin.</p>
                </div>
                <div>
                    <?php if ($status_laporan === 'Menunggu Approval'): ?>
                        <div class="alert alert-warning m-0 fw-bold d-inline-flex align-items-center px-4 py-2" style="border-radius: 10px; border: 1px solid #ffeba2; font-size: 14px;">
                            <i class="bi bi-hourglass-split me-2 text-warning"></i> Menunggu di Approved Admin
                        </div>
                    <?php elseif ($status_laporan === 'Disetujui'): ?>
                        <div class="alert alert-success m-0 fw-bold d-inline-flex align-items-center px-4 py-2" style="border-radius: 10px; border: 1px solid #b7ebc6; font-size: 14px;">
                            <i class="bi bi-check-circle-fill me-2 text-success"></i> Laporan Selesai & Disetujui
                        </div>
                    <?php else: ?>
                        <!-- Form diubah ID dan Event-nya untuk Handle via AJAX -->
                        <form id="formKirimLaporan" onsubmit="prosesKirim(event)">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="id_sekolah" value="<?= htmlspecialchars($id_sekolah, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="bulan_realisasi" value="<?= (int)$bulan_realisasi; ?>">
                            
                            <?php 
                            $sudah_selesai_semuall = ($res_acuan && mysqli_num_rows($res_acuan) > 0 && $total_kekurangan <= 0);
                            if ($sudah_selesai_semuall): 
                            ?>
                                <button type="submit" id="btnKirimLaporan" class="btn btn-primary fw-bold px-4 py-2 shadow-sm d-inline-flex align-items-center" style="border-radius: 10px; font-size: 14px;">
                                    <i class="bi bi-send-fill me-2"></i> Kirim Laporan
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-secondary fw-bold px-4 py-2 text-white-50 d-inline-flex align-items-center" style="border-radius: 10px; cursor: not-allowed; font-size: 14px;" disabled title="Belum bisa kirim! Realisasi belum selesai semua.">
                                    <i class="bi bi-lock-fill me-2"></i> Kirim Laporan (Readonly)
                                </button>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- MODAL SUKSES KIRIM LAPORAN -->
<div class="modal fade" id="modalSuksesKirim" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-body text-center p-4">
                <div class="d-inline-flex align-items-center justify-content-center bg-success bg-opacity-10 text-success rounded-circle mb-3" style="width: 72px; height: 72px;">
                    <i class="bi bi-check-circle-fill" style="font-size: 36px;"></i>
                </div>
                <h5 class="text-dark mb-2" style="font-weight: 800;">Laporan Terkirim!</h5>
                <p class="text-secondary small px-2 mb-0">Status laporan telah berubah. Memuat ulang halaman dalam beberapa detik...</p>
            </div>
        </div>
    </div>
</div>

<!-- SCRIPT AJAX SUBMISSION -->
<script>
function prosesKirim(event) {
    event.preventDefault(); // Mencegah form memuat ulang / pindah halaman default
    
    let form = document.getElementById('formKirimLaporan');
    if (!form) return;

    let formData = new FormData(form);
    formData.append('csrf_token', <?= json_encode($_SESSION['csrf_token']); ?>);
    let btnKirim = document.getElementById('btnKirimLaporan');

    if (!btnKirim) return;

    // Ubah status tombol agar tidak di klik 2 kali
    btnKirim.disabled = true;
    btnKirim.innerHTML = '<i class="bi bi-arrow-repeat me-2"></i> Memproses...';

    // Eksekusi data ke PHP tujuan lewat fetch API
    fetch('proses_kirim_laporan.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        // Tampilkan Modal Sukses
        let modalEl = document.getElementById('modalSuksesKirim');
        if (modalEl && typeof bootstrap !== 'undefined') {
            let myModal = new bootstrap.Modal(modalEl);
            myModal.show();
        }

        // Tahan selama 3 detik, lalu refresh halaman untuk update status
        setTimeout(function() {
            window.location.reload();
        }, 3000);
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Terjadi kesalahan saat mengirim laporan ke server.');
        // Kembalikan status tombol jika error
        btnKirim.disabled = false;
        btnKirim.innerHTML = '<i class="bi bi-send-fill me-2"></i> Kirim Laporan';
    });
}
</script>
