<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $judul }} — {{ $sekolah?->nama_sekolah }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #0f172a; margin: 20px 24px; }
        .kop { text-align: center; border-bottom: 2px solid #1e3a8a; padding-bottom: 8px; margin-bottom: 14px; }
        .kop h1 { font-size: 13px; margin: 0; text-transform: uppercase; }
        .kop h2 { font-size: 11px; margin: 1px 0 0; font-weight: normal; }
        .kop p { font-size: 9px; margin: 2px 0 0; color: #475569; }
        h3.judul { text-align: center; font-size: 12px; text-transform: uppercase; margin: 10px 0 4px; }
        .periode { text-align: center; font-size: 10px; color: #475569; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cbd5e1; padding: 4px 5px; vertical-align: top; }
        th { background: #eff6ff; font-size: 9px; text-transform: uppercase; }
        td.num { text-align: right; }
        td.mid { text-align: center; }
        .ttd { margin-top: 28px; width: 100%; }
        .ttd td { border: 0; text-align: center; font-size: 10px; padding-top: 6px; }
        .kosong { color: #94a3b8; }
    </style>
</head>
<body>
@php
    $pejabatKiri = $pejabat['kepala_sekolah'] ?? null;
    $pejabatKanan = $pejabat['pembantu_pengurus_barang'] ?? ($pejabat['wakasek_sarpras'] ?? null);
@endphp

<div class="kop">
    <h1>Pemerintah Provinsi Jawa Barat</h1>
    <h2>Dinas Pendidikan</h2>
    <h1 style="font-size:12px;">{{ $sekolah?->nama_sekolah ?? '-' }}</h1>
    <p>
        {{ $sekolah?->alamat ?? '' }}
        @if($sekolah?->telepon) · Tlp: {{ $sekolah->telepon }} @endif
        @if($sekolah?->npsn) · NPSN: {{ $sekolah->npsn }} @endif
    </p>
</div>

<h3 class="judul">{{ $judul }}</h3>
<div class="periode">
    @if(isset($ruang)) Ruang: <strong>{{ $ruang->nama_ruang }}</strong> · @endif
    @if(isset($tahun)) Tahun: <strong>{{ $tahun }}</strong> · @endif
    Dicetak: {{ now()->translatedFormat('d F Y') }}
</div>

<table>
    <thead>
    <tr>
        <th style="width:26px;">No</th>
        <th style="width:46px;">Register</th>
        <th style="width:90px;">Kode Barang</th>
        <th>Nama Barang</th>
        <th style="width:80px;">Merk/Tipe</th>
        <th style="width:50px;">Kondisi</th>
        <th style="width:70px;">Tgl Perolehan</th>
        <th style="width:80px;">Harga Satuan</th>
        <th style="width:80px;">Nilai</th>
        <th style="width:90px;">Ruang</th>
        <th style="width:90px;">Penanggung Jawab</th>
    </tr>
    </thead>
    <tbody>
    @forelse($units as $i => $unit)
        <tr>
            <td class="mid">{{ $i + 1 }}</td>
            <td class="mid">{{ $unit->nomor_register }}</td>
            <td>{{ $unit->kode_barang }}</td>
            <td>{{ $unit->nama_barang ?? '-' }}</td>
            <td>{{ $unit->merk_tipe ?? '-' }}</td>
            <td class="mid">{{ $unit->kondisi }}</td>
            <td class="mid">{{ $unit->tanggal_perolehan?->format('d/m/Y') ?? '-' }}</td>
            <td class="num">Rp {{ number_format((float) $unit->harga_satuan, 0, ',', '.') }}</td>
            <td class="num">Rp {{ number_format((float) $unit->nilai_perolehan_unit, 0, ',', '.') }}</td>
            <td>{{ $unit->ruang?->nama_ruang ?? '-' }}</td>
            <td>{{ $unit->pegawai?->nama_lengkap ?? '-' }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="11" class="mid kosong">Tidak ada unit pada kriteria ini.</td>
        </tr>
    @endforelse
    </tbody>
</table>

<table class="ttd">
    <tr>
        <td style="width:50%;">
            Mengetahui,<br>{{ $pejabatKiri['jabatan'] ?? ($labelJabatan['kepala_sekolah'] ?? '-') }}
            <br><br><br><br>
            <strong>{{ $pejabatKiri['nama'] ?? '...........................' }}</strong><br>
            NIP {{ $pejabatKiri['nip'] ?? '...................' }}
        </td>
        <td style="width:50%;">
            {{ $sekolah?->kota_kab ?? '' }}, {{ now()->translatedFormat('d F Y') }}<br>
            {{ $pejabatKanan['jabatan'] ?? ($labelJabatan['pembantu_pengurus_barang'] ?? '-') }}
            <br><br><br><br>
            <strong>{{ $pejabatKanan['nama'] ?? '...........................' }}</strong><br>
            NIP {{ $pejabatKanan['nip'] ?? '...................' }}
        </td>
    </tr>
</table>
</body>
</html>
