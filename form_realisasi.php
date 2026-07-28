<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'user') {
    echo "<script>window.location.href='login.php';</script>";
    exit;
}

include "koneksi.php";
$id_sekolah = $_SESSION['id_sekolah'] ?? '';

// Tangkap Parameter bulan & kategori dari URL
if (isset($_GET['bulan_realisasi'])) {
    $bulan_aktif = (int)$_GET['bulan_realisasi'];
    $_SESSION['bulan_aktif_spj'] = $bulan_aktif;
} else {
    $bulan_aktif = isset($_SESSION['bulan_aktif_spj']) ? (int)$_SESSION['bulan_aktif_spj'] : (int)date('n');
}

$nama_bulan = [
    1 => "Januari", 2 => "Februari", 3 => "Maret", 4 => "April", 
    5 => "Mei", 6 => "Juni", 7 => "Juli", 8 => "Agustus", 
    9 => "September", 10 => "Oktober", 11 => "November", 12 => "Desember"
];
$bulan_teks = $nama_bulan[$bulan_aktif] ?? date('F');

$kategori_aktif = $_GET['kategori'] ?? 'Peralatan & Mesin';
$no_spk_edit = $_GET['no_spk_edit'] ?? '';

// Blok logika penarikan data lama jika masuk mode EDIT
$js_edit_data = '[]';
if (!empty($no_spk_edit)) {
    $spk_escaped = mysqli_real_escape_string($conn, $no_spk_edit);
    // Cari data murni berdasarkan NO_SPK & ID_SEKOLAH agar tidak bentrok antar instansi/bulan salah urus
    $q_edit = mysqli_query($conn, "SELECT * FROM `master_barang_sekolah` WHERE `no_spk` = '$spk_escaped' AND `id_sekolah` = '$id_sekolah' ORDER BY `id` ASC");
    
    $data_edit_arr = [];
    while ($r = mysqli_fetch_assoc($q_edit)) {
        $data_edit_arr[] = $r;
    }
    if (!empty($data_edit_arr)) {
        $js_edit_data = json_encode($data_edit_arr);
        // Sinkronisasi bulan aktif berdasarkan data tersimpan agar layout tidak lari ke bulan default computer
        $bulan_aktif = (int)$data_edit_arr[0]['bulan_realisasi'];
        $bulan_teks = $nama_bulan[$bulan_aktif] ?? date('F');
    }
}
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght=400;500;600;700;800&display=swap');

#form_realisasi_spj, #form_realisasi_spj * { font-family:'Plus Jakarta Sans',sans-serif !important; }
.card-spj-input { border:1px solid #e2e8f0 !important; box-shadow:0 10px 30px -5px rgba(0,0,0,.05)!important; border-radius:24px !important; background:#fff; margin-top:110px !important; }
.header-floating { position:fixed; top:100px; right:25px; left:300px; z-index:9999; background:rgba(255,255,255,.95); backdrop-filter:blur(10px); padding:15px 20px; border-radius:18px; box-shadow:0 8px 30px rgba(0,0,0,.08); border:1px solid #e2e8f0; }
.section-spj-header { background:#1e3a8a !important; color:#fff !important; font-weight:800; font-size:14px; padding:12px 20px; border-radius:10px; text-transform:uppercase; letter-spacing:.5px; }
.section-spj-header * { color:#fff !important; }
.section-barang-title { background:#0d9488 !important; color:#fff !important; font-weight:800; font-size:13px; padding:10px 16px; border-radius:8px; text-transform:uppercase; }
.form-label-spj { font-size:11px; font-weight:700 !important; color:#000 !important; margin-bottom:5px; text-transform:uppercase; }
.form-control-spj, .form-select-spj { border-radius:10px !important; padding:10px 16px !important; font-size:13px !important; font-weight:500 !important; border:1px solid #bae6fd !important; color:#000 !important; background:#fff !important; }
.form-control-spj:focus, .form-select-spj:focus { border-color:#38bdf8 !important; box-shadow:0 0 0 4px rgba(56,189,248,.15) !important; }
.form-control-readonly { background-color:#f1f5f9 !important; color:#000 !important; font-weight:700 !important; opacity:1 !important; }
.item-barang-row { border:1px solid #bae6fd !important; border-radius:16px !important; margin-bottom:16px !important; overflow:hidden; background:#fff; }
.item-barang-header { padding:14px 20px !important; background:#f0f9ff !important; display:flex; align-items:center; justify-content:space-between; cursor:pointer; border-bottom:1px solid #bae6fd; }
.number-badge-spj { width:26px; height:26px; background:#0284c7; color:#fff !important; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:12px; }
.item-title-text { font-weight:700; color:#1e293b !important; font-size:13.5px; }
.sub-total-badge-spj { font-size:13px; font-weight:800; background:#fff; color:#0369a1 !important; padding:6px 16px; border-radius:8px; border:1px solid #bae6fd; font-family:monospace !important; }
.total-akumulasi-box { background:#f0f9ff; border:2px dashed #38bdf8; border-radius:12px; padding:18px 24px; display:flex; justify-content:space-between; align-items:center; margin-top:20px; }
#grand_total_display { color:#0284c7 !important; font-weight:800 !important; }
.search-results-box-spj { position:absolute; top:76px; left:12px; right:12px; max-height:250px; overflow-y:auto; background:#fff; border:1px solid #38bdf8; border-radius:12px; z-index:1060; display:none; padding:6px; box-shadow:0 10px 25px -5px rgba(0,0,0,.1); }
.search-item-spj { padding:10px 14px; cursor:pointer; border-radius:8px; font-size:12.5px; font-weight:600; border-bottom:1px solid #f1f5f9; color:#000 !important; }
.search-item-spj:hover { background:#f1f5f9; color:#334155 !important; }
.info-bulan-alert { background-color: #fef3c7; border: 1px solid #fde68a; border-radius: 12px; padding: 12px 16px; color: #92400e; font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 10px; }
</style>

<div class="row">
    <div class="col-12">
        <div class="card card-box bg-white card-spj-input p-4">
            
            <div class="border-bottom pb-3 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 header-floating">
                <div>
                    <span class="badge bg-primary px-3 py-2 rounded-3 fw-bold font-monospace" style="background:#1e3a8a !important; font-size:13px;"><i class="bi bi-box-seam"></i> Workspace Form Realisasi Barang</span>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-warning text-dark px-3 py-2" style="border-radius: 12px; font-size: 13.5px; font-weight: 700;" onclick="kembaliKeDaftar()">
                        <i class="bi bi-arrow-left-short fs-5 me-1"></i> Kembali ke Daftar
                    </button>
                </div>
            </div>

            <div class="info-bulan-alert mb-4 shadow-sm">
                <i class="bi bi-calendar-check-fill fs-5 text-warning" style="color: #d97706 !important;"></i>
                <span>Periode Input Data: Anda sedang menginput realisasi barang untuk bulan <strong><?= $bulan_teks; ?></strong>.</span>
            </div>

            <form id="form_realisasi_spj" onsubmit="return false;">
                <input type="hidden" name="is_mode_edit" id="is_mode_edit" value="<?= !empty($no_spk_edit) ? '1' : '0'; ?>">
                <input type="hidden" name="no_spk_lama_key" id="no_spk_lama_key" value="<?= htmlspecialchars($no_spk_edit); ?>">
                <input type="hidden" name="bulan_realisasi" id="bulan_realisasi" value="<?= $bulan_aktif; ?>">
                <input type="hidden" id="kategori_terpilih_input" value="<?= htmlspecialchars($kategori_aktif); ?>">

                <div class="section-spj-header mb-3">
                    <i class="bi bi-file-earmark-text-fill me-2"></i> I. DOKUMEN & ADMINISTRASI KEUANGAN SPJ (<span id="txt_label_kategori" class="text-warning fw-bold"><?= strtoupper($kategori_aktif); ?></span>)
                </div>

                <div class="row g-3 mb-4 p-3 border rounded-3 bg-light bg-opacity-50">
                    <div class="col-12 col-md-4">
                        <label class="form-label-spj">No. SP2D</label>
                        <input type="text" name="no_sp2d" id="no_sp2d" class="form-control form-control-spj fw-bold text-dark" placeholder="Boleh dikosongkan (Opsional)">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label-spj">Sumber Perolehan *</label>
                        <select name="sumber_perolehan" id="sumber_perolehan" class="form-select form-select-spj fw-semibold" required>
                            <option value="BOS Reguler" selected>BOS Reguler</option>
                            <option value="BOS Kinerja">BOS Kinerja</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label-spj">No. SPK / Kwitansi *</label>
                        <input type="text" name="no_spk" id="no_spk" class="form-control form-control-spj font-monospace fw-bold" placeholder="Nomor Kwitansi/Nota Belanja" required>
                    </div>
                    <div class="col-12 col-md-8">
                        <label class="form-label-spj">Nomor Berita Acara Penerimaan (BA No)</label>
                        <input type="text" name="ba_no" id="ba_no" class="form-control form-control-spj" placeholder="Masukkan nomor berita acara...">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label-spj">Tanggal BA (BA Tgl)</label>
                        <input type="date" name="ba_tgl" id="ba_tgl" class="form-control form-control-spj">
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3" id="wrapper_btn_tambah_item">
                    <div class="section-barang-title">
                        <i class="bi bi-box-seam-fill me-2"></i> II & III. DETAIL ITEM BARANG UNTUK SPJ INI
                    </div>
                    <button type="button" class="btn btn-primary btn-sm px-3 py-2 fw-bold text-white shadow-sm" style="background:#0d9488; border-radius:10px; font-size:12px; border:none;" onclick="addBarangRowToSpj()">
                        <i class="bi bi-plus-lg me-1"></i> Tambah Item Barang
                    </button>
                </div>

                <div id="container-items-spj" class="mb-4"></div>

                <div class="total-akumulasi-box">
                    <span class="text-secondary fw-bold small">TOTAL AKUMULASI REALISASI:</span>
                    <h3 class="mb-0 text-primary font-monospace fw-bold" id="grand_total_display">Rp 0</h3>
                </div>

                <div class="d-flex justify-content-end gap-2 pt-4 mt-3 border-top">
                    <button type="button" class="btn btn-light px-4 py-2 border text-secondary fw-bold" style="border-radius:10px; font-size:13px;" onclick="kembaliKeDaftar()">Batal</button>
                    <button type="button" id="btn_simpan_realisasi" class="btn btn-info px-4 py-2 text-white fw-bold" style="border-radius:10px; font-size:13px; background:#0ea5e9; border:none;" onclick="eksekusiSimpanSpj()">Simpan Realisasi</button>
                </div>
            </form>

        </div>
    </div>
</div>

<script>
    var containerItems = document.getElementById('container-items-spj');
    var rawEditData = <?= $js_edit_data; ?>;

    window.addEventListener('DOMContentLoaded', () => {
        if (rawEditData && rawEditData.length > 0) {
            bukaFormEditSpj(rawEditData);
        } else {
            addBarangRowToSpj();
        }
    });

    function kembaliKeDaftar() {
        window.location.href = 'index.php?p=data_barang.php';
    }

    function toggleAccordionContent(id) {
        let content = document.getElementById(`accordion_content_${id}`);
        if(content) content.style.display = (content.style.display === "none") ? "block" : "none";
    }

    function collapseSemuaRow() {
        let openContents = containerItems.querySelectorAll('.p-3.bg-white[id^="accordion_content_"]');
        openContents.forEach(content => { content.style.display = 'none'; });
    }

    function bukaFormEditSpj(arrayDataBarang) {
        containerItems.innerHTML = '';
        let dataInduk = arrayDataBarang[0];
        
        // Pasang Administrasi Induk Dokumen
        document.getElementById('no_sp2d').value = dataInduk.no_sp2d ? dataInduk.no_sp2d : '';
        document.getElementById('sumber_perolehan').value = dataInduk.sumber_perolehan;
        document.getElementById('no_spk').value = dataInduk.no_spk;
        document.getElementById('ba_no').value = dataInduk.ba_no ? dataInduk.ba_no : '';
        document.getElementById('ba_tgl').value = dataInduk.ba_tgl ? dataInduk.ba_tgl : '';
        
        let checkAset = dataInduk.jenis_aset ? dataInduk.jenis_aset.toUpperCase() : '';
        let katEksisting = "Peralatan & Mesin";
        if (checkAset.includes("BUKU") || checkAset.includes("PERPUSTAKAAN")) katEksisting = "Buku";
        
        document.getElementById('kategori_terpilih_input').value = katEksisting;
        document.getElementById('txt_label_kategori').innerText = katEksisting.toUpperCase() + " (MODE EDIT)";

        arrayDataBarang.forEach((dataObj, index) => {
            let inputRowId = 'edit_' + dataObj.id + '_' + index; 
            let block = document.createElement('div');
            block.className = 'item-barang-row custom-item-row-spj';
            block.id = `item_barang_block_${inputRowId}`;

            let volumeBersih = parseFloat(dataObj.volume) || 0;
            let nomorUrut = index + 1;
            let isBuku = katEksisting === "Buku";
            let reqAttr = isBuku ? 'required' : '';
            let labelStar = isBuku ? ' *' : '';

            block.innerHTML = `
                <div class="item-barang-header" onclick="toggleAccordionContent('${inputRowId}')">
                    <div class="d-flex align-items-center gap-3">
                        <div class="number-badge-spj class-index-badge">${nomorUrut}</div>
                        <span class="item-title-text text-uppercase fw-bold text-dark" id="header_title_barang_${inputRowId}">${dataObj.nama_barang}</span>
                    </div>
                    <div class="d-flex align-items-center gap-3" onclick="event.stopPropagation();">
                        <span class="sub-total-badge-spj" id="sub_total_badge_${inputRowId}">Rp 0</span>
                    </div>
                </div>

                <div class="p-3 bg-white" id="accordion_content_${inputRowId}" style="display: ${index === 0 ? 'block' : 'none'};">
                    <input type="hidden" name="id_master_barang_row[]" value="${dataObj.id}">
                    <input type="hidden" name="id_uraian[]" id="id_uraian_${inputRowId}" value="${dataObj.id_uraian ? dataObj.id_uraian : ''}">

                    <div class="row g-2">
                        <div class="col-12 position-relative mb-2">
                            <label class="form-label-spj"><i class="bi bi-search"></i> Cari Nama Barang / Kode Asset dari Katalog Pagu</label>
                            <input type="text" class="form-control form-control-spj fw-semibold" style="border-color:#bae6fd !important;" id="live_search_input_${inputRowId}" value="${dataObj.nama_barang}" placeholder="Ketik nama barang yang dicari..." autocomplete="off" oninput="ajaxLiveSearchKatalog('${inputRowId}')">
                            <div id="search_results_box_${inputRowId}" class="search-results-box-spj"></div>
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label-spj">Kode Barang</label>
                            <input type="text" name="kode_barang[]" id="kode_barang_${inputRowId}" value="${dataObj.kode_barang}" class="form-control form-control-spj form-control-readonly" readonly>
                        </div>
                        <div class="col-12 col-md-8">
                            <label class="form-label-spj">Nama Barang / Uraian</label>
                            <input type="text" name="nama_barang[]" id="nama_barang_${inputRowId}" value="${dataObj.nama_barang}" class="form-control form-control-spj form-control-readonly" readonly>
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label-spj">Jenis Aset</label>
                            <input type="text" name="jenis_aset[]" id="jenis_aset_${inputRowId}" value="${dataObj.jenis_aset}" class="form-control form-control-spj form-control-readonly" readonly>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label-spj">Merk / Tipe</label>
                            <input type="text" name="merk_tipe[]" id="merk_tipe_${inputRowId}" value="${dataObj.merk_tipe ? dataObj.merk_tipe : ''}" class="form-control form-control-spj" placeholder="Contoh: Lenovo Core i3">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label-spj" id="label_sertifikat_${inputRowId}">No. Sertifikat / Pabrik${labelStar}</label>
                            <input type="text" name="no_sertifikat[]" id="no_sertifikat_${inputRowId}" value="${dataObj.no_sertifikat ? dataObj.no_sertifikat : ''}" class="form-control form-control-spj" placeholder="Wajib jika kategori Buku" ${reqAttr}>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label-spj">Ukuran / Dimensi Bangunan</label>
                            <input type="text" name="ukuran_bangunan[]" id="ukuran_bangunan_${inputRowId}" value="${dataObj.ukuran_bangunan ? dataObj.ukuran_bangunan : ''}" class="form-control form-control-spj" placeholder="-">
                        </div>

                        <div class="col-6 col-md-2">
                            <label class="form-label-spj">Satuan *</label>
                            <input type="text" name="satuan[]" id="satuan_${inputRowId}" value="${dataObj.satuan ? dataObj.satuan : ''}" class="form-control form-control-spj" placeholder="Pcs/Unit" required>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label-spj">Volume (QTY) *</label>
                            <input type="number" step="any" name="volume[]" id="volume_${inputRowId}" value="${volumeBersih}" class="form-control form-control-spj text-center fw-bold" oninput="kalkulasiItemBarang('${inputRowId}')" required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label-spj">Harga Satuan (Rp) *</label>
                            <input type="text" id="harga_satuan_mask_${inputRowId}" class="form-control form-control-spj text-end fw-bold" value="${formatRupiahValue(dataObj.harga_satuan)}" oninput="inputHargaRupiahHandler('${inputRowId}')" required>
                            <input type="hidden" name="harga_satuan[]" id="harga_satuan_${inputRowId}" value="${dataObj.harga_satuan}">
                        </div>
                        <input type="hidden" name="nilai_perolehan[]" id="nilai_perolehan_${inputRowId}" value="${dataObj.nilai_perolehan}">
                    </div>
                </div>
            `;
            containerItems.appendChild(block);
            kalkulasiItemBarang(inputRowId);
        });
    }

    function addBarangRowToSpj() {
        collapseSemuaRow();
        let uniqueTimestampId = Date.now();
        let block = document.createElement('div');
        block.className = 'item-barang-row custom-item-row-spj';
        block.id = `item_barang_block_${uniqueTimestampId}`;

        let katAktif = document.getElementById('kategori_terpilih_input').value;
        let isBuku = katAktif === "Buku";
        let reqAttr = isBuku ? 'required' : '';
        let labelStar = isBuku ? ' *' : '';

        block.innerHTML = `
            <div class="item-barang-header" onclick="toggleAccordionContent('${uniqueTimestampId}')">
                <div class="d-flex align-items-center gap-3">
                    <div class="number-badge-spj class-index-badge">0</div>
                    <span class="item-title-text text-uppercase fw-bold text-dark" id="header_title_barang_${uniqueTimestampId}">ITEM BARANG BARU</span>
                </div>
                <div class="d-flex align-items-center gap-3" onclick="event.stopPropagation();">
                    <span class="sub-total-badge-spj" id="sub_total_badge_${uniqueTimestampId}">Rp 0</span>
                    <button type="button" class="btn btn-sm text-danger p-0 border-0" onclick="hapusBarisBarangSpj('${uniqueTimestampId}')">
                        <i class="bi bi-trash3-fill fs-5"></i>
                    </button>
                </div>
            </div>

            <div class="p-3 bg-white" id="accordion_content_${uniqueTimestampId}" style="display: block;">
                <input type="hidden" name="id_master_barang_row[]" value="">
                <input type="hidden" name="id_uraian[]" id="id_uraian_${uniqueTimestampId}" value="">

                <div class="row g-2">
                    <div class="col-12 position-relative mb-2">
                        <label class="form-label-spj"><i class="bi bi-search"></i> Cari Nama Barang / Kode Asset dari Katalog Pagu</label>
                        <input type="text" class="form-control form-control-spj fw-semibold" style="border-color:#38bdf8 !important;" id="live_search_input_${uniqueTimestampId}" placeholder="Ketik nama barang yang dicari..." autocomplete="off" oninput="ajaxLiveSearchKatalog('${uniqueTimestampId}')">
                        <div id="search_results_box_${uniqueTimestampId}" class="search-results-box-spj"></div>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label-spj">Kode Barang (Asset)</label>
                        <input type="text" name="kode_barang[]" id="kode_barang_${uniqueTimestampId}" class="form-control form-control-spj form-control-readonly" readonly placeholder="Otomatis...">
                    </div>
                    <div class="col-12 col-md-8">
                        <label class="form-label-spj">Nama Barang / Uraian</label>
                        <input type="text" name="nama_barang[]" id="nama_barang_${uniqueTimestampId}" class="form-control form-control-spj form-control-readonly" readonly placeholder="Otomatis...">
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label-spj">Jenis Aset</label>
                        <input type="text" name="jenis_aset[]" id="jenis_aset_${uniqueTimestampId}" class="form-control form-control-spj form-control-readonly" readonly placeholder="Otomatis...">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label-spj">Merk / Tipe</label>
                        <input type="text" name="merk_tipe[]" id="merk_tipe_${uniqueTimestampId}" class="form-control form-control-spj" placeholder="Contoh: Lenovo Core i3">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label-spj" id="label_sertifikat_${uniqueTimestampId}">No. Sertifikat / Pabrik${labelStar}</label>
                        <input type="text" name="no_sertifikat[]" id="no_sertifikat_${uniqueTimestampId}" class="form-control form-control-spj" placeholder="Wajib jika kategori Buku" ${reqAttr}>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label-spj">Ukuran / Dimensi Bangunan</label>
                        <input type="text" name="ukuran_bangunan[]" id="ukuran_bangunan_${uniqueTimestampId}" class="form-control form-control-spj" placeholder="-">
                    </div>

                    <div class="col-6 col-md-2">
                        <label class="form-label-spj">Satuan *</label>
                        <input type="text" name="satuan[]" id="satuan_${uniqueTimestampId}" class="form-control form-control-spj" placeholder="Pcs/Unit" required>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label-spj">Volume (QTY) *</label>
                        <input type="number" step="any" name="volume[]" id="volume_${uniqueTimestampId}" class="form-control form-control-spj text-center fw-bold" value="1" oninput="kalkulasiItemBarang('${uniqueTimestampId}')" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label-spj">Harga Satuan (Rp) *</label>
                        <input type="text" id="harga_satuan_mask_${uniqueTimestampId}" class="form-control form-control-spj text-end fw-bold" value="0" oninput="inputHargaRupiahHandler('${uniqueTimestampId}')" required>
                        <input type="hidden" name="harga_satuan[]" id="harga_satuan_${uniqueTimestampId}" value="0">
                    </div>
                    <input type="hidden" name="nilai_perolehan[]" id="nilai_perolehan_${uniqueTimestampId}" value="0">
                </div>
            </div>
        `;
        containerItems.appendChild(block);
        refreshNomorDanKalkulasi();
    }

    function hapusBarisBarangSpj(id) {
        let el = document.getElementById(`item_barang_block_${id}`);
        if(el) { el.remove(); refreshNomorDanKalkulasi(); }
    }

    function formatRupiahValue(angka) { return new Intl.NumberFormat('id-ID').format(angka); }

    function inputHargaRupiahHandler(id) {
        let maskEl = document.getElementById(`harga_satuan_mask_${id}`);
        let hiddenEl = document.getElementById(`harga_satuan_${id}`);
        let cleanValue = maskEl.value.replace(/\D/g, '');
        let numericValue = parseFloat(cleanValue) || 0;
        
        hiddenEl.value = numericValue;
        maskEl.value = formatRupiahValue(numericValue);
        kalkulasiItemBarang(id);
    }

    function ajaxLiveSearchKatalog(id) {
        let kw = document.getElementById(`live_search_input_${id}`).value;
        let box = document.getElementById('search_results_box_' + id);
        if (kw.length < 2) { box.style.display = 'none'; return; }

        fetch(`ajax_cari_barang.php?q=${encodeURIComponent(kw)}`)
        .then(res => res.json())
        .then(data => {
            box.innerHTML = '';
            if(data.length > 0) {
                box.style.display = 'block';
                data.forEach(item => {
                    let d = document.createElement('div');
                    d.className = 'search-item-spj';
                    let autoDetectedAset = item.jenis_aset ? item.jenis_aset : "Persediaan";
                    
                    d.innerHTML = `<i class="bi bi-tag-fill text-secondary me-2"></i><strong>[${item.kode_barang}]</strong> ${item.nama_barang} <span class="badge bg-secondary ms-2" style="font-size:10px; background:#64748b !important;">${autoDetectedAset}</span>`;
                    
                    d.onclick = function() {
                        document.getElementById(`id_uraian_${id}`).value = item.id || '';
                        document.getElementById(`kode_barang_${id}`).value = item.kode_barang || '';
                        document.getElementById(`nama_barang_${id}`).value = item.nama_barang || '';
                        document.getElementById(`jenis_aset_${id}`).value = autoDetectedAset;
                        document.getElementById(`header_title_barang_${id}`).innerText = item.nama_barang;
                        document.getElementById(`live_search_input_${id}`).value = item.nama_barang;
                        box.style.display = 'none';
                        kalkulasiItemBarang(id);
                    };
                    box.appendChild(d);
                });
            } else {
                box.innerHTML = `<div class="p-3 text-muted text-center small">Barang tidak ditemukan.</div>`;
                box.style.display = 'block';
            }
        });
    }

    function kalkulasiItemBarang(id) {
        let vol = parseFloat(document.getElementById(`volume_${id}`).value) || 0;
        let harga = parseFloat(document.getElementById(`harga_satuan_${id}`).value) || 0;
        let total = vol * harga;
        document.getElementById(`nilai_perolehan_${id}`).value = total;
        document.getElementById(`sub_total_badge_${id}`).innerText = "Rp " + formatRupiahValue(total);
        hitungGrandTotalSpj();
    }

    function refreshNomorDanKalkulasi() {
        let rows = containerItems.querySelectorAll('.custom-item-row-spj');
        rows.forEach((row, index) => { row.querySelector('.class-index-badge').innerText = index + 1; });
        hitungGrandTotalSpj();
    }

    function hitungGrandTotalSpj() {
        let totalInputs = containerItems.querySelectorAll('input[id^="nilai_perolehan_"]');
        let grandTotal = 0;
        totalInputs.forEach(input => { grandTotal += parseFloat(input.value) || 0; });
        document.getElementById('grand_total_display').innerText = "Rp " + formatRupiahValue(grandTotal);
    }

    function eksekusiSimpanSpj() {
        let form = document.getElementById('form_realisasi_spj');
        if (!form.checkValidity()) { 
            form.reportValidity(); 
            alert("Harap periksa kembali kolom bertanda (*) yang wajib diisi.");
            return; 
        }

        let btnSimpan = document.getElementById('btn_simpan_realisasi');
        btnSimpan.disabled = true;
        btnSimpan.innerText = "Menyimpan Dokumen...";

        let formData = new FormData(form);
        fetch("simpan_katalog_barang.php", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.status === "success") {
                alert("Data SPJ Berhasil Disimpan!");
                window.location.href = 'index.php?p=data_barang.php';
            } else {
                alert(data.message);
                btnSimpan.disabled = false; btnSimpan.innerText = "Simpan Realisasi";
            }
        }).catch(() => { alert("Error sistem."); btnSimpan.disabled = false; btnSimpan.innerText = "Simpan Realisasi"; });
    }
</script>