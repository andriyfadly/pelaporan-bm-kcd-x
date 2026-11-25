<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $judul }} — {{ $sekolah?->nama_sekolah }}</title>
    <style>
        @page { size: 330mm 215mm; margin: 8mm 10mm; }
        body { font-family: 'Times New Roman', 'Times', serif; font-size: 9px; color: #000; margin: 0; }
        table { border-collapse: collapse; }
        .info td { border: 0; padding: 1px 0; font-size: 10px; }
        table.main { width: 100%; font-size: 8.5px; }
        table.main th, table.main td { border: 1px solid #000; padding: 2px 3px; vertical-align: middle; line-height: 1.2; }
        table.main th { text-align: center; font-weight: bold; }
        .mid { text-align: center; }
        .kosong { text-align: center; }
        table.ttd { width: 100%; text-align: center; font-size: 10px; margin-top: 6px; }
        table.ttd td { border: 0; vertical-align: top; }
        .ttd-ruang { height: 50px; }
    </style>
</head>
<body>
@php
    $kiri = $pejabat['kepala_sekolah'] ?? null;
    $tengah = $pejabat['wakasek_sarpras'] ?? null;
    $kananPejabat = $pejabat['pembantu_pengurus_barang'] ?? null;
    $pj = $opsi['penanggung_jawab'] ?? null;
    $tanggalCetak = $opsi['tanggal'] ?? now();

    // Susunan kolom tanda tangan mengikuti acuan: tiga penanda tangan penuh,
    // dua (kiri + PJ), selain itu hanya kolom kanan.
    $kolom = [];
    if (($opsi['show_ks'] ?? false) && $kiri) {
        $kolom[] = ['label' => 'Kepala Sekolah', 'nama' => $kiri['nama'] ?? null, 'nip' => $kiri['nip'] ?? null, 'ks' => true];
    } elseif ($opsi['show_ks'] ?? false) {
        $kolom[] = ['label' => 'Kepala Sekolah', 'nama' => null, 'nip' => null, 'ks' => true];
    }
    if (($opsi['show_wks'] ?? false)) {
        $kolom[] = ['label' => 'Wakasek Sarpras', 'nama' => $tengah['nama'] ?? null, 'nip' => $tengah['nip'] ?? null, 'ks' => false];
    }
    if (($opsi['show_pb'] ?? false)) {
        $kolom[] = ['label' => 'Pengurus Barang Pembantu', 'nama' => $kananPejabat['nama'] ?? null, 'nip' => $kananPejabat['nip'] ?? null, 'ks' => false];
    }

    $pjItem = ($opsi['show_pj'] ?? false)
        ? ['label' => 'Penanggung Jawab Ruangan', 'nama' => $pj?->nama_lengkap, 'nip' => $pj?->nip, 'ks' => false]
        : null;

    $total = count($kolom) + ($pjItem ? 1 : 0);

    if ($total === 3) {
        $p1 = $kolom[0] ?? null;
        $p2 = $kolom[1] ?? null;
        $p3 = $pjItem ?? ($kolom[2] ?? null);
    } elseif ($total === 2) {
        $p1 = $kolom[0] ?? null;
        $p2 = null;
        $p3 = $pjItem ?? ($kolom[1] ?? null);
    } else {
        $p1 = null;
        $p2 = null;
        $p3 = $pjItem ?? ($kolom[0] ?? null);
    }

    // Kelompokkan unit per (nama, merek Title Case, tahun) seperti acuan.
    $kelompok = $units
        ->groupBy(fn ($unit): string => implode('|', [
            $unit->nama_barang,
            $unit->merk_tipe ? ucwords(mb_strtolower($unit->merk_tipe)) : '-',
            $unit->tahun_perolehan,
        ]))
        ->map(function ($baris) {
            return [
                'unit' => $baris->first(),
                'jumlah' => $baris->count(),
                'B' => $baris->where('kondisi', 'B')->count(),
                'RR' => $baris->where('kondisi', 'RR')->count(),
                'RB' => $baris->where('kondisi', 'RB')->count(),
            ];
        })
        ->values();
@endphp

<table class="info" style="width: 100%; margin-bottom: 4px;">
    <tr>
        <td style="width: 12%;">Unit Kerja</td>
        <td style="width: 2%;">:</td>
        <td style="width: 51%;">{{ ucwords(mb_strtolower((string) ($sekolah?->nama_sekolah ?? '-'))) }}</td>
        <td style="width: 10%;">Ruangan</td>
        <td style="width: 2%;">:</td>
        <td style="width: 23%;">{{ ucwords(mb_strtolower((string) ($ruang->nama_ruang ?? '-'))) }}</td>
    </tr>
</table>

<table class="main">
    <thead>
    <tr>
        <th rowspan="2" style="width: 4%;">NO</th>
        <th rowspan="2" style="width: 13%;">Jenis Barang / Nama Barang</th>
        <th rowspan="2" style="width: 13%;">Merk / Model</th>
        <th rowspan="2" style="width: 10%;">No. Seri Pabrik</th>
        <th rowspan="2" style="width: 8%;">Ukuran</th>
        <th rowspan="2" style="width: 8%;">Bahan</th>
        <th rowspan="2" style="width: 7%;">Pengadaan</th>
        <th rowspan="2" style="width: 11%;">Kode Barang</th>
        <th rowspan="2" style="width: 7%;">Jumlah</th>
        <th colspan="3" style="width: 12%;">Keadaan Barang</th>
        <th rowspan="2" style="width: 7%;">Ket</th>
    </tr>
    <tr>
        <th style="width: 4%;">Baik</th>
        <th style="width: 4%;">RR</th>
        <th style="width: 4%;">RB</th>
    </tr>
    </thead>
    <tbody>
    @forelse($kelompok as $i => $item)
        @php($unit = $item['unit'])
        <tr>
            <td class="mid">{{ $i + 1 }}</td>
            <td>{{ $unit->nama_barang ?? '-' }}</td>
            <td>{{ $unit->merk_tipe ? ucwords(mb_strtolower($unit->merk_tipe)) : '-' }}</td>
            <td class="mid">-</td>
            <td class="mid">{{ $unit->ukuran_bangunan ?: '-' }}</td>
            <td class="mid">{{ $unit->bahan ?: '-' }}</td>
            <td class="mid">{{ $unit->tahun_perolehan }}</td>
            <td class="mid">{{ $unit->kode_barang }}</td>
            <td class="mid">{{ $item['jumlah'] }}</td>
            <td class="mid">{{ $item['B'] ?: '' }}</td>
            <td class="mid">{{ $item['RR'] ?: '' }}</td>
            <td class="mid">{{ $item['RB'] ?: '' }}</td>
            <td>{{ $unit->keterangan ?: '' }}</td>
        </tr>
    @empty
        <tr><td colspan="13" class="kosong">Data barang tidak ditemukan.</td></tr>
    @endforelse
    </tbody>
</table>

<table class="ttd">
    <tr>
        <td style="width: 33%;">{{ $p1 && ($p1['ks'] ?? false) ? 'Mengetahui,' : '' }}</td>
        <td style="width: 33%;">{{ $p2 && ($p2['ks'] ?? false) ? 'Mengetahui,' : '' }}</td>
        <td style="width: 33%;">{{ ucwords(mb_strtolower((string) ($sekolah?->kota_kab ?? ''))) }}, {{ $tanggalCetak->translatedFormat('d F Y') }}</td>
    </tr>
    <tr>
        <td>{{ $p1['label'] ?? '' }}</td>
        <td>{{ $p2['label'] ?? '' }}</td>
        <td>{{ $p3['label'] ?? '' }}</td>
    </tr>
    <tr><td colspan="3" class="ttd-ruang"></td></tr>
    <tr>
        <td>@if($p1)<b>{{ $p1['nama'] ?? '...........................' }}</b><br>NIP. {{ $p1['nip'] ?: '...................' }}@endif</td>
        <td>@if($p2)<b>{{ $p2['nama'] ?? '...........................' }}</b><br>NIP. {{ $p2['nip'] ?: '...................' }}@endif</td>
        <td>@if($p3)<b>{{ $p3['nama'] ?? '...........................' }}</b><br>NIP. {{ $p3['nip'] ?: '...................' }}@endif</td>
    </tr>
</table>
</body>
</html>
