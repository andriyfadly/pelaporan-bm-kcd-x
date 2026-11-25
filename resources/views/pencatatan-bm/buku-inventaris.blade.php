<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $judul }} — {{ $sekolah->nama_sekolah }}</title>
    <style>
        @page { size: 330mm 215mm; margin: 5mm; }
        body { font-family: 'Times New Roman', 'Times', serif; font-size: 7.5px; color: #000; margin: 0; }
        table { border-collapse: collapse; }
        .judul { text-align: center; line-height: 1.2; }
        .judul .b1 { font-size: 11pt; font-weight: bold; }
        .judul .b2 { font-size: 11pt; font-weight: bold; }
        .judul .b3 { font-size: 10pt; font-weight: bold; }
        .meta { width: 100%; font-size: 8pt; margin-top: 4px; }
        .meta td { border: 0; }
        table.main { width: 100%; margin-top: 4px; }
        table.main th, table.main td { border: 1px solid #000; padding: 1px 2px; vertical-align: middle; }
        table.main th { text-align: center; font-weight: bold; font-size: 7pt; line-height: 1.15; }
        .mid { text-align: center; }
        .num { text-align: right; }
        .kosong { text-align: center; padding: 6px; }
        table.ttd { width: 100%; text-align: center; font-size: 9pt; margin-top: 8px; }
        table.ttd td { border: 0; vertical-align: top; }
        .ttd-ruang { height: 18mm; }
    </style>
</head>
<body>
@php
    $kepala = $pejabat['kepala_sekolah'] ?? null;

    // Satu baris per kelompok barang; register digabung jadi rentang.
    $baris = $units
        ->groupBy(fn ($unit): string => implode('|', [
            $unit->kode_barang,
            $unit->nama_barang,
            $unit->merk_tipe,
            $unit->no_sertifikat,
            $unit->bahan,
            $unit->asal_perolehan,
            $unit->bulan_perolehan,
            $unit->tahun_perolehan,
            $unit->ukuran_bangunan,
            $unit->satuan,
            $unit->harga_satuan,
            $unit->keterangan,
        ]))
        ->map(fn ($kelompok) => [
            'unit' => $kelompok->first(),
            'reg' => \App\Support\RentangRegister::dari($kelompok->pluck('nomor_register')->all()),
            'B' => $kelompok->where('kondisi', 'B')->count(),
            'RR' => $kelompok->where('kondisi', 'RR')->count(),
            'RB' => $kelompok->where('kondisi', 'RB')->count(),
            'vol' => $kelompok->count(),
        ])
        ->values();
@endphp

<div class="judul">
    <div class="b1">BUKU INVENTARIS</div>
    <div class="b2">PERALATAN DAN MESIN</div>
    <div class="b3">{{ mb_strtoupper($sekolah->nama_sekolah) }}</div>
</div>

<table class="meta">
    <tr>
        <td style="width: 60%;">KAB/KOTA : {{ mb_strtoupper((string) $sekolah->kota_kab) }}</td>
        <td style="width: 40%; text-align: right; font-weight: bold;">NO. KODE LOKASI : ................</td>
    </tr>
    <tr>
        <td colspan="2">PROVINSI : JAWA BARAT</td>
    </tr>
</table>

<table class="main">
    <thead>
    <tr>
        <th rowspan="2" style="width: 8mm;">NO</th>
        <th rowspan="2" style="width: 26mm;">KODE BARANG</th>
        <th rowspan="2" style="width: 15mm;">REG</th>
        <th colspan="3" style="width: 101mm;">SPESIFIKASI</th>
        <th rowspan="2" style="width: 15mm;">BAHAN</th>
        <th rowspan="2" style="width: 18mm;">ASAL PEROLEHAN</th>
        <th rowspan="2" style="width: 10mm;">BLN</th>
        <th rowspan="2" style="width: 10mm;">THN</th>
        <th rowspan="2" style="width: 14mm;">UKURAN</th>
        <th rowspan="2" style="width: 13mm;">SATUAN</th>
        <th colspan="3">KEADAAN</th>
        <th colspan="2">JUMLAH</th>
        <th rowspan="2" style="width: 30mm;">KETERANGAN</th>
    </tr>
    <tr>
        <th style="width: 38mm;">NAMA BARANG</th>
        <th style="width: 38mm;">MERK/TYPE</th>
        <th style="width: 25mm;">NO SERI</th>
        <th style="width: 8mm;">B</th>
        <th style="width: 8mm;">RR</th>
        <th style="width: 8mm;">RB</th>
        <th style="width: 10mm;">Vol</th>
        <th style="width: 26mm;">HARGA (Rp)</th>
    </tr>
    </thead>
    <tbody>
    @forelse($baris as $i => $item)
        @php($unit = $item['unit'])
        <tr>
            <td class="mid">{{ $i + 1 }}</td>
            <td class="mid" style="font-size: 6.5px;">{{ $unit->kode_barang }}</td>
            <td class="mid">{{ $item['reg'] }}</td>
            <td>{{ $unit->nama_barang ?? '-' }}</td>
            <td>{{ $unit->merk_tipe ?? '-' }}</td>
            <td class="mid">{{ $unit->no_sertifikat ?: '-' }}</td>
            <td class="mid">{{ $unit->bahan ?: '-' }}</td>
            <td>{{ $unit->asal_perolehan ?: '-' }}</td>
            <td class="mid">{{ $unit->bulan_perolehan ?? '-' }}</td>
            <td class="mid">{{ $unit->tahun_perolehan }}</td>
            <td class="mid">{{ $unit->ukuran_bangunan ?: '-' }}</td>
            <td class="mid">{{ $unit->satuan ?: '-' }}</td>
            <td class="mid">{{ $item['B'] ?: '' }}</td>
            <td class="mid">{{ $item['RR'] ?: '' }}</td>
            <td class="mid">{{ $item['RB'] ?: '' }}</td>
            <td class="mid">{{ $item['vol'] }}</td>
            <td class="num">{{ number_format((float) $unit->harga_satuan, 0, ',', '.') }}</td>
            <td>{{ $unit->keterangan ?: '' }}</td>
        </tr>
    @empty
        <tr><td colspan="18" class="kosong">Data barang tidak ditemukan.</td></tr>
    @endforelse
    </tbody>
</table>

<table class="ttd">
    <tr>
        <td style="width: 55%;"></td>
        <td style="width: 45%;">
            {{ $sekolah->kota_kab }}, {{ $tanggal->translatedFormat('d F Y') }}<br>
            Mengetahui,<br>
            Kepala Sekolah
            <div class="ttd-ruang"></div>
            <b style="text-decoration: underline;">{{ $kepala['nama'] ?? '...........................' }}</b><br>
            NIP. {{ ($kepala['nip'] ?? null) ?: '...................' }}
        </td>
    </tr>
</table>
</body>
</html>
