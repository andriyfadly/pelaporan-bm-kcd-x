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

include "koneksi.php";

// Ambil data global dari form induk
$id_sekolah       = $_SESSION['id_sekolah'] ?? '';
$bulan_realisasi  = isset($_POST['bulan_realisasi']) ? (int)$_POST['bulan_realisasi'] : (int)date('n');
$no_sp2d          = mysqli_real_escape_string($conn, $_POST['no_sp2d'] ?? '');
$sumber_perolehan = mysqli_real_escape_string($conn, $_POST['sumber_perolehan'] ?? '');
$no_spk           = mysqli_real_escape_string($conn, $_POST['no_spk'] ?? '');
$ba_no            = mysqli_real_escape_string($conn, $_POST['ba_no'] ?? '');
$ba_tgl           = mysqli_real_escape_string($conn, $_POST['ba_tgl'] ?? '');

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
$id_master_barang_utama = isset($_POST['id_master_barang_utama']) ? mysqli_real_escape_string($conn, $_POST['id_master_barang_utama']) : '';

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
        $q_spk_lama = mysqli_query($conn, "SELECT `no_spk` FROM `master_barang_sekolah` WHERE `id` = '$id_target_cari' AND `id_sekolah` = '$id_sekolah' LIMIT 1");
        
        if (mysqli_num_rows($q_spk_lama) > 0) {
            $r_spk_lama = mysqli_fetch_assoc($q_spk_lama);
            $spk_lama_db = mysqli_real_escape_string($conn, $r_spk_lama['no_spk']);

            // Ambil seluruh ID item barang yang saat ini tercatat di database untuk SPK ini
            $q_db_items = mysqli_query($conn, "SELECT `id` FROM `master_barang_sekolah` WHERE `no_spk` = '$spk_lama_db' AND `id_sekolah` = '$id_sekolah'");
            $id_di_database = [];
            while ($row_db = mysqli_fetch_assoc($q_db_items)) {
                $id_di_database[] = (int)$row_db['id'];
            }

            // Cari ID mana saja yang ada di DB tetapi sudah hilang di form kiriman user
            $id_yang_dihapus_user = array_diff($id_di_database, $id_aktif_dari_form);

            if (!empty($id_yang_dihapus_user)) {
                $string_id_hapus = implode(",", $id_yang_dihapus_user);
                $q_hapus_item_buangan = mysqli_query($conn, "DELETE FROM `master_barang_sekolah` WHERE `id` IN ($string_id_hapus) AND `id_sekolah` = '$id_sekolah'");
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
        $id_barang_tunggal = isset($_POST['id_master_barang'][$index]) ? mysqli_real_escape_string($conn, $_POST['id_master_barang'][$index]) : '';

        // Ambil dan bersihkan data dari array berdasarkan index looping saat ini
        $id_uraian        = mysqli_real_escape_string($conn, $_POST['id_uraian'][$index] ?? '');
        $kode_barang      = mysqli_real_escape_string($conn, $_POST['kode_barang'][$index] ?? '');
        $nama_barang      = mysqli_real_escape_string($conn, $_POST['nama_barang'][$index] ?? '');
        $jenis_aset       = mysqli_real_escape_string($conn, $_POST['jenis_aset'][$index] ?? '');
        $merk_tipe        = mysqli_real_escape_string($conn, $_POST['merk_tipe'][$index] ?? '');
        $no_sertifikat    = mysqli_real_escape_string($conn, $_POST['no_sertifikat'][$index] ?? '');
        $ukuran_bangunan  = mysqli_real_escape_string($conn, $_POST['ukuran_bangunan'][$index] ?? '');
        $satuan           = mysqli_real_escape_string($conn, $_POST['satuan'][$index] ?? '');
        
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
                `no_sp2d` = '$no_sp2d',
                `sumber_perolehan` = '$sumber_perolehan',
                `no_spk` = '$no_spk', 
                `ba_no` = '$ba_no',
                `ba_tgl` = " . (!empty($ba_tgl) ? "'$ba_tgl'" : "NULL") . ",
                `id_uraian` = " . (!empty($id_uraian) ? "'$id_uraian'" : "NULL") . ",
                `kode_barang` = '$kode_barang',
                `nama_barang` = '$nama_barang',
                `jenis_aset` = '$jenis_aset',
                `merk_tipe` = '$merk_tipe',
                `no_sertifikat` = '$no_sertifikat',
                `ukuran_bangunan` = '$ukuran_bangunan',
                `satuan` = '$satuan',
                `volume` = '$volume',
                `harga_satuan` = '$harga_satuan',
                `nilai_perolehan` = '$nilai_perolehan'
                WHERE `id` = '$id_barang_tunggal' AND `id_sekolah` = '$id_sekolah'";
        } else {
            // JIKA ID KOSONG: Insert data baru (User menambahkan item barang baru di form edit/tambah)
            $query_eksekusi = "INSERT INTO `master_barang_sekolah` (
                `id_sekolah`, `bulan_realisasi`, `no_sp2d`, `sumber_perolehan`, 
                `no_spk`, `ba_no`, `ba_tgl`, `id_uraian`, `kode_barang`, 
                `nama_barang`, `jenis_aset`, `merk_tipe`, `no_sertifikat`, 
                `ukuran_bangunan`, `satuan`, `volume`, `harga_satuan`, `nilai_perolehan`
            ) VALUES (
                '$id_sekolah', '$bulan_realisasi', '$no_sp2d', '$sumber_perolehan', 
                '$no_spk', '$ba_no', " . (!empty($ba_tgl) ? "'$ba_tgl'" : "NULL") . ", " . (!empty($id_uraian) ? "'$id_uraian'" : "NULL") . ", '$kode_barang', 
                '$nama_barang', '$jenis_aset', '$merk_tipe', '$no_sertifikat', 
                '$ukuran_bangunan', '$satuan', '$volume', '$harga_satuan', '$nilai_perolehan'
            )";
        }

        $eksekusi = mysqli_query($conn, $query_eksekusi);
        if (!$eksekusi) {
            throw new Exception("Gagal memproses item ke database pada baris " . ($index + 1) . ": " . mysqli_error($conn));
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

    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
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