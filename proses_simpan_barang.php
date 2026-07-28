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
    echo "<script>alert('Akses ditolak! Silahkan login terlebih dahulu.'); window.location.href='login.php';</script>";
    exit;
}

if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== md5($user_agent)) {
    session_unset();
    session_destroy();
    echo "<script>alert('Sesi tidak valid! Silahkan login kembali.'); window.location.href='login.php';</script>";
    exit;
}

// KEAMANAN 3: Validasi Request Method & CSRF Token
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "<script>alert('Metode akses salah!'); window.location.href='index.php';</script>";
    exit;
}

$posted_csrf = $_POST['csrf_token'] ?? '';
$session_csrf = $_SESSION['csrf_token'] ?? '';

if (empty($posted_csrf) || empty($session_csrf) || !hash_equals($session_csrf, $posted_csrf)) {
    echo "<script>alert('Akses Ditolak! Token keamanan tidak valid atau kadaluarsa (CSRF Detected).'); window.history.back();</script>";
    exit;
}

// KONEKSI DATABASE
include "koneksi.php";

// AMBIL DATA INDUK (HEADER FORM) & VALIDAISI INPUT
$id_sekolah       = $_SESSION['id_sekolah'] ?? '';
$is_edit          = isset($_POST['is_edit']) && $_POST['is_edit'] === '1';

// Whitelist & Boundary Check Input
$raw_kategori     = trim($_POST['kategori_belanja'] ?? 'Peralatan & Mesin');
$kategori_belanja = (stripos($raw_kategori, 'buku') !== false) ? 'Buku' : 'Peralatan & Mesin';

$bulan_input      = isset($_POST['bulan_realisasi']) ? (int)$_POST['bulan_realisasi'] : (int)date('n');
$bulan_realisasi  = ($bulan_input >= 1 && $bulan_input <= 12) ? $bulan_input : (int)date('n');

$no_sp2d          = trim($_POST['no_sp2d'] ?? '');
$sumber_perolehan = trim($_POST['sumber_perolehan'] ?? 'BOS Reguler');
$no_spk           = trim($_POST['no_spk'] ?? '');
$ba_no            = trim($_POST['ba_no'] ?? '');
$ba_tgl_raw       = trim($_POST['ba_tgl'] ?? '');

// Validasi Format Tanggal YYYY-MM-DD
if (!empty($ba_tgl_raw) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $ba_tgl_raw)) {
    $ba_tgl = $ba_tgl_raw;
} else {
    $ba_tgl = "0000-00-00"; 
}

// Ambil array items barang dari form
$items = $_POST['items'] ?? [];

if (!is_array($items) || empty($items)) {
    echo "<script>alert('Gagal menyimpan! Tidak ada item barang yang diinput.'); window.history.back();</script>";
    exit;
}

// KEAMANAN 4: Proteksi Backend Status Laporan (Gembok Data)
$status_laporan = 'Belum Dikirim';
$stmt_status = mysqli_prepare($conn, "SELECT `status` FROM `laporan_realisasi` WHERE `id_sekolah` = ? AND `bulan` = ? LIMIT 1");
if ($stmt_status) {
    mysqli_stmt_bind_param($stmt_status, "si", $id_sekolah, $bulan_realisasi);
    mysqli_stmt_execute($stmt_status);
    $res_status = mysqli_stmt_get_result($stmt_status);
    if ($res_status && $row_status = mysqli_fetch_assoc($res_status)) {
        $status_laporan = $row_status['status'];
    }
    mysqli_stmt_close($stmt_status);
}

if ($status_laporan === 'Menunggu Approval' || $status_laporan === 'Disetujui') {
    echo "<script>alert('Gagal menyimpan! Laporan bulan ini sudah dikirim atau disetujui, data terkunci.'); window.location.href='index.php?p=data_barang.php&bulan_realisasi=" . $bulan_realisasi . "';</script>";
    exit;
}

// MULAI DATABASE TRANSACTION (Safety Net)
mysqli_begin_transaction($conn);

try {
    
    // 🌟 LOGIKA AUTO-GENERATOR ID_URAIAN BERDASARKAN NOMOR URUT (1,2,3,4...)
    $id_uraian_fix = 0;

    if ($is_edit) {
        // [KEAMANAN BAJA]: Prepared Statement untuk Mode Edit (Mencegah SQL Injection di No SPK)
        $stmt_asal = mysqli_prepare($conn, "SELECT id_uraian FROM `master_barang_sekolah` WHERE `no_spk` = ? AND `id_sekolah` = ? LIMIT 1");
        if ($stmt_asal) {
            mysqli_stmt_bind_param($stmt_asal, "ss", $no_spk, $id_sekolah);
            mysqli_stmt_execute($stmt_asal);
            $q_cari_asal = mysqli_stmt_get_result($stmt_asal);
            
            if ($r_asal = mysqli_fetch_assoc($q_cari_asal)) {
                $id_uraian_fix = (int)$r_asal['id_uraian'];
            }
            mysqli_stmt_close($stmt_asal);
        }
    }

    // Jika masuk data baru ATAU ternyata data edit lama tidak ditemukan id_uraian-nya
    if ($id_uraian_fix == 0) {
        // [KEAMANAN BAJA]: Prepared Statement untuk mencari MAX ID
        $stmt_max = mysqli_prepare($conn, "SELECT MAX(CAST(id_uraian AS UNSIGNED)) as max_uraian FROM `master_barang_sekolah` WHERE `id_sekolah` = ?");
        if ($stmt_max) {
            mysqli_stmt_bind_param($stmt_max, "s", $id_sekolah);
            mysqli_stmt_execute($stmt_max);
            $q_max = mysqli_stmt_get_result($stmt_max);
            $r_max = mysqli_fetch_assoc($q_max);
            
            $max_sekarang = (int)($r_max['max_uraian'] ?? 0);
            
            // Otomatis menjadi urutan berikutnya (1, 2, 3, 4, dst...)
            $id_uraian_fix = $max_sekarang + 1;
            mysqli_stmt_close($stmt_max);
        }
    }

    // Logika Penghapusan Item yang Sengaja Dibuang User dari Interface Form Edit
    if ($is_edit) {
        $id_item_dipertahankan = [];
        foreach ($items as $item) {
            if (isset($item['id']) && !empty($item['id']) && is_numeric($item['id'])) {
                $id_item_dipertahankan[] = (int)$item['id'];
            }
        }
        
        // Jika ada item yang tersisa di form, hapus item di database yang TIDAK termasuk dalam form tersebut
        if (!empty($id_item_dipertahankan)) {
            // Bikin array of '?' sesuai jumlah ID yang dipertahankan untuk klausa IN (...)
            $placeholders = implode(',', array_fill(0, count($id_item_dipertahankan), '?'));
            
            // Format bind param: 2 string (no_spk, id_sekolah), 1 int (bulan_realisasi), dan sisanya int (id item)
            $types = "ssi" . str_repeat('i', count($id_item_dipertahankan));
            
            // Siapkan parameter binding
            $params = [$no_spk, $id_sekolah, $bulan_realisasi];
            foreach ($id_item_dipertahankan as $id_dip) {
                $params[] = $id_dip;
            }

            // [KEAMANAN BAJA]: Prepared Statement untuk penghapusan (DELETE IN)
            $q_clean = "DELETE FROM `master_barang_sekolah` 
                        WHERE `no_spk` = ? 
                        AND `id_sekolah` = ? 
                        AND `bulan_realisasi` = ?
                        AND `id` NOT IN ($placeholders)";
            
            $stmt_clean = mysqli_prepare($conn, $q_clean);
            if ($stmt_clean) {
                mysqli_stmt_bind_param($stmt_clean, $types, ...$params);
                
                if (!mysqli_stmt_execute($stmt_clean)) {
                    throw new Exception("Gagal membersihkan item yang dihapus.");
                }
                mysqli_stmt_close($stmt_clean);
            }
        }
    }

    // LOOPING UNTUK INSERT / UPDATE DATA BARANG
    foreach ($items as $index => $item) {
        $id_item       = isset($item['id']) && is_numeric($item['id']) ? (int)$item['id'] : 0; 
        $kode_barang   = trim($item['kode_barang'] ?? '');
        $nama_barang   = trim($item['nama_barang'] ?? '');
        $jenis_aset    = trim($item['jenis_aset'] ?? '');
        $merk_tipe     = trim($item['merk_tipe'] ?? '');
        $no_sertifikat = trim($item['no_sertifikat'] ?? '');
        $ukuran        = trim($item['ukuran_bangunan'] ?? '-');
        $satuan        = trim($item['satuan'] ?? 'UNIT');
        
        $volume        = (float)($item['volume'] ?? 0);
        $harga_satuan  = (float)($item['harga_satuan'] ?? 0);
        
        $kodering_item = isset($item['kodering_belanja']) ? trim($item['kodering_belanja']) : '';

        // HITUNG MATEMATIS NILAI PEROLEHAN (Volume x Harga Satuan)
        $nilai_perolehan = $volume * $harga_satuan;

        // Validasi data krusial sebelum masuk ke database
        if (empty($nama_barang) || $volume <= 0 || $harga_satuan <= 0) {
            throw new Exception("Item ke-" . ($index + 1) . " (" . (empty($nama_barang) ? 'Nama Kosong' : htmlspecialchars($nama_barang, ENT_QUOTES, 'UTF-8')) . ") gagal disimpan. Pastikan Nama, Volume, dan Harga sudah terisi dengan benar!");
        }

        if ($is_edit && $id_item > 0) {
            // OPTION A: JIKA DALAM MODE EDIT -> UPDATE (`id_uraian` menggunakan hasil hitung auto-counter)
            $q_update = "UPDATE `master_barang_sekolah` SET 
                            `id_uraian` = ?, `no_sp2d` = ?, `sumber_perolehan` = ?,
                            `no_spk` = ?, `ba_no` = ?, `ba_tgl` = ?, `kode_barang` = ?,
                            `nama_barang` = ?, `jenis_aset` = ?, `kategori` = ?, 
                            `merk_tipe` = ?, `no_sertifikat` = ?, `ukuran_bangunan` = ?,
                            `satuan` = ?, `volume` = ?, `harga_satuan` = ?, `nilai_perolehan` = ?
                         WHERE `id` = ? AND `id_sekolah` = ?";

            // [KEAMANAN BAJA]: Prepared Statement untuk UPDATE
            $stmt_update = mysqli_prepare($conn, $q_update);
            if ($stmt_update) {
                mysqli_stmt_bind_param($stmt_update, "isssssssssssssdddis", 
                    $id_uraian_fix, $no_sp2d, $sumber_perolehan, 
                    $no_spk, $ba_no, $ba_tgl, $kode_barang, 
                    $nama_barang, $jenis_aset, $kategori_belanja, 
                    $merk_tipe, $no_sertifikat, $ukuran, 
                    $satuan, $volume, $harga_satuan, $nilai_perolehan, 
                    $id_item, $id_sekolah
                );

                if (!mysqli_stmt_execute($stmt_update)) {
                    throw new Exception("Gagal memperbarui item: " . htmlspecialchars($nama_barang, ENT_QUOTES, 'UTF-8'));
                }
                mysqli_stmt_close($stmt_update);
            }

        } else {
            // OPTION B: JIKA INPUT BARU -> INSERT (`id_uraian` menggunakan hasil hitung auto-counter)
            $q_insert = "INSERT INTO `master_barang_sekolah` (
                            `id_sekolah`, `id_uraian`, `no_sp2d`, `sumber_perolehan`, 
                            `bulan_realisasi`, `no_spk`, `ba_no`, `ba_tgl`, `kode_barang`, 
                            `nama_barang`, `jenis_aset`, `kategori`, 
                            `merk_tipe`, `no_sertifikat`, `ukuran_bangunan`, 
                            `satuan`, `volume`, `harga_satuan`, `nilai_perolehan`
                         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            // [KEAMANAN BAJA]: Prepared Statement untuk INSERT
            $stmt_insert = mysqli_prepare($conn, $q_insert);
            if ($stmt_insert) {
                mysqli_stmt_bind_param($stmt_insert, "sississsssssssssddd", 
                    $id_sekolah, $id_uraian_fix, $no_sp2d, $sumber_perolehan, 
                    $bulan_realisasi, $no_spk, $ba_no, $ba_tgl, $kode_barang, 
                    $nama_barang, $jenis_aset, $kategori_belanja, 
                    $merk_tipe, $no_sertifikat, $ukuran, 
                    $satuan, $volume, $harga_satuan, $nilai_perolehan
                );

                if (!mysqli_stmt_execute($stmt_insert)) {
                    throw new Exception("Gagal menyimpan item baru: " . htmlspecialchars($nama_barang, ENT_QUOTES, 'UTF-8'));
                }
                mysqli_stmt_close($stmt_insert);
            }
        }

        // 🌟 AUTOMATIC SYNC: Update data ke REALISASI_BARANG_SEKOLAH menggunakan kecocokan kode_barang & no_spk
        if (!empty($kodering_item)) {
            $q_sync_realisasi = "UPDATE `REALISASI_BARANG_SEKOLAH` r
                                 INNER JOIN `master_barang_sekolah` m ON r.id_sekolah = m.id_sekolah 
                                                                  AND r.no_spk = m.no_spk
                                                                  AND r.kode_barang = m.kode_barang
                                 SET r.id_uraian = m.id_uraian,
                                     r.nama_barang = m.nama_barang,
                                     r.jenis_aset = m.jenis_aset,
                                     r.merk_tipe = m.merk_tipe,
                                     r.satuan = m.satuan,
                                     r.harga_satuan = m.harga_satuan,
                                     r.nilai_perolehan = (r.volume * m.harga_satuan)
                                 WHERE r.id_sekolah = ? 
                                   AND r.kodering_belanja = ?
                                   AND r.no_spk = ?
                                   AND r.kode_barang = ?";
                                   
            // [KEAMANAN BAJA]: Prepared Statement untuk UPDATE SYNC
            $stmt_sync = mysqli_prepare($conn, $q_sync_realisasi);
            if ($stmt_sync) {
                mysqli_stmt_bind_param($stmt_sync, "ssss", $id_sekolah, $kodering_item, $no_spk, $kode_barang);
                mysqli_stmt_execute($stmt_sync);
                mysqli_stmt_close($stmt_sync);
            }
        }
    }

    // Jika semua looping sukses tanpa interupsi, commit transaksi database
    mysqli_commit($conn);
    
    echo "<script>
            alert('Data Realisasi Barang Berhasil Disimpan! (ID Uraian Urutan ke-" . $id_uraian_fix . ")'); 
            window.location.href='index.php?p=data_barang.php&bulan_realisasi=" . $bulan_realisasi . "';
          </script>";
    exit;

} catch (Exception $e) {
    // Batalkan semua perubahan jika salah satu baris query gagal
    mysqli_rollback($conn);
    
    // [KEAMANAN BAJA]: Proteksi XSS (Cross-Site Scripting) pada Output Pesan Error
    $pesan_error_aman = addslashes(htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
    
    echo "<script>
            alert('Terjadi Kegagalan: " . $pesan_error_aman . "'); 
            window.history.back();
          </script>";
    exit;
}
?>