<?php
// Set atribut keamanan session cookie sebelum session dimulai
if (session_status() === PHP_SESSION_NONE) {
    @ini_set('session.cookie_httponly', 1);
    @ini_set('session.use_only_cookies', 1);
    session_start();
}

// Set Header JSON & Security Headers
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Aktifkan mode exception MySQLi untuk error handling try-catch yang aman
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Proteksi halaman user
if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'user') {
    echo json_encode(['status' => 'error', 'message' => 'Sesi login tidak valid atau kadaluarsa.']);
    exit;
}

include "koneksi.php";
require_once __DIR__ . '/report_lock.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (empty($csrf_token) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf_token)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Token keamanan tidak valid.']);
        exit;
    }
    
    // Ambil data session & parameter dasar utama + sanitasi awal
    $id_sekolah       = isset($_SESSION['id_sekolah']) ? trim((string)$_SESSION['id_sekolah']) : ''; 
    $id_uraian_acuan  = isset($_POST['id_uraian']) ? trim((string)$_POST['id_uraian']) : ''; 
    $kodering_belanja = isset($_POST['kodering']) ? trim((string)$_POST['kodering']) : ''; 
    
    // Validasi range bulan realisasi (1 - 12)
    $bulan_realisasi_raw = isset($_POST['bulan_realisasi']) ? (int)$_POST['bulan_realisasi'] : 0;
    $bulan_realisasi     = ($bulan_realisasi_raw >= 1 && $bulan_realisasi_raw <= 12) ? $bulan_realisasi_raw : 0;
    assert_report_unlocked($conn, $id_sekolah, $bulan_realisasi);
    
    // Tangkap data paket JSON dari client
    $paket_data_json      = isset($_POST['paket_data_json']) ? $_POST['paket_data_json'] : '';
    $paket_data_edit_json = isset($_POST['paket_data_edit_json']) ? $_POST['paket_data_edit_json'] : ''; 

    // Validasi Awal 
    if (empty($id_sekolah)) {
        echo json_encode(['status' => 'error', 'message' => 'Gagal! Identitas sekolah kosong.']);
        exit;
    }

    // =========================================================================
    // 🌟 JALUR PROSES 1: PROSES EDIT SPJ (UNCHECK BARANG)
    // =========================================================================
    if (!empty($paket_data_edit_json)) {
        
        $arr_edit = json_decode($paket_data_edit_json, true);
        
        // Validasi keabsahan struktur JSON
        if (!is_array($arr_edit) || json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(['status' => 'error', 'message' => 'Format data edit JSON tidak valid.']);
            exit;
        }

        try {
            mysqli_begin_transaction($conn);
            $total_updated = 0;

            // Persiapkan query SEBELUM loop agar performa database maksimal & anti injeksi
            $stmt_find = mysqli_prepare($conn, "SELECT `id_master_barang` FROM `realisasi_barang_sekolah` WHERE `id` = ? AND `id_sekolah` = ? LIMIT 1");
            $stmt_del  = mysqli_prepare($conn, "DELETE FROM `realisasi_barang_sekolah` WHERE `id` = ? AND `id_sekolah` = ?");
            $stmt_upd  = mysqli_prepare($conn, "UPDATE `master_barang_sekolah` SET `is_realisasi` = 0 WHERE `id` = ? AND `id_sekolah` = ?");
            
            foreach ($arr_edit as $val) {
                if (!is_array($val)) continue;

                $id_realisasi = isset($val['id_barang']) ? (int)$val['id_barang'] : 0;
                $is_aktif     = isset($val['is_aktif']) ? (int)$val['is_aktif'] : 0; // 0 jika di-uncheck
                
                // KONDISI A: JIKA DI-UNCHECK (Wajib Hilang dari Realisasi & Master JADI 0)
                if ($is_aktif === 0) {
                    
                    // 1. Ambil id_master_barang langsung dari tabel realisasi
                    mysqli_stmt_bind_param($stmt_find, "is", $id_realisasi, $id_sekolah);
                    mysqli_stmt_execute($stmt_find);
                    $res_find = mysqli_stmt_get_result($stmt_find);
                    
                    if ($res_find && $r_find = mysqli_fetch_assoc($res_find)) {
                        $id_master = (int)$r_find['id_master_barang'];
                        
                        // 2. Hapus data dari realisasi barang sekolah
                        mysqli_stmt_bind_param($stmt_del, "is", $id_realisasi, $id_sekolah);
                        if (mysqli_stmt_execute($stmt_del)) {
                            
                            // 3. Tembak master_barang_sekolah menggunakan ID MASTER
                            if (!empty($id_master)) {
                                mysqli_stmt_bind_param($stmt_upd, "is", $id_master, $id_sekolah);
                                mysqli_stmt_execute($stmt_upd);
                            }
                            $total_updated++;
                        }
                    } else {
                        $total_updated++; // Jika data sudah tidak ada di realisasi, skip aman
                    }
                } 
                // KONDISI B: JIKA TETAP DICENTANG
                else {
                    $total_updated++; // Dilewati saja demi keamanan data master lainnya
                }
            }
            
            if ($total_updated === count($arr_edit)) {
                mysqli_commit($conn);
                echo json_encode([
                    'status' => 'success', 
                    'message' => 'Sukses sinkronisasi perubahan status realisasi dan Master Barang.',
                    'bulan_realisasi' => $bulan_realisasi
                ]);
            } else {
                mysqli_rollback($conn);
                echo json_encode(['status' => 'error', 'message' => 'Gagal mengubah status item realisasi.']);
            }
            
            mysqli_stmt_close($stmt_find);
            mysqli_stmt_close($stmt_del);
            mysqli_stmt_close($stmt_upd);
            mysqli_close($conn);
            exit;

        } catch (Throwable $e) {
            mysqli_rollback($conn);
            // Log detail error ke file log server (bukan ditampilkan ke client)
            error_log("Database Error [Process 1 Edit SPJ]: " . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan sistem saat memproses perbaikan data.']);
            mysqli_close($conn);
            exit;
        }
    }

    // =========================================================================
    // 🔒 JALUR PROSES 2: PROSES SIMPAN REALISASI BARU (DENGAN REKAM ID MASTER)
    // =========================================================================
    else {
        if (empty($id_uraian_acuan) || empty($kodering_belanja)) {
            echo json_encode(['status' => 'error', 'message' => 'Gagal! ID acuan kerja atau kodering belanja kosong.']);
            exit;
        }

        $arr_spj = !empty($paket_data_json) ? json_decode($paket_data_json, true) : [];

        if (!is_array($arr_spj)) {
            echo json_encode(['status' => 'error', 'message' => 'Format data paket JSON tidak valid.']);
            exit;
        }

        if (empty($arr_spj)) {
            echo json_encode([
                'status' => 'success', 
                'message' => 'Tidak ada item baru yang disimpan.',
                'bulan_realisasi' => $bulan_realisasi
            ]);
            mysqli_close($conn);
            exit;
        }

        try {
            mysqli_begin_transaction($conn);

            // Persiapkan semua statement INSERT, SELECT, UPDATE di luar loop
            $query_insert = "INSERT INTO `realisasi_barang_sekolah` (
                                `id_sekolah`, `id_master_barang`, `id_uraian`, `no_sp2d`, `sumber_perolehan`, `kodering_belanja`, 
                                `bulan_realisasi`, `no_spk`, `ba_no`, `ba_tgl`, `kode_barang`, 
                                `nama_barang`, `jenis_aset`, `merk_tipe`, `no_sertifikat`, 
                                `ukuran_bangunan`, `satuan`, `volume`, `harga_satuan`, 
                                `nilai_perolehan`, `is_realisasi`, `created_at`
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

            $stmt_insert = mysqli_prepare($conn, $query_insert);
            $stmt_detail = mysqli_prepare($conn, "SELECT id_uraian, kode_barang, nama_barang, jenis_aset, merk_tipe, no_sertifikat, ukuran_bangunan, satuan, volume, harga_satuan FROM master_barang_sekolah WHERE id = ? AND id_sekolah = ?");
            $stmt_check  = mysqli_prepare($conn, "SELECT id FROM realisasi_barang_sekolah WHERE id_sekolah = ? AND id_master_barang = ? AND kodering_belanja = ? LIMIT 1");
            $stmt_upd_m  = mysqli_prepare($conn, "UPDATE master_barang_sekolah SET is_realisasi = 1 WHERE id = ? AND id_sekolah = ?");

            if ($stmt_insert && $stmt_detail && $stmt_check && $stmt_upd_m) {
                
                $total_success_items = 0;

                foreach ($arr_spj as $index => $row_data) {
                    if (!is_array($row_data)) continue;
                    
                    $id_barang        = isset($row_data['id_barang']) ? (int)$row_data['id_barang'] : 0; // ID Master Asli
                    $no_spk           = isset($row_data['no_spk']) ? trim((string)$row_data['no_spk']) : '';
                    $no_sp2ds         = isset($row_data['no_sp2d']) ? trim((string)$row_data['no_sp2d']) : '';
                    $sumber_perolehan = isset($row_data['sumber_perolehan']) ? trim((string)$row_data['sumber_perolehan']) : '';
                    $ba_no            = isset($row_data['ba_no']) ? trim((string)$row_data['ba_no']) : '';
                    $ba_tgl           = isset($row_data['ba_tgl']) ? trim((string)$row_data['ba_tgl']) : '';

                    if ($id_barang <= 0) continue;

                    // 1. Ambil Detail Barang
                    mysqli_stmt_bind_param($stmt_detail, "is", $id_barang, $id_sekolah);
                    mysqli_stmt_execute($stmt_detail);
                    $res_detail = mysqli_stmt_get_result($stmt_detail);
                    
                    if ($res_detail && $barang = mysqli_fetch_assoc($res_detail)) {
                        
                        $kode_barang     = $barang['kode_barang'];
                        $nama_barang     = $barang['nama_barang'];

                        // 2. Cek apakah id_master_barang ini sudah pernah di-realisasikan
                        mysqli_stmt_bind_param($stmt_check, "sis", $id_sekolah, $id_barang, $kodering_belanja);
                        mysqli_stmt_execute($stmt_check);
                        $res_check = mysqli_stmt_get_result($stmt_check);
                        
                        if ($res_check && mysqli_num_rows($res_check) > 0) {
                            continue; // Jika sudah ada, lewatkan
                        }

                        $id_uraian_fix   = $id_uraian_acuan; 
                        $jenis_aset      = !empty($barang['jenis_aset']) ? $barang['jenis_aset'] : '-';
                        $merk_tipe       = !empty($barang['merk_tipe']) ? $barang['merk_tipe'] : '-';
                        $no_sertifikat   = !empty($barang['no_sertifikat']) ? $barang['no_sertifikat'] : '-';
                        $ukuran_bangunan = !empty($barang['ukuran_bangunan']) ? $barang['ukuran_bangunan'] : '-';
                        $satuan          = !empty($barang['satuan']) ? $barang['satuan'] : 'Pcs';
                        $volume          = (float)$barang['volume'];
                        $harga_satuan    = (float)$barang['harga_satuan'];
                        
                        $nilai_perolehan = $volume * $harga_satuan;
                        $is_realisasi    = 1;

                        // 3. Eksekusi Insert
                        mysqli_stmt_bind_param(
                            $stmt_insert, "sissssissssssssssdddi", 
                            $id_sekolah, $id_barang, $id_uraian_fix, $no_sp2ds, $sumber_perolehan, $kodering_belanja, 
                            $bulan_realisasi, $no_spk, $ba_no, $ba_tgl, $kode_barang, 
                            $nama_barang, $jenis_aset, $merk_tipe, $no_sertifikat, $ukuran_bangunan, 
                            $satuan, $volume, $harga_satuan, $nilai_perolehan, $is_realisasi
                        );

                        if (mysqli_stmt_execute($stmt_insert)) {
                            // 4. Kunci perubahan status master barang
                            mysqli_stmt_bind_param($stmt_upd_m, "is", $id_barang, $id_sekolah);
                            mysqli_stmt_execute($stmt_upd_m);
                            
                            $total_success_items++;
                        } else {
                            mysqli_rollback($conn);
                            error_log("Database Error on insert item: " . mysqli_stmt_error($stmt_insert));
                            
                            // Mencegah XSS jika nama_barang berisi karakter khusus
                            $safe_nama_barang = htmlspecialchars($nama_barang, ENT_QUOTES, 'UTF-8');
                            echo json_encode([
                                'status' => 'error', 
                                'message' => "Gagal menyimpan barang: {$safe_nama_barang}."
                            ]);
                            mysqli_stmt_close($stmt_insert);
                            mysqli_stmt_close($stmt_detail);
                            mysqli_stmt_close($stmt_check);
                            mysqli_stmt_close($stmt_upd_m);
                            mysqli_close($conn);
                            exit;
                        }
                    }
                }

                if ($total_success_items > 0) {
                    mysqli_commit($conn);
                    echo json_encode([
                        'status' => 'success', 
                        'message' => "Sukses! Sebanyak {$total_success_items} rincian barang berhasil direalisasikan.",
                        'bulan_realisasi' => $bulan_realisasi
                    ]);
                } else {
                    mysqli_rollback($conn);
                    echo json_encode(['status' => 'error', 'message' => 'Gagal! Data sudah pernah disimpan atau tidak valid.']);
                }
                
                mysqli_stmt_close($stmt_insert);
                mysqli_stmt_close($stmt_detail);
                mysqli_stmt_close($stmt_check);
                mysqli_stmt_close($stmt_upd_m);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Gagal menyusun query ke database (Prepare Error).']);
            }
            
            mysqli_close($conn);

        } catch (Throwable $e) {
            mysqli_rollback($conn);
            // Catat log detail kesalahan secara internal
            error_log("Database Error [Process 2 Simpan Realisasi]: " . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan sistem saat menyimpan realisasi barang.']);
            mysqli_close($conn);
            exit;
        }
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Metode request tidak valid.']);
}
?>
