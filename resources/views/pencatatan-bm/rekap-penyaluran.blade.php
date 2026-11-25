<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $judul }}</title>
    <style>
        @page { size: 330mm 215mm; margin: 5mm; }
        body { font-family: 'Times New Roman', 'Times', serif; font-size: 7pt; color: #000; margin: 0; }
        .judul { text-align: center; line-height: 1.2; font-weight: bold; font-size: 10pt; }
        table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        th, td { border: 1px solid #000; padding: 1px 2px; vertical-align: middle; line-height: 1.15; }
        th { text-align: center; font-weight: bold; font-size: 6.5pt; }
        .mid { text-align: center; }
        .kosong { text-align: center; padding: 6px; }
    </style>
</head>
<body>
<div class="judul">
    <div>REKAP PENYALURAN BELANJA MODAL</div>
    <div>PERALATAN DAN MESIN</div>
    <div>{{ mb_strtoupper($sekolah->nama_sekolah) }}</div>
</div>

<table>
    <thead>
    <tr>
        <th rowspan="2" style="width: 8mm;">NO</th>
        <th rowspan="2" style="width: 35mm;">NAMA BARANG</th>
        <th rowspan="2" style="width: 25mm;">MERK/TIPE</th>
        <th rowspan="2" style="width: 28mm;">KODE BARANG</th>
        <th colspan="5">PENGADAAN</th>
        <th colspan="3">PENYALURAN</th>
        <th rowspan="2" style="width: 15mm;">NO. REG</th>
        <th rowspan="2" style="width: 45mm;">KETERANGAN</th>
    </tr>
    <tr>
        <th style="width: 10mm;">TGL</th>
        <th style="width: 8mm;">BLN</th>
        <th style="width: 8mm;">THN</th>
        <th style="width: 8mm;">Vol</th>
        <th style="width: 15mm;">HARGA SATUAN</th>
        <th style="width: 27mm;">LOKASI</th>
        <th style="width: 8mm;">Vol</th>
        <th style="width: 27mm;">NAMA PENERIMA</th>
    </tr>
    </thead>
    <tbody>
    @forelse($rincian as $i => $item)
        <tr>
            <td class="mid">{{ $i + 1 }}</td>
            <td>{{ $item['nama_barang'] ?? '-' }}</td>
            <td>{{ $item['merk_tipe'] ?? '-' }}</td>
            <td class="mid">{{ $item['kode_barang'] }}</td>
            <td class="mid">{{ $item['tanggal_perolehan'] ?? '-' }}</td>
            <td class="mid">{{ $item['bulan_perolehan'] ?? '-' }}</td>
            <td class="mid">{{ $item['tahun_perolehan'] }}</td>
            <td class="mid">{{ $item['volume'] }}</td>
            <td style="text-align: right;">Rp. {{ number_format($item['harga_satuan'], 0, ',', '.') }}</td>
            <td class="mid">{{ $item['disalurkan'] ? ($item['lokasi'] ?: '-') : '-' }}</td>
            <td class="mid">{{ $item['volume'] }}</td>
            <td>{{ $item['disalurkan'] ? ($item['penerima'] ?: '-') : '-' }}</td>
            <td class="mid">{{ $item['nomor_register'] }}</td>
            <td>{{ $item['disalurkan'] ? ($item['keterangan'] ?: '') : 'Belum Disalurkan / Dikembalikan' }}</td>
        </tr>
    @empty
        <tr><td colspan="14" class="kosong">Data barang tidak ditemukan.</td></tr>
    @endforelse
    </tbody>
</table>
</body>
</html>
