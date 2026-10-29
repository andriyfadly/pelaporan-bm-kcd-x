<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $judul }} {{ $dokumen->nomor ?? '' }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #0f172a; margin: 22px 30px; line-height: 1.55; }
        .kop { text-align: center; border-bottom: 2px solid #1e3a8a; padding-bottom: 8px; margin-bottom: 16px; }
        .kop h1 { font-size: 13px; margin: 0; text-transform: uppercase; }
        .kop h2 { font-size: 11px; margin: 1px 0 0; font-weight: normal; }
        .kop p { font-size: 9px; margin: 2px 0 0; color: #475569; }
        h3.judul { text-align: center; font-size: 13px; text-transform: uppercase; text-decoration: underline; margin: 12px 0 2px; }
        .nomor { text-align: center; font-size: 11px; margin-bottom: 16px; }
        table.data { width: 100%; border-collapse: collapse; margin: 10px 0; }
        table.data th, table.data td { border: 1px solid #cbd5e1; padding: 4px 6px; font-size: 10px; vertical-align: top; }
        table.data th { background: #eff6ff; text-align: left; width: 34%; }
        .ttd { margin-top: 30px; width: 100%; }
        .ttd td { border: 0; text-align: center; font-size: 11px; padding-top: 8px; vertical-align: top; }
    </style>
</head>
<body>
@php
    $payload = $dokumen->payload ?? [];
    $sekolahPayload = $payload['sekolah'] ?? [];
    $unit = $payload['unit'] ?? null;
    $pegawai = $payload['pegawai'] ?? null;
    $mutasi = $payload['mutasi'] ?? null;
    $pejabat = $payload['pejabat'] ?? [];
    $pejabatKiri = $pejabat['kepala_sekolah'] ?? null;
    $pejabatKanan = $pejabat['pembantu_pengurus_barang'] ?? ($pejabat['wakasek_sarpras'] ?? null);
    $tanggal = $dokumen->tanggal ? $dokumen->tanggal->translatedFormat('d F Y') : now()->translatedFormat('d F Y');
@endphp

<div class="kop">
    <h1>Pemerintah Provinsi Jawa Barat</h1>
    <h2>Dinas Pendidikan</h2>
    <h1 style="font-size:12px;">{{ $sekolahPayload['nama_sekolah'] ?? '-' }}</h1>
    <p>
        {{ $sekolahPayload['alamat'] ?? '' }}
        @if(!empty($sekolahPayload['telepon'])) · Tlp: {{ $sekolahPayload['telepon'] }} @endif
        @if(!empty($sekolahPayload['npsn'])) · NPSN: {{ $sekolahPayload['npsn'] }} @endif
    </p>
</div>

<h3 class="judul">{{ $judul }}</h3>
<div class="nomor">{{ $dokumen->nomor ? 'Nomor: '.$dokumen->nomor : '' }}</div>

@if($dokumen->jenis === 'bast')
    <p>Pada hari ini, {{ $tanggal }}, telah dilaksanakan serah terima inventaris sebagai berikut:</p>
    <table class="data">
        <tr><th>Register</th><td>{{ $unit['nomor_register'] ?? '-' }}</td></tr>
        <tr><th>Kode Barang</th><td>{{ $unit['kode_barang'] ?? '-' }}</td></tr>
        <tr><th>Nama Barang</th><td>{{ $unit['nama_barang'] ?? '-' }}</td></tr>
        <tr><th>Merk / Tipe</th><td>{{ $unit['merk_tipe'] ?? '-' }}</td></tr>
        <tr><th>Asal Perolehan</th><td>{{ $unit['asal_perolehan'] ?? '-' }}</td></tr>
        <tr><th>Kondisi</th><td>{{ $unit['kondisi'] ?? '-' }}</td></tr>
        <tr><th>Ruang</th><td>{{ $unit['ruang'] ?? '-' }}</td></tr>
        <tr><th>Penanggung Jawab</th><td>{{ $unit['pegawai'] ?? '-' }}</td></tr>
        <tr><th>Nilai Perolehan</th><td>Rp {{ number_format((float) ($unit['nilai_perolehan_unit'] ?? 0), 0, ',', '.') }}</td></tr>
        <tr><th>No. SPK / BA</th><td>{{ $unit['realisasi']['no_spk'] ?? '-' }} / {{ $unit['realisasi']['ba_no'] ?? '-' }}</td></tr>
    </table>
@elseif($dokumen->jenis === 'fakta_integritas')
    <p>Yang bertanda tangan di bawah ini menyatakan bertanggung jawab atas inventaris berikut:</p>
    <table class="data">
        <tr><th>Nama</th><td>{{ $pegawai['nama_lengkap'] ?? '-' }}</td></tr>
        <tr><th>NIP</th><td>{{ $pegawai['nip'] ?? '-' }}</td></tr>
        <tr><th>Jabatan</th><td>{{ $pegawai['jabatan'] ?? '-' }}</td></tr>
    </table>
    <table class="data">
        <thead>
        <tr><th style="width:30px;">No</th><th>Register</th><th>Nama Barang</th><th>Kondisi</th></tr>
        </thead>
        <tbody>
        @foreach(($payload['units'] ?? []) as $i => $item)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $item['nomor_register'] }}</td>
                <td>{{ $item['nama_barang'] }}</td>
                <td>{{ $item['kondisi'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@elseif($dokumen->jenis === 'surat_pengembalian')
    <p>Dengan ini kami mengajukan pengembalian inventaris berikut:</p>
    <table class="data">
        <tr><th>Register</th><td>{{ $unit['nomor_register'] ?? '-' }}</td></tr>
        <tr><th>Nama Barang</th><td>{{ $unit['nama_barang'] ?? '-' }}</td></tr>
        <tr><th>Tanggal Pengembalian</th><td>{{ $mutasi['tanggal'] ?? '-' }}</td></tr>
        <tr><th>Ruang Asal</th><td>{{ $mutasi['asal_ruang'] ?? '-' }}</td></tr>
        <tr><th>Penanggung Jawab Asal</th><td>{{ $mutasi['asal_pegawai'] ?? '-' }}</td></tr>
        <tr><th>Kondisi Awal</th><td>{{ $mutasi['asal_kondisi'] ?? '-' }}</td></tr>
        <tr><th>Kondisi Akhir</th><td>{{ $mutasi['tujuan_kondisi'] ?? '-' }}</td></tr>
        <tr><th>Keterangan</th><td>{{ $mutasi['keterangan'] ?? '-' }}</td></tr>
    </table>
@else
    <p>{{ $payload['keterangan'] ?? 'Dokumen inventaris.' }}</p>
@endif

<table class="ttd">
    <tr>
        <td style="width:50%;">
            Mengetahui,<br>{{ $pejabatKiri['jabatan'] ?? ($labelJabatan['kepala_sekolah'] ?? '-') }}
            <br><br><br><br>
            <strong>{{ $pejabatKiri['nama'] ?? '...........................' }}</strong><br>
            NIP {{ $pejabatKiri['nip'] ?? '...................' }}
        </td>
        <td style="width:50%;">
            {{ $sekolahPayload['kota_kab'] ?? '' }}, {{ $tanggal }}<br>
            {{ $pegawai ? 'Yang menyatakan' : ($pejabatKanan['jabatan'] ?? ($labelJabatan['pembantu_pengurus_barang'] ?? '-')) }}
            <br><br><br><br>
            <strong>{{ $pegawai['nama_lengkap'] ?? ($pejabatKanan['nama'] ?? '...........................') }}</strong><br>
            NIP {{ $pegawai['nip'] ?? ($pejabatKanan['nip'] ?? '...................') }}
        </td>
    </tr>
</table>
</body>
</html>
