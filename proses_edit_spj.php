<?php
// Pastikan tidak ada karakter, space, atau echo sebelum tag php ini demi menjaga kemurnian JSON
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set header agar browser/JS tahu ini adalah JSON murni
header('Content-Type: application/json; charset=utf-8');

include "koneksi.php";
require_once __DIR__ . '/report_lock.php';

// Array penampung respon awal
$response = [
    'status' => 'error',
    'message' => 'Terjadi kesalahan sistem internal.',
    'bulan_realisasi' => 0
];

if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'user') {
    $response['message'] = 'Sesi login Anda telah habis. Silakan login kembali.';
    echo json_encode($response);
    exit;
}

// Validasi request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Metode request tidak valid.';
    echo json_encode($response);
    exit;
}

$csrf_token = $_POST['csrf_token'] ?? '';
if (empty($csrf_token) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf_token)) {
    $response['message'] = 'Token keamanan tidak valid.';
    echo json_encode($response);
    exit;
}

// 1. Tangkap Parameter Utama Kontrol
$id_uraian       = trim($_POST['id_uraian'] ?? '');
$kodering        = trim($_POST['kodering_belanja'] ?? '');
$bulan_realisasi = isset($_POST['bulan_realisasi']) ? (int)$_POST['bulan_realisasi'] : 0;
$no_sp2d         = trim($_POST['no_sp2ds'] ?? '');
$sumber_dana     = trim($_POST['sumber_perolehan'] ?? '');
$no_spk          = trim($_POST['no_spk'] ?? '');
$ba_no           = trim($_POST['ba_noba'] ?? '');
$ba_tgl          = trim($_POST['ba_tgl'] ?? '');
$id_sekolah      = $_SESSION['id_sekolah'] ?? '';

// Tangkap penanda kunci unik SPK lama dari form interface workspace
$old_spk_key     = trim($_POST['old_spk_key'] ?? '');

$response['bulan_realisasi'] = $bulan_realisasi;

if (empty($id_uraian) || $bulan_realisasi === 0 || empty($no_spk)) {
    $response['message'] = 'Parameter kunci update (ID Uraian / Bulan / No. SPK) tidak boleh kosong.';
    echo json_encode($response);
    exit;
}

assert_report_unlocked($conn, (string)$id_sekolah, $bulan_realisasi);

// 2. Tangkap Array Multi-Item Barang
$arr_kode_barang     = $_POST['kode_barang'] ?? [];
$arr_nama_barang     = $_POST['nama_barang'] ?? [];
$arr_jenis_aset      = $_POST['jenis_aset'] ?? [];
$arr_merk_tipe       = $_POST['merk_tipe'] ?? [];
$arr_no_sertifikat   = $_POST['no_sertifikat'] ?? [];
$arr_ukuran_bangunan = $_POST['ukuran_bangunan'] ?? [];
$arr_satuan          = $_POST['satuan'] ?? [];
$arr_volume          = $_POST['volume'] ?? [];
$arr_harga_satuan    = $_POST['harga_satuan'] ?? [];

if (empty($arr_nama_barang) || count($arr_nama_barang) === 0) {
    $response['message'] = 'Gagal: Minimal harus ada 1 item barang yang diinput.';
    echo json_encode($response);
    exit;
}

// 3. MULAI TRANSAKSI DATABASE 
mysqli_begin_transaction($conn);

try {
    // ⚙️ TENTUKAN TARGET PEMBERSIHAN DATA (Agar multi-SPK di kuitansi lain dalam bulan yang sama aman)
    $target_delete_spk = !empty($old_spk_key) ? $old_spk_key : $no_spk;

    // LANGKAH A: Hapus hanya data realisasi lama yang memiliki kesamaan No SPK/Kuitansi ini saja
    $delete_query = "DELETE FROM `realisasi_barang_sekolah`
                     WHERE `id_uraian` = ? AND `bulan_realisasi` = ?
                     AND `id_sekolah` = ? AND `no_spk` = ?";
    $stmt_delete = mysqli_prepare($conn, $delete_query);
    mysqli_stmt_bind_param($stmt_delete, "siss", $id_uraian, $bulan_realisasi, $id_sekolah, $target_delete_spk);

    if (!mysqli_stmt_execute($stmt_delete)) {
        throw new Exception("Gagal sinkronisasi: Pembersihan rekam data SPK lama ditolak database.");
    }

    // LANGKAH B: Re-insert data baru hasil editing dari form
    for ($i = 0; $i < count($arr_nama_barang); $i++) {
        $kode_barang   = trim($arr_kode_barang[$i]);
        $nama_barang   = trim($arr_nama_barang[$i]);
        $jenis_aset    = trim($arr_jenis_aset[$i]);
        $merk          = trim($arr_merk_tipe[$i]);
        $no_sertif     = trim($arr_no_sertifikat[$i]);
        $ukuran        = trim($arr_ukuran_bangunan[$i]);
        $satuan        = trim($arr_satuan[$i]);
        $volume        = (float)$arr_volume[$i];
        $harga_satuan  = (float)$arr_harga_satuan[$i];
        
        // Hitung nilai perolehan baris
        $nilai_perolehan = $volume * $harga_satuan;

        // Skip jika baris data esensial kosong
        if (empty($nama_barang) || $volume <= 0) {
            continue;
        }

        // Eksekusi insert data item kuitansi (Memastikan field jenis_aset terisi presisi)
        $insert_query = "INSERT INTO `realisasi_barang_sekolah` (
            `id_sekolah`, `id_uraian`, `kodering_belanja`, `bulan_realisasi`, 
            `no_sp2d`, `sumber_perolehan`, `no_spk`, `ba_no`, `ba_tgl`, 
            `kode_barang`, `nama_barang`, `jenis_aset`, `merk_tipe`, 
            `no_sertifikat`, `ukuran_bangunan`, `satuan`, `volume`, 
            `harga_satuan`, `nilai_perolehan`
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_insert = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param(
            $stmt_insert,
            "sssissssssssssssddd",
            $id_sekolah,
            $id_uraian,
            $kodering,
            $bulan_realisasi,
            $no_sp2d,
            $sumber_dana,
            $no_spk,
            $ba_no,
            $ba_tgl,
            $kode_barang,
            $nama_barang,
            $jenis_aset,
            $merk,
            $no_sertif,
            $ukuran,
            $satuan,
            $volume,
            $harga_satuan,
            $nilai_perolehan
        );

        if (!mysqli_stmt_execute($stmt_insert)) {
            throw new Exception("Gagal menyimpan data rincian komponen: " . $nama_barang);
        }
    }

    // Jika semua proses aman, kunci perubahan ke database
    mysqli_commit($conn);

    $response['status'] = 'success';
    $response['message'] = 'Data SPJ Dokumen Kuitansi berhasil disinkronkan!';
    
} catch (Exception $e) {
    // Jika ada yang error ditengah jalan, batalkan semua perubahan kembali ke semula
    mysqli_rollback($conn);
    error_log('Edit SPJ gagal: ' . $e->getMessage());
    $response['status'] = 'error';
    $response['message'] = 'Gagal menyimpan perubahan. Silakan coba lagi.';
}

// Cetak respon dalam format JSON bersih
echo json_encode($response);
exit;
