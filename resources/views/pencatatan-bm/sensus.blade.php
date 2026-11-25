<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $judul }} — {{ $sekolah->nama_sekolah }}</title>
    <style>
        @page { size: 330mm 215mm; margin: 15mm; }
        body { font-family: 'Times New Roman', 'Times', serif; font-size: 9pt; color: #000; margin: 0; }
        .judul { text-align: center; margin-bottom: 12px; }
        .judul .b1 { font-size: 12pt; font-weight: bold; text-transform: uppercase; }
        .judul .b2 { font-size: 10.5pt; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; font-size: 8.5pt; }
        th, td { border: 1px solid #000; padding: 3px 4px; vertical-align: middle; }
        th { text-align: center; font-weight: bold; line-height: 1.3; }
        .mid { text-align: center; }
        .reg { font-family: 'Courier New', monospace; font-weight: bold; }
        .merk { color: #555; font-size: 8pt; }
        .kosong { text-align: center; padding: 8px; }
    </style>
</head>
<body>
@php
    $kelompok = $units
        ->groupBy(fn ($unit): string => $unit->pegawai?->nama_lengkap ?: 'TIDAK TERIDENTIFIKASI')
        ->sortKeys();
@endphp

<div class="judul">
    <div class="b1">SENSUS BARANG MILIK DAERAH PERALATAN DAN MESIN</div>
    <div class="b2">{{ mb_strtoupper($sekolah->nama_sekolah) }}</div>
</div>

<table>
    <thead>
    <tr>
        <th style="width: 4%;">NO</th>
        <th style="width: 21%;">NAMA PEGAWAI</th>
        <th style="width: 14%;">NO. REGISTER</th>
        <th style="width: 35%;">BARANG / ASET</th>
        <th style="width: 14%;">LOKASI</th>
        <th style="width: 12%;">VOL/SATUAN</th>
    </tr>
    </thead>
    @forelse($kelompok as $nama => $daftar)
        <tbody>
        @foreach($daftar as $i => $unit)
            <tr>
                @if($i === 0)
                    <td rowspan="{{ $daftar->count() }}" class="mid">{{ $loop->parent->iteration }}</td>
                    <td rowspan="{{ $daftar->count() }}" style="font-weight: bold;">{{ $nama }}</td>
                @endif
                <td class="mid reg">{{ $unit->nomor_register }}</td>
                <td>
                    <span style="font-weight: bold;">{{ $unit->nama_barang ?? '-' }}</span><br>
                    <span class="merk">{{ $unit->merk_tipe ?? '-' }} ({{ $unit->kode_barang }})</span>
                </td>
                <td class="mid">{{ $unit->ruang?->nama_ruang ?: '-' }}</td>
                <td class="mid">1 {{ $unit->satuan ?: 'Unit' }}</td>
            </tr>
        @endforeach
        </tbody>
    @empty
        <tbody>
        <tr><td colspan="6" class="kosong">Tidak ada data penyerahan barang yang ditemukan.</td></tr>
        </tbody>
    @endforelse
</table>
</body>
</html>
