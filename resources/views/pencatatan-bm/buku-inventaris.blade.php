<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $judul }} {{ $tahun }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #0f172a; margin: 18px 20px; }
        .kop { text-align: center; border-bottom: 2px solid #1e3a8a; padding-bottom: 8px; margin-bottom: 12px; }
        .kop h1 { font-size: 13px; margin: 0; text-transform: uppercase; }
        .kop h2 { font-size: 10px; margin: 1px 0 0; font-weight: normal; }
        h3.judul { text-align: center; font-size: 12px; text-transform: uppercase; margin: 8px 0 2px; }
        .periode { text-align: center; color: #475569; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cbd5e1; padding: 3px 4px; }
        th { background: #eff6ff; font-size: 8px; text-transform: uppercase; }
        td.num { text-align: right; }
        td.mid { text-align: center; }
        tfoot td { background: #f8fafc; font-weight: bold; }
        .ttd { margin-top: 24px; width: 100%; }
        .ttd td { border: 0; text-align: center; padding-top: 6px; }
    </style>
</head>
<body>
@php
    $pejabatKiri = $pejabat['kepala_sekolah'] ?? null;
    $pejabatKanan = $pejabat['pembantu_pengurus_barang'] ?? ($pejabat['wakasek_sarpras'] ?? null);
    $totalNilai = $units->sum(fn ($unit) => (float) $unit->nilai_perolehan_unit);
@endphp

<div class="kop">
    <h1>Pemerintah Provinsi Jawa Barat</h1>
    <h2>Dinas Pendidikan</h2>
    <h1 style="font-size:12px;">{{ $sekolah->nama_sekolah }}</h1>
    <p style="font-size:8px;margin:2px 0 0;color:#475569;">
        {{ $sekolah->alamat ?? '' }}@if($sekolah->npsn) · NPSN: {{ $sekolah->npsn }}@endif
    </p>
</div>

<h3 class="judul">{{ $judul }} Tahun {{ $tahun }}</h3>
<div class="periode">Jumlah unit: {{ $units->count() }}</div>

<table>
    <thead>
    <tr>
        <th style="width:24px;">No</th>
        <th style="width:42px;">Register</th>
        <th>Kode Barang</th>
        <th>Nama Barang</th>
        <th>Merk/Tipe</th>
        <th style="width:40px;">Kondisi</th>
        <th style="width:60px;">Tgl Perolehan</th>
        <th style="width:70px;">Harga Satuan</th>
        <th style="width:70px;">Jumlah</th>
        <th style="width:80px;">Ruang</th>
        <th style="width:80px;">Penanggung Jawab</th>
        <th style="width:55px;">Status</th>
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
            <td class="num">{{ number_format((float) $unit->harga_satuan, 0, ',', '.') }}</td>
            <td class="num">{{ number_format((float) $unit->nilai_perolehan_unit, 0, ',', '.') }}</td>
            <td>{{ $unit->ruang?->nama_ruang ?? '-' }}</td>
            <td>{{ $unit->pegawai?->nama_lengkap ?? '-' }}</td>
            <td class="mid">{{ $unit->status }}</td>
        </tr>
    @empty
        <tr><td colspan="12" class="mid">Tidak ada unit pada tahun {{ $tahun }}.</td></tr>
    @endforelse
    </tbody>
    <tfoot>
    <tr>
        <td colspan="8" class="num">Total Nilai</td>
        <td class="num">{{ number_format($totalNilai, 0, ',', '.') }}</td>
        <td colspan="3"></td>
    </tr>
    </tfoot>
</table>

<table class="ttd">
    <tr>
        <td style="width:50%;">
            Mengetahui,<br>{{ $pejabatKiri['jabatan'] ?? ($labelJabatan['kepala_sekolah'] ?? '-') }}
            <br><br><br>
            <strong>{{ $pejabatKiri['nama'] ?? '...........................' }}</strong><br>
            NIP {{ $pejabatKiri['nip'] ?? '...................' }}
        </td>
        <td style="width:50%;">
            {{ $sekolah->kota_kab ?? '' }}, {{ now()->translatedFormat('d F Y') }}<br>
            {{ $pejabatKanan['jabatan'] ?? ($labelJabatan['pembantu_pengurus_barang'] ?? '-') }}
            <br><br><br>
            <strong>{{ $pejabatKanan['nama'] ?? '...........................' }}</strong><br>
            NIP {{ $pejabatKanan['nip'] ?? '...................' }}
        </td>
    </tr>
</table>
</body>
</html>
