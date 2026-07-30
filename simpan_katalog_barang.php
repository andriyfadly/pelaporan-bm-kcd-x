<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set response header berupa JSON
header('Content-Type: application/json');

// Pastikan user sudah login
if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'user') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Sesi login tidak valid atau kadaluarsa.'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Metode request tidak valid.']);
    exit;
}

$csrf_token = $_POST['csrf_token'] ?? '';
if (empty($csrf_token) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf_token)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Token keamanan tidak valid.']);
    exit;
}

include "koneksi.php";

// Ambil data global dari form induk
$id_sekolah       = $_SESSION['id_sekolah'] ?? '';
$bulan_realisasi  = isset($_POST['bulan_realisasi']) ? (int)$_POST['bulan_realisasi'] : (int)date('n');
$no_sp2d          = trim($_POST['no_sp2d'] ?? '');
$sumber_perolehan = trim($_POST['sumber_perolehan'] ?? '');
$no_spk           = trim($_POST['no_spk'] ?? '');
$ba_no            = trim($_POST['ba_no'] ?? '');
$ba_tgl           = trim($_POST['ba_tgl'] ?? '');

// No. SPK yang wajib.
if (empty($no_spk)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No. SPK/Kwitansi wajib diisi!'
    ]);
    exit;
}

// Pastikan data array barang masuk dan tidak kosong
if (!isset($_POST['nama_barang']) || !is_array($_POST['nama_barang']) || count($_POST['nama_barang']) === 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Minimal harus menambahkan 1 item barang belanjaan.'
    ]);
    exit;
}

// Ambil ID utama dari form edit penanda data awal (jika dikirim dari hidden input utama)
$id_master_barang_utama = filter_var($_POST['id_master_barang_utama'] ?? null, FILTER_VALIDATE_INT) ?: 0;

// Mulai transaction agar aman dan rollback jika terjadi kegagalan data di tengah jalan
mysqli_begin_transaction($conn);

try {
    // -----------------------------------------------------------------------------------------
    // 🧹 TAHAP DETEKSI DAN HAPUS OTOMATIS ITEM YANG DIBUANG USER SAAT EDIT
    // -----------------------------------------------------------------------------------------
    $id_aktif_dari_form = [];
    if (isset($_POST['id_master_barang']) && is_array($_POST['id_master_barang'])) {
        foreach ($_POST['id_master_barang'] as $id_val) {
            if (!empty($id_val)) {
                $id_aktif_dari_form[] = (int)$id_val;
            }
        }
    }

    // Jika sedang mode edit (ada ID utama atau nomor SPK terdeteksi), bersihkan data yang sengaja didelete dari form frontend
    if (!empty($id_master_barang_utama) || !empty($id_aktif_dari_form)) {
        // Cari no SPK lama dari database berdasarkan salah satu id aktif/utama untuk pelacakan
        $id_target_cari = !empty($id_master_barang_utama) ? $id_master_barang_utama : $id_aktif_dari_form[0];
        $stmt_spk_lama = mysqli_prepare($conn, "SELECT `no_spk` FROM `master_barang_sekolah` WHERE `id` = ? AND `id_sekolah` = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt_spk_lama, "is", $id_target_cari, $id_sekolah);
        mysqli_stmt_execute($stmt_spk_lama);
        $q_spk_lama = mysqli_stmt_get_result($stmt_spk_lama);
        
        if (mysqli_num_rows($q_spk_lama) > 0) {
            $r_spk_lama = mysqli_fetch_assoc($q_spk_lama);
            $spk_lama_db = $r_spk_lama['no_spk'];

            // Ambil seluruh ID item barang yang saat ini tercatat di database untuk SPK ini
            $stmt_db_items = mysqli_prepare($conn, "SELECT `id` FROM `master_barang_sekolah` WHERE `no_spk` = ? AND `id_sekolah` = ?");
            mysqli_stmt_bind_param($stmt_db_items, "ss", $spk_lama_db, $id_sekolah);
            mysqli_stmt_execute($stmt_db_items);
            $q_db_items = mysqli_stmt_get_result($stmt_db_items);
            $id_di_database = [];
            while ($row_db = mysqli_fetch_assoc($q_db_items)) {
                $id_di_database[] = (int)$row_db['id'];
            }

            // Cari ID mana saja yang ada di DB tetapi sudah hilang di form kiriman user
            $id_yang_dihapus_user = array_diff($id_di_database, $id_aktif_dari_form);

            if (!empty($id_yang_dihapus_user)) {
                $placeholders = implode(',', array_fill(0, count($id_yang_dihapus_user), '?'));
                $types_hapus = str_repeat('i', count($id_yang_dihapus_user)) . 's';
                $params_hapus = [...array_values($id_yang_dihapus_user), $id_sekolah];
                $stmt_hapus = mysqli_prepare($conn, "DELETE FROM `master_barang_sekolah` WHERE `id` IN ($placeholders) AND `id_sekolah` = ?");
                mysqli_stmt_bind_param($stmt_hapus, $types_hapus, ...$params_hapus);
                $q_hapus_item_buangan = mysqli_stmt_execute($stmt_hapus);
                if (!$q_hapus_item_buangan) {
                    throw new Exception("Gagal mensinkronisasikan penghapusan item barang lama.");
                }
            }
        }
    }

    // -----------------------------------------------------------------------------------------
    // 🔄 TAHAP LOOPING UPDATE & INSERT ITEM BARANG
    // -----------------------------------------------------------------------------------------
    foreach ($_POST['nama_barang'] as $index => $val) {
        
        // Ambil ID detail barang berdasarkan index array masing-masing baris form
        $id_barang_tunggal = filter_var($_POST['id_master_barang'][$index] ?? null, FILTER_VALIDATE_INT) ?: 0;

        // Ambil dan bersihkan data dari array berdasarkan index looping saat ini
        $id_uraian        = trim($_POST['id_uraian'][$index] ?? '');
        $kode_barang      = trim($_POST['kode_barang'][$index] ?? '');
        $nama_barang      = trim($_POST['nama_barang'][$index] ?? '');
        $jenis_aset       = trim($_POST['jenis_aset'][$index] ?? '');
        $merk_tipe        = trim($_POST['merk_tipe'][$index] ?? '');
        $no_sertifikat    = trim($_POST['no_sertifikat'][$index] ?? '');
        $ukuran_bangunan  = trim($_POST['ukuran_bangunan'][$index] ?? '');
        $satuan           = trim($_POST['satuan'][$index] ?? '');
        
        // Parsing volume dan harga satuan rupiah dengan aman melalui helper function
        $volume           = parseToFloat($_POST['volume'][$index] ?? 0);
        $harga_satuan     = parseToFloat($_POST['harga_satuan'][$index] ?? 0);
        $nilai_perolehan  = $volume * $harga_satuan;

        // Validasi internal item per baris form
        if (empty($nama_barang) || $volume <= 0 || $harga_satuan <= 0) {
            throw new Exception("Item Nomor " . ($index + 1) . " gagal diproses. Nama barang harus dipilih, Volume & Harga Satuan tidak boleh 0!");
        }

        if (!empty($id_barang_tunggal)) {
            // JIKA ADA ID: Update data baris item barang eksisting (Mendukung perubahan nomor SPK massal)
            $query_eksekusi = "UPDATE `master_barang_sekolah` SET
                `no_sp2d` = ?, `sumber_perolehan` = ?, `no_spk` = ?, `ba_no` = ?,
                `ba_tgl` = NULLIF(?, ''), `id_uraian` = NULLIF(?, ''), `kode_barang` = ?,
                `nama_barang` = ?, `jenis_aset` = ?, `merk_tipe` = ?,
                `no_sertifikat` = ?, `ukuran_bangunan` = ?, `satuan` = ?,
                `volume` = ?, `harga_satuan` = ?, `nilai_perolehan` = ?
                WHERE `id` = ? AND `id_sekolah` = ?";
            $stmt_eksekusi = mysqli_prepare($conn, $query_eksekusi);
            mysqli_stmt_bind_param($stmt_eksekusi, "sssssssssssssdddis", $no_sp2d, $sumber_perolehan, $no_spk, $ba_no, $ba_tgl, $id_uraian, $kode_barang, $nama_barang, $jenis_aset, $merk_tipe, $no_sertifikat, $ukuran_bangunan, $satuan, $volume, $harga_satuan, $nilai_perolehan, $id_barang_tunggal, $id_sekolah);
        } else {
            // JIKA ID KOSONG: Insert data baru (User menambahkan item barang baru di form edit/tambah)
            $query_eksekusi = "INSERT INTO `master_barang_sekolah` (
                `id_sekolah`, `bulan_realisasi`, `no_sp2d`, `sumber_perolehan`, 
                `no_spk`, `ba_no`, `ba_tgl`, `id_uraian`, `kode_barang`, 
                `nama_barang`, `jenis_aset`, `merk_tipe`, `no_sertifikat`, 
                `ukuran_bangunan`, `satuan`, `volume`, `harga_satuan`, `nilai_perolehan`
            ) VALUES (?, ?, ?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_eksekusi = mysqli_prepare($conn, $query_eksekusi);
            mysqli_stmt_bind_param($stmt_eksekusi, "sisssssssssssssddd", $id_sekolah, $bulan_realisasi, $no_sp2d, $sumber_perolehan, $no_spk, $ba_no, $ba_tgl, $id_uraian, $kode_barang, $nama_barang, $jenis_aset, $merk_tipe, $no_sertifikat, $ukuran_bangunan, $satuan, $volume, $harga_satuan, $nilai_perolehan);
        }

        $eksekusi = mysqli_stmt_execute($stmt_eksekusi);
        if (!$eksekusi) {
            throw new Exception("Gagal memproses item pada baris " . ($index + 1) . ".");
        }
    }

    // Jika semua alur proses sukses tanpa rintangan, commit data langsung ke database
    mysqli_commit($conn);

    echo json_encode([
        'status' => 'success',
        'message' => 'Data realisasi SPJ belanja berhasil diperbarui secara penuh!'
    ]);

} catch (Exception $e) {
    // Batalkan seluruh rangkaian perubahan jika di tengah jalan ada yang error (Rollback Aman)
    mysqli_rollback($conn);

    error_log('Simpan katalog barang gagal: ' . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Gagal menyimpan data. Silakan periksa input lalu coba lagi.'
    ]);
}

// Helper Function: Memproses desimal/masking rupiah menjadi float murni
function parseToFloat($value) {
    if (empty($value)) return 0;
    $clean_value = preg_replace('/[^\d.,]/', '', $value);
    if (strpos($clean_value, '.') !== false && strpos($clean_value, ',') !== false) {
        $clean_value = str_replace('.', '', $clean_value);
        $clean_value = str_replace(',', '.', $clean_value);
    } elseif (strpos($clean_value, ',') !== false && strpos($clean_value, '.') === false) {
        if (strlen(substr(strrchr($clean_value, ","), 1)) <= 2) {
            $clean_value = str_replace(',', '.', $clean_value);
        } else {
            $clean_value = str_replace(',', '', $clean_value);
        }
    } elseif (strpos($clean_value, '.') !== false) {
        if (strlen(substr(strrchr($clean_value, "."), 1)) > 2) {
            $clean_value = str_replace('.', '', $clean_value);
        }
    }
    return floatval($clean_value);
}
?>
