<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validasi Proteksi: Jika belum login ATAU role-nya bukan user sekolah
if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit;
}

include "koneksi.php";

// Tangkap parameter dari URL acuan belanja modal
$id_uraian        = isset($_GET['id_uraian']) ? mysqli_real_escape_string($conn, $_GET['id_uraian']) : '';
$kodering         = isset($_GET['kodering']) ? htmlspecialchars($_GET['kodering']) : '';
$sisa_awal        = isset($_GET['sisa']) ? (float)$_GET['sisa'] : 0;
$bulan_realisasi  = isset($_GET['bulan_realisasi']) ? (int)$_GET['bulan_realisasi'] : 0;

// Cari judul acuan utama (Uraian Belanja) berdasarkan id_uraian
$nama_uraian_kegiatan = "Uraian Belanja Tidak Diketahui";
if (!empty($id_uraian)) {
    $q_judul = mysqli_query($conn, "SELECT `uraian` FROM `data_barang_acuan` WHERE `id` = '$id_uraian' LIMIT 1");
    if ($q_judul && mysqli_num_rows($q_judul) > 0) {
        $r_judul = mysqli_fetch_assoc($q_judul);
        $nama_uraian_kegiatan = $r_judul['uraian'];
    }
}
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
    .badge-kodering { 
        background: #f0f9ff; 
        color: #0369a1; 
        font-weight: 700; 
        padding: 6px 14px; 
        border-radius: 10px; 
        border: 1px solid #e0f2fe; 
        font-size: 13px; 
    }
    .badge-sisa { 
        background: #f0fdf4; 
        color: #166534; 
        font-weight: 700; 
        padding: 6px 14px; 
        border-radius: 10px; 
        border: 1px solid #dcfce7; 
        font-size: 13px; 
    }
    .badge-sisa.minus { 
        background: #fef2f2 !important; 
        color: #991b1b !important; 
        border-color: #fecaca !important; 
    }
    .section-block-modern { 
        background: #ffffff; 
        border: 1px solid #e0f2fe; 
        border-radius: 18px; 
        padding: 24px; 
        margin-bottom: 24px; 
    }
    .section-title-modern { 
        font-size: 12px; 
        font-weight: 800; 
        color: #0284c7; 
        text-transform: uppercase; 
        letter-spacing: 0.8px; 
        display: flex; 
        align-items: center; 
        gap: 8px; 
    }
    .form-label-spj { 
        font-size: 13px; 
        font-weight: 700 !important; 
        color: #1e293b; 
        margin-bottom: 6px; 
    }
    .form-control-spj, .form-select-spj { 
        border-radius: 12px !important; 
        padding: 10px 16px !important; 
        font-size: 13.5px !important; 
        font-weight: 500 !important; 
        border: 1px solid #bae6fd !important; 
        color: #0f172a !important; 
    }
    .form-select-spj, .form-control-spj, .search-input, .input-harga-mask, .input-total-mask { 
        text-transform: none !important; 
    }
    .form-control-spj:focus, .form-select-spj:focus { 
        border-color: #0ea5e9 !important; 
        box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.12) !important; 
    }
    .search-results-box-modern { 
        position: absolute; 
        top: 76px; 
        left: 0; 
        right: 0; 
        max-height: 250px; 
        overflow-y: auto; 
        background: #ffffff; 
        border: 1px solid #bae6fd; 
        border-radius: 14px; 
        z-index: 1060; 
        display: none; 
        padding: 6px; 
        box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
    }
    .search-item-modern { 
        padding: 12px 16px; 
        cursor: pointer; 
        border-radius: 10px; 
        font-size: 13.5px; 
        font-weight: 600; 
    }
    .search-item-modern:hover { 
        background: #f0f9ff; 
    }
    .sub-text-keterangan {
        font-size: 11px;
        color: #64748b;
        font-weight: 500;
        margin-top: 2px;
    }
    .item-row-modern { 
        border: 1px solid #e0f2fe !important; 
        border-radius: 20px !important; 
        margin-bottom: 14px !important; 
        overflow: hidden; 
    }
    .item-header-modern { 
        padding: 16px 24px !important; 
        background: #f0f9ff !important; 
        display: flex; 
        align-items: center; 
        justify-content: space-between; 
        cursor: pointer; 
    }
    .number-badge-modern { 
        width: 28px; 
        height: 28px; 
        background: #0284c7; 
        color: #ffffff; 
        border-radius: 9px; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        font-weight: 700; 
    }
    .sub-total-badge-modern { 
        font-size: 13.5px; 
        font-weight: 700; 
        background: #ffffff; 
        color: #0369a1; 
        padding: 6px 14px; 
        border-radius: 10px; 
        border: 1px solid #bae6fd; 
    }
    .btn-add-item-top { 
        border: 1px solid #bae6fd !important; 
        background: #f0f9ff !important; 
        color: #0284c7 !important; 
        border-radius: 12px !important; 
        font-size: 13px !important; 
        font-weight: 700; 
        padding: 10px 18px !important; 
    }
    .grand-total-box { 
        background: #f8fafc; 
        border: 2px dashed #bae6fd; 
        border-radius: 20px; 
        padding: 24px 28px; 
    }
    .header-floating{
        position: fixed;
        top: 100px;
        right: 25px;
        left: 300px; 
        z-index: 9999;
        background: rgba(255,255,255,0.95);
        backdrop-filter: blur(10px);
        padding: 15px 20px;
        border-radius: 18px;
        box-shadow: 0 8px 30px rgba(0,0,0,.12);
        border: 1px solid #e2e8f0;
    }
    .card-spj{
        margin-top: 110px !important;
    }
</style>

<div class="row">
    <div class="col-12">
        <div class="card card-box bg-white card-spj p-4">
            
            <div class="border-bottom pb-3 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 header-floating">
                <div>
                    <h4 class="text-dark mb-2" style="font-weight: 800; color: #0f172a;">
                        <?= htmlspecialchars($nama_uraian_kegiatan); ?>
                    </h4>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <span class="badge-kodering font-monospace"><i class="bi bi-hash"></i><?= htmlspecialchars($kodering); ?></span>
                        <span class="badge-sisa font-monospace" id="sisa_anggaran_display">Sisa Anggaran: Rp <?= number_format($sisa_awal, 0, ',', '.'); ?></span>
                        <div id="draft-alert" class="badge text-white px-2 py-1" style="display:none; background-color: #0ea5e9; font-size: 11px; font-weight: 700;">
                            <i class="bi bi-check-circle-fill"></i> Draft Pulih
                        </div>
                    </div>
                </div>
                <button type="button" class="btn btn-white border px-3 py-2" style="border-radius: 12px; font-size: 13.5px; font-weight: 700;" onclick="clearDraftAndBack()">
                    <i class="bi bi-arrow-left me-1"></i> Kembali
                </button>
            </div>

            <form id="form_realisasi" action="simpan_realisasi.php" method="POST" onsubmit="return false;">
                <input type="hidden" id="payload_id_uraian" value="<?= htmlspecialchars($id_uraian); ?>">
                <input type="hidden" id="payload_kodering" value="<?= htmlspecialchars($kodering); ?>">
                <input type="hidden" id="payload_bulan" value="<?= $bulan_realisasi; ?>">

                <!-- SECTION I -->
                <div class="section-block-modern">
                    <div class="section-title-modern mb-3">
                        <i class="bi bi-file-earmark-text fs-6"></i> I. Dokumen & Administrasi Keuangan
                    </div>
                    <div class="row g-3">
                        <div class="col-12 col-md-3">
                            <label class="form-label-spj">No. SP2D</label>
                            <input type="text" class="form-control form-control-spj storage-save font-monospace" id="no_sp2ds" placeholder="Masukkan nomor SP2D">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label-spj">Sumber Perolehan</label>
                            <select class="form-select form-select-spj storage-save" id="sumber_perolehan" required>
                                <option value="" disabled selected>Pilih Sumber Perolehan</option>
                                <option value="BOS Reguler">BOS Reguler</option>
                                <option value="BOS Kinerja">BOS Kinerja</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label-spj">No. SPK / Kuitansi</label>
                            <input type="text" class="form-control form-control-spj storage-save font-monospace" id="no_spk" placeholder="Masukkan nomor SPK atau kuitansi" required>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label-spj">Kodering Belanja</label>
                            <input type="text" class="form-control form-control-spj bg-light text-muted font-monospace" value="<?= htmlspecialchars($kodering); ?>" readonly style="border-color:#e2e8f0!important;">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label-spj">Nomor Berita Acara Penerimaan (BA No)</label>
                            <input type="text" class="form-control form-control-spj storage-save font-monospace" id="ba_noba" placeholder="Contoh: 002/BA-PST/2026" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label-spj">Tanggal BA (BA Tgl)</label>
                            <input type="date" class="form-control form-control-spj storage-save" id="ba_tgl" required>
                        </div>
                    </div>
                </div>

                <!-- SECTION II & III -->
                <div class="d-flex justify-content-between align-items-center mx-1 mb-3">
                    <div class="section-title-modern">
                        <i class="bi bi-box-seam fs-6"></i> II & III. Detail Spesifikasi & Nilai Barang
                    </div>
                    <button type="button" class="btn btn-add-item-top shadow-sm" onclick="addBarangRow()">
                        <i class="bi bi-plus-lg me-1"></i> Tambah Item Barang
                    </button>
                </div>
                
                <div id="container-barang" class="accordion mb-3"></div>

                <!-- GRAND TOTAL -->
                <div class="grand-total-box mb-4 shadow-sm">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span style="font-size: 13px; font-weight: 800; color: #0369a1; letter-spacing: 0.5px;">TOTAL AKUMULASI REALISASI:</span>
                        <h2 class="font-monospace mb-0" id="grand_total_display" style="font-weight: 900; color: #0284c7; font-size: 2rem;">Rp 0</h2>
                    </div>
                </div>

                <!-- FOOTER BUTTONS -->
                <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                    <button type="button" class="btn btn-white px-4 py-2 border text-secondary" style="border-radius: 12px; font-size: 13.5px; font-weight: 700; background:#fff;" onclick="clearDraftAndBack()">Batal</button>
                    <button type="button" id="btn_simpan_realisasi" class="btn btn-primary px-4 py-2 shadow-sm" style="border-radius: 12px; font-size: 13.5px; background: #0ea5e9; font-weight: 700; border:none;" onclick="eksekusiSimpanSPJ()">Simpan Realisasi</button>
                </div>
            </form>

        </div>
    </div>
</div>

<!-- MODAL SUCCESS -->
<div class="modal fade" id="modalSuksesSPJ" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-body text-center p-4">
                <div class="d-inline-flex align-items-center justify-content-center bg-success bg-opacity-10 text-success rounded-circle mb-3" style="width: 72px; height: 72px;">
                    <i class="bi bi-check-circle-fill" style="font-size: 36px; color: #10b981 !important;"></i>
                </div>
                <h5 class="text-dark mb-2" style="font-weight: 800;">Penyimpanan Berhasil!</h5>
                <p class="text-secondary small px-2 mb-4" id="modal_success_message">Data kuitansi realisasi barang berhasil dikunci ke ID baris anggaran terkait.</p>
                <button type="button" class="btn btn-success w-100 py-2" id="btnModalMengerti" style="border-radius: 12px; font-weight: 700; background: #10b981; border: none;">Mengerti & Kembali</button>
            </div>
        </div>
    </div>
</div>

<script>
    var rowCounter = 0; 
    var containerBarang = document.getElementById('container-barang');
    var storageKey = 'spj_draft_<?= md5($id_uraian . $kodering . $bulan_realisasi); ?>';
    var sisaAwalAnggaran = <?= $sisa_awal; ?>;
    var globalTotalAkumulasi = 0; 

    if(typeof window.initFormSPJRunned === 'undefined'){
        window.initFormSPJRunned = true;
    }

    setTimeout(function() {
        loadDraft();
        document.querySelectorAll('.storage-save').forEach(input => {
            input.addEventListener('input', saveDraft);
        });
    }, 200);

    function formatRupiah(angka) {
        return new Intl.NumberFormat('id-ID').format(angka);
    }

    function hitungGrandTotal() {
        let total = 0;
        document.querySelectorAll('.input-harga-asli').forEach(input => {
            let id = input.id.replace('harga_satuan_', '');
            let vol = parseFloat(document.getElementById(`volume_${id}`).value) || 0;
            let harga = parseFloat(input.value) || 0;
            total += (vol * harga);
        });

        globalTotalAkumulasi = total;
        document.getElementById('grand_total_display').innerText = 'Rp ' + formatRupiah(total);

        let displaySisa = document.getElementById('sisa_anggaran_display');
        let sisaAkhir = sisaAwalAnggaran - total;
        if(sisaAkhir < 0) {
            displaySisa.className = "badge-sisa font-monospace minus";
            displaySisa.innerText = `Over Budget: Rp ${formatRupiah(Math.abs(sisaAkhir))}`;
        } else {
            displaySisa.className = "badge-sisa font-monospace";
            displaySisa.innerText = `Sisa Anggaran: Rp ${formatRupiah(sisaAkhir)}`;
        }
    }

    function addBarangRow(savedData = null) {
        let existingRows = containerBarang.querySelectorAll('.accordion-collapse');
        existingRows.forEach(row => {
            let bsCollapse = bootstrap.Collapse.getInstance(row);
            if (!bsCollapse) bsCollapse = new bootstrap.Collapse(row, { toggle: false });
            bsCollapse.hide();
        });

        rowCounter++;
        let internalId = rowCounter; 

        let block = document.createElement('div');
        block.className = 'item-row-modern accordion-item';
        block.id = `barang_row_${internalId}`;
        block.setAttribute('data-internal-id', internalId);

        block.innerHTML = `
            <div class="item-header-modern" data-bs-toggle="collapse" data-bs-target="#collapse_body_${internalId}">
                <div class="d-flex align-items-center gap-2">
                    <span class="number-badge-modern">0</span>
                    <strong class="text-dark header-title text-uppercase" id="header_title_${internalId}" style="font-size:13.5px; font-weight:700;">Item Baru</strong>
                </div>
                <div class="d-flex align-items-center gap-3" onclick="event.stopPropagation();">
                    <span class="sub-total-badge-modern font-monospace" id="badge_total_${internalId}">Rp 0</span>
                    <button type="button" class="btn btn-sm btn-link text-danger p-0 border-0 bg-transparent" onclick="removeBarangRow(${internalId})">
                        <i class="bi bi-trash3-fill fs-6" style="color:#ef4444!important;"></i>
                    </button>
                </div>
            </div>

            <div id="collapse_body_${internalId}" class="accordion-collapse collapse show" data-bs-parent="#container-barang">
                <div class="accordion-body p-4 bg-white">
                    
                    <div class="row mb-3">
                        <div class="col-12 position-relative">
                            <label class="form-label-spj text-info" style="color:#0284c7!important;"><i class="bi bi-search me-1"></i> Cari Nama Buku / Kode Buku</label>
                            <input type="text" class="form-control form-control-spj border-info border-opacity-50 search-input" id="search_barang_${internalId}" placeholder="Ketik judul buku atau kode barang..." autocomplete="off">
                            <div id="box_hasil_cari_${internalId}" class="search-results-box-modern"></div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-3">
                            <label class="form-label-spj">Kode Barang</label>
                            <input type="text" class="form-control form-control-spj bg-light font-monospace text-dark" id="kode_barang_${internalId}" readonly required>
                        </div>
                        <div class="col-12 col-md-5">
                            <label class="form-label-spj">Nama Barang / Judul Buku</label>
                            <input type="text" class="form-control form-control-spj bg-light text-dark" id="nama_barang_${internalId}" readonly required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label-spj">Jenis Aset</label>
                            <input type="text" class="form-control form-control-spj bg-light text-dark" id="jenis_aset_${internalId}" readonly required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label-spj">Merk / Type / Spesifikasi</label>
                            <input type="text" class="form-control form-control-spj storage-item-save" id="merk_tipe_${internalId}" placeholder="Contoh: Cetakan 1, Berwarna, Lux">
                        </div>
                        <div class="col-12 col-md-4">
                            <!-- Label UI: Penerbit Buku, ID Input internal: penerbit_${internalId} -->
                            <label class="form-label-spj text-danger">Penerbit Buku *wajib</label>
                            <input type="text" class="form-control form-control-spj storage-item-save font-monospace" id="penerbit_${internalId}" placeholder="Masukkan nama penerbit buku" required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label-spj">Ukuran / Halaman</label>
                            <input type="text" class="form-control form-control-spj storage-item-save font-monospace" id="ukuran_bangunan_${internalId}" placeholder="Contoh: A4, 250 Halaman">
                        </div>
                    </div>

                    <div class="row g-3 border-top pt-3 mt-2">
                        <div class="col-12 col-md-3">
                            <label class="form-label-spj">Satuan</label>
                            <input type="text" class="form-control form-control-spj storage-item-save" id="satuan_${internalId}" placeholder="Contoh: Eks, Paket, Jilid" required>
                        </div>
                        <div class="col-12 col-md-2">
                            <label class="form-label-spj">QTY</label>
                            <input type="number" class="form-control form-control-spj text-center input-volume font-monospace storage-item-save" id="volume_${internalId}" min="1" value="1" required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label-spj">Harga Satuan</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light font-monospace py-1 px-3 text-sm" style="border-radius: 12px 0 0 12px; border-color:#bae6fd; font-weight:700;">Rp</span>
                                <input type="text" class="form-control form-control-spj text-end input-harga-mask font-monospace" id="harga_satuan_mask_${internalId}" placeholder="0" required style="border-radius: 0 12px 12px 0; border-left: none;">
                            </div>
                            <input type="hidden" class="input-harga-asli" id="harga_satuan_${internalId}" value="0">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label-spj" style="color:#0ea5e9!important;">Total Perolehan</label>
                            <div class="input-group">
                                <span class="input-group-text bg-info bg-opacity-10 text-info font-monospace py-1 px-3 text-sm" style="border-radius: 12px 0 0 12px; border-color: rgba(14, 165, 233, 0.2); font-weight:700;">Rp</span>
                                <input type="text" class="form-control form-control-spj text-end bg-light text-info input-total-mask font-monospace" id="nilai_perolehan_mask_${internalId}" readonly value="0" style="border-radius: 0 12px 12px 0; border-left: none; border-color: rgba(14, 165, 233, 0.2)!important;">
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        `;

        containerBarang.appendChild(block);
        initRowEvents(internalId, block);
        reorderBadges(); 

        if (savedData) {
            document.getElementById(`search_barang_${internalId}`).value   = savedData.search_val || '';
            document.getElementById(`kode_barang_${internalId}`).value     = savedData.kode_barang || '';
            document.getElementById(`nama_barang_${internalId}`).value     = savedData.nama_barang || '';
            document.getElementById(`jenis_aset_${internalId}`).value      = savedData.jenis_aset || '';
            document.getElementById(`merk_tipe_${internalId}`).value       = savedData.merk_tipe || '';
            document.getElementById(`penerbit_${internalId}`).value        = savedData.penerbit || '';
            document.getElementById(`ukuran_bangunan_${internalId}`).value = savedData.ukuran_bangunan || '';
            document.getElementById(`satuan_${internalId}`).value          = savedData.satuan || '';
            document.getElementById(`volume_${internalId}`).value          = savedData.volume || 1;
            document.getElementById(`harga_satuan_${internalId}`).value    = savedData.harga_satuan || 0;
            document.getElementById(`harga_satuan_mask_${internalId}`).value = formatRupiah(savedData.harga_satuan || 0);
            
            if(savedData.nama_barang) {
                document.getElementById(`header_title_${internalId}`).innerText = savedData.nama_barang;
            }
            let total = (parseFloat(savedData.volume) || 1) * (parseFloat(savedData.harga_satuan) || 0);
            document.getElementById(`nilai_perolehan_mask_${internalId}`).value = formatRupiah(total);
            document.getElementById(`badge_total_${internalId}`).innerText = 'Rp ' + formatRupiah(total);
            document.getElementById(`collapse_body_${internalId}`).classList.remove('show');
        }
    }

    function removeBarangRow(internalId) {
        let row = document.getElementById(`barang_row_${internalId}`);
        if (row) { 
            row.remove(); 
            reorderBadges(); 
            hitungGrandTotal(); 
            saveDraft(); 
        }
    }

    function reorderBadges() {
        let currentRows = containerBarang.querySelectorAll('.item-row-modern');
        currentRows.forEach((row, index) => {
            row.querySelector('.number-badge-modern').innerText = index + 1;
            let removeBtn = row.querySelector('.btn-link');
            if (removeBtn) {
                removeBtn.style.display = (currentRows.length === 1) ? 'none' : 'block';
            }
        });
    }

    function initRowEvents(internalId, block) {
        const inpSearch = document.getElementById(`search_barang_${internalId}`);
        const boxHasil  = document.getElementById(`box_hasil_cari_${internalId}`);
        const fKode     = document.getElementById(`kode_barang_${internalId}`);
        const fNama     = document.getElementById(`nama_barang_${internalId}`);
        const fJenis    = document.getElementById(`jenis_aset_${internalId}`);
        const inpVol    = document.getElementById(`volume_${internalId}`);
        const hgMask    = document.getElementById(`harga_satuan_mask_${internalId}`);
        const hgAsli    = document.getElementById(`harga_satuan_${internalId}`);
        const totMask   = document.getElementById(`nilai_perolehan_mask_${internalId}`);
        const hTitle    = document.getElementById(`header_title_${internalId}`);
        const bTotal    = document.getElementById(`badge_total_${internalId}`);

        function kalkulasiBaris() {
            let vol = parseFloat(inpVol.value) || 0;
            let hg  = parseFloat(hgAsli.value) || 0;
            let total = vol * hg;
            totMask.value = formatRupiah(total);
            bTotal.innerText = 'Rp ' + formatRupiah(total);
            hitungGrandTotal();
            saveDraft();
        }

        inpSearch.addEventListener('input', function() {
            let keyword = this.value.trim();
            if (keyword.length < 2) { 
                boxHasil.innerHTML = ''; 
                boxHasil.style.display = 'none'; 
                return; 
            }

            fetch(`ajax_cari_barang.php?q=${encodeURIComponent(keyword)}`)
                .then(response => {
                    if (!response.ok) throw new Error('HTTP ' + response.status);
                    return response.json();
                })
                .then(data => {
                    boxHasil.innerHTML = '';
                    if (data.length > 0) {
                        data.forEach(item => {
                            let div = document.createElement('div');
                            div.className = 'search-item-modern';
                            div.innerHTML = `
                                <div>
                                    <span style="color:#0369a1; font-family:monospace;">${item.kode_barang}</span>
                                    <div class="sub-text-keterangan">${item.nama_barang} • <span class="badge bg-secondary text-white px-1" style="font-size:9px;">${item.jenis_aset}</span></div>
                                </div>
                            `;
                            div.addEventListener('click', function() {
                                fKode.value = item.kode_barang;
                                fNama.value = item.nama_barang;
                                fJenis.value = item.jenis_aset;
                                inpSearch.value = item.nama_barang;
                                hTitle.innerText = item.nama_barang;
                                boxHasil.style.display = 'none';
                                saveDraft();
                            });
                            boxHasil.appendChild(div);
                        });
                        boxHasil.style.display = 'block';
                    }
                })
                .catch(() => {
                    boxHasil.innerHTML = '';
                    boxHasil.style.display = 'none';
                    alert('Gagal memuat daftar barang. Periksa koneksi atau coba beberapa saat lagi.');
                });
        });

        document.addEventListener('click', function(e) {
            if (e.target !== inpSearch && e.target !== boxHasil) {
                boxHasil.style.display = 'none';
            }
        });

        hgMask.addEventListener('input', function() {
            let nilaiBersih = this.value.replace(/[^0-9]/g, '');
            let nominal = parseFloat(nilaiBersih) || 0;
            hgAsli.value = nominal;
            this.value = formatRupiah(nominal);
            kalkulasiBaris();
        });

        inpVol.addEventListener('input', function() {
            if(parseInt(this.value) < 1 || this.value === '') this.value = 1;
            kalkulasiBaris();
        });

        block.querySelectorAll('.storage-item-save').forEach(input => {
            input.addEventListener('input', saveDraft);
        });
    }

    function saveDraft() {
        let items = [];
        document.querySelectorAll('.item-row-modern').forEach(row => {
            let id = row.getAttribute('data-internal-id');
            items.push({
                search_val: document.getElementById(`search_barang_${id}`).value,
                kode_barang: document.getElementById(`kode_barang_${id}`).value,
                nama_barang: document.getElementById(`nama_barang_${id}`).value,
                jenis_aset: document.getElementById(`jenis_aset_${id}`).value,
                merk_tipe: document.getElementById(`merk_tipe_${id}`).value,
                penerbit: document.getElementById(`penerbit_${id}`).value,
                ukuran_bangunan: document.getElementById(`ukuran_bangunan_${id}`).value,
                satuan: document.getElementById(`satuan_${id}`).value,
                volume: document.getElementById(`volume_${id}`).value,
                harga_satuan: document.getElementById(`harga_satuan_${id}`).value
            });
        });

        let draft = {
            no_sp2ds: document.getElementById('no_sp2ds').value,
            sumber_perolehan: document.getElementById('sumber_perolehan').value,
            no_spk: document.getElementById('no_spk').value,
            ba_noba: document.getElementById('ba_noba').value,
            ba_tgl: document.getElementById('ba_tgl').value,
            items: items
        };

        localStorage.setItem(storageKey, JSON.stringify(draft));
    }

    function loadDraft() {
        let raw = localStorage.getItem(storageKey);
        if (!raw) {
            addBarangRow();
            return;
        }

        try {
            let draft = JSON.parse(raw);
            document.getElementById('no_sp2ds').value = draft.no_sp2ds || '';
            document.getElementById('sumber_perolehan').value = draft.sumber_perolehan || '';
            document.getElementById('no_spk').value = draft.no_spk || '';
            document.getElementById('ba_noba').value = draft.ba_noba || '';
            document.getElementById('ba_tgl').value = draft.ba_tgl || '';

            if (draft.items && draft.items.length > 0) {
                draft.items.forEach(item => {
                    addBarangRow(item);
                });
            } else {
                addBarangRow();
            }

            document.getElementById('draft-alert').style.display = 'inline-block';
            hitungGrandTotal();
        } catch (e) {
            addBarangRow();
        }
    }

    function clearDraftAndBack() {
        if(confirm("Apakah Anda yakin ingin membatalkan? Seluruh draft isian kuitansi ini akan dihapus.")) {
            localStorage.removeItem(storageKey);
            if (typeof loadPage === 'function') {
                loadPage(`input_realisasi.php?bulan_realisasi=<?= $bulan_realisasi; ?>`, 'Target Acuan Kerja');
            } else {
                window.location.href = `index.php#input_realisasi.php?bulan_realisasi=<?= $bulan_realisasi; ?>`;
            }
        }
    }

    function eksekusiSimpanSPJ() {
        const btnSubmit = document.getElementById('btn_simpan_realisasi');
        
        if (globalTotalAkumulasi > sisaAwalAnggaran) {
            let selisih = globalTotalAkumulasi - sisaAwalAnggaran;
            alert(`Penyimpanan Ditolak!\n\nNilai total realisasi belanja (Rp ${formatRupiah(globalTotalAkumulasi)}) melebihi Sisa Anggaran yang tersedia (Rp ${formatRupiah(sisaAwalAnggaran)}).\nAnda over-budget sebesar: Rp ${formatRupiah(selisih)}.\n\nMohon sesuaikan kembali volume atau harga satuan barang.`);
            return;
        }

        const fSumber = document.getElementById('sumber_perolehan').value;
        const fNoSpk = document.getElementById('no_spk').value;
        const fBaNo = document.getElementById('ba_noba').value;
        const fBaTgl = document.getElementById('ba_tgl').value;

        if(!fSumber || !fNoSpk || !fBaNo || !fBaTgl) {
            alert("Gagal: Mohon lengkapi semua dokumen administrasi keuangan yang bertanda wajib!");
            return;
        }

        let currentRows = containerBarang.querySelectorAll('.item-row-modern');
        if(currentRows.length === 0) {
            alert("Gagal: Anda belum memasukkan satu pun rincian item buku!");
            return;
        }

        let validationPass = true;
        let formData = new FormData();
        
        formData.append('id_uraian', document.getElementById('payload_id_uraian').value);
        formData.append('kodering_belanja', document.getElementById('payload_kodering').value);
        formData.append('bulan_realisasi', document.getElementById('payload_bulan').value);
        formData.append('no_sp2ds', document.getElementById('no_sp2ds').value);
        formData.append('sumber_perolehan', fSumber);
        formData.append('no_spk', fNoSpk);
        formData.append('ba_noba', fBaNo);
        formData.append('ba_tgl', fBaTgl);

        currentRows.forEach(row => {
            let id = row.getAttribute('data-internal-id');
            let kode = document.getElementById(`kode_barang_${id}`).value;
            let nama = document.getElementById(`nama_barang_${id}`).value;
            let satuan = document.getElementById(`satuan_${id}`).value;
            let vol = document.getElementById(`volume_${id}`).value;
            let harga = document.getElementById(`harga_satuan_${id}`).value;
            let penerbit = document.getElementById(`penerbit_${id}`).value;

            if(!kode || !nama || !satuan || !vol || !harga || !penerbit) {
                validationPass = false;
            }

            formData.append('kode_barang[]', kode);
            formData.append('nama_barang[]', nama);
            formData.append('jenis_aset[]', document.getElementById(`jenis_aset_${id}`).value);
            formData.append('merk_tipe[]', document.getElementById(`merk_tipe_${id}`).value);
            
            // DISINI PERUBAHANNYA: Data Penerbit Buku dikirim sebagai array 'no_sertifikat[]'
            formData.append('no_sertifikat[]', penerbit);
            
            formData.append('ukuran_bangunan[]', document.getElementById(`ukuran_bangunan_${id}`).value);
            formData.append('satuan[]', satuan);
            formData.append('volume[]', vol);
            formData.append('harga_satuan[]', harga);
        });

        if(!validationPass) {
            alert("Gagal: Pastikan Anda telah memilih data buku dari kolom pencarian, mengisi Penerbit Buku, serta volume & harga satuan!");
            return;
        }

        btnSubmit.disabled = true;
        btnSubmit.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...`;

        fetch('simpan_realisasi.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if(!response.ok) throw new Error("Server Melempar Error Status " + response.status);
            return response.json();
        })
        .then(res => {
            if (res.status === 'success') {
                localStorage.removeItem(storageKey); 
                sessionStorage.clear();

                document.getElementById('modal_success_message').innerText = res.message;
                let modalElement = document.getElementById('modalSuksesSPJ');
                let bsModal = new bootstrap.Modal(modalElement);
                bsModal.show();

                document.getElementById('btnModalMengerti').onclick = function() {
                    bsModal.hide();
                    let backdrop = document.querySelector('.modal-backdrop');
                    if(backdrop) backdrop.remove();
                    
                    let targetBulan = res.bulan_realisasi || <?= $bulan_realisasi; ?>;
                    if (typeof loadPage === 'function') {
                        loadPage(`input_realisasi.php?bulan_realisasi=${targetBulan}`, 'Target Acuan Kerja');
                    } else {
                        window.location.href = `index.php#input_realisasi.php?bulan_realisasi=${targetBulan}`;
                    }
                };
            } else {
                alert("Gagal Memproses Data: " + res.message);
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = 'Simpan Realisasi';
            }
        })
        .catch(error => {
            console.error("Error Detail:", error);
            alert("Gagal Interseptor Server: Silakan klik ulang tombol Simpan.");
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = 'Simpan Realisasi';
        });
    }
</script>