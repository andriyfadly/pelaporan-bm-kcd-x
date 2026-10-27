<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Berita Acara Stok Opname</title>
    <style>
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { font-size: 10px; color: #0f172a; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.data th, table.data td { border: 1px solid #cbd5e1; padding: 4px 5px; }
        table.data th { background: #eff6ff; text-transform: uppercase; font-size: 9px; text-align: center; }
        .right { text-align: right; }
        .center { text-align: center; }
        .meta { margin-bottom: 8px; font-size: 10px; }
        .meta td { padding: 2px 0; vertical-align: top; }
        .meta .label { width: 180px; color: #475569; }
        .ttd { width: 100%; margin-top: 35px; font-size: 10px; }
        .ttd td { width: 33%; text-align: center; vertical-align: top; }
        .ttd .space { height: 55px; }
        .narasi { line-height: 1.5; margin-bottom: 10px; text-align: justify; }
    </style>
</head>
<body>
    @include('distribusi-bhp.partials.kop-surat', [
        'sekolah' => $sekolah,
        'alamat' => $alamat ?? '',
        'telepon' => $telepon ?? '',
        'website' => $website ?? '',
        'email' => $email ?? '',
        'kodePos' => $kodePos ?? '',
    ])

    <h2 style="text-align:center;margin:0 0 4px;text-transform:uppercase;">{{ $judul }}</h2>
    <p style="text-align:center;margin:0 0 12px;font-weight:bold;">{{ $dokumen->nomor }}</p>

    @php
        $kasek = $penandatangan['kepala_sekolah'] ?? [];
        $ws = $penandatangan['wakasek_sarpras'] ?? [];
        $ppb = $penandatangan['pembantu_pengurus_barang'] ?? [];
        $items = $dokumen->payload['items'] ?? [];
    @endphp

    <div class="narasi">
        Pada hari ini, {{ optional($dokumen->tanggal_titimangsa)->isoFormat('dddd') ?? '-' }} tanggal
        {{ optional($dokumen->tanggal_titimangsa)->format('d F Y') ?? '-' }},
        bertempat di Gudang {{ $sekolah }},
        telah dilakukan inventarisasi fisik persediaan barang dengan hasil sebagai berikut:
    </div>

    <table class="meta">
        <tr>
            <td class="label">{{ $labelJabatan['kepala_sekolah'] ?? '-' }}</td>
            <td>: {{ $kasek['nama'] ?? '-' }}</td>
            <td class="label">{{ $labelJabatan['wakasek_sarpras'] ?? '-' }}</td>
            <td>: {{ $ws['nama'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">&nbsp;&nbsp;NIP</td>
            <td>: {{ $kasek['nip'] ?? '-' }}</td>
            <td class="label">&nbsp;&nbsp;NIP</td>
            <td>: {{ $ws['nip'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">{{ $labelJabatan['pembantu_pengurus_barang'] ?? '-' }}</td>
            <td>: {{ $ppb['nama'] ?? '-' }}</td>
            <td class="label">&nbsp;&nbsp;NIP</td>
            <td>: {{ $ppb['nip'] ?? '-' }}</td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th style="width:30px;">No</th>
                <th>Kode Barang</th>
                <th>Nama Barang</th>
                <th style="width:50px;">Jumlah</th>
                <th style="width:80px;">Nilai (Rp)</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @php $totalNilai = 0; @endphp
            @forelse ($items as $i => $item)
                @php $totalNilai += (int) ($item['nilai_total'] ?? 0); @endphp
                <tr>
                    <td class="center">{{ $i + 1 }}</td>
                    <td>{{ $item['kode_barang'] ?? '-' }}</td>
                    <td>{{ $item['nama_barang'] ?? '-' }}</td>
                    <td class="center">{{ $item['fisik_nyata'] ?? $item['fisik'] ?? $item['jumlah'] ?? 0 }} {{ $item['satuan'] ?? '' }}</td>
                    <td class="right">{{ number_format($item['nilai_total'] ?? 0, 0, ',', '.') }}</td>
                    <td>{{ $item['keterangan'] ?? '' }}</td>
                </tr>
            @empty
                @forelse ($pengajuan->details as $i => $detail)
                    @php
                        $barang = $detail->barang;
                        $totalNilai += 0;
                    @endphp
                    <tr>
                        <td class="center">{{ $i + 1 }}</td>
                        <td>{{ $barang->kode_barang ?? '-' }}</td>
                        <td>{{ $barang->nama_barang ?? '-' }}</td>
                        <td class="center">{{ $detail->jumlah_disetujui ?? $detail->jumlah_diminta }} {{ $barang->satuan ?? '' }}</td>
                        <td class="right">-</td>
                        <td>{{ $detail->keterangan ?? '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="text-align:center;color:#64748b;">Tidak ada item.</td></tr>
                @endforelse
            @endforelse
        </tbody>
        <tfoot>
            <tr style="font-weight:bold;">
                <td colspan="4" style="text-align:right;padding:5px 6px;">Jumlah</td>
                <td class="right">{{ number_format($totalNilai, 0, ',', '.') }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <table class="ttd">
        <tr>
            <td>
                {{ $labelJabatan['wakasek_sarpras'] ?? '-' }},<div class="space"></div>
                <strong>{{ $ws['nama'] ?? '-' }}</strong>
                @if(!empty($ws['nip']))<br>NIP. {{ $ws['nip'] }}@endif
            </td>
            <td>
                {{ optional($dokumen->tanggal_titimangsa)->format('d F Y') ?? now()->format('d F Y') }}<br>
                {{ $labelJabatan['pembantu_pengurus_barang'] ?? '-' }},<div class="space"></div>
                <strong>{{ $ppb['nama'] ?? '-' }}</strong>
                @if(!empty($ppb['nip']))<br>NIP. {{ $ppb['nip'] }}@endif
            </td>
            <td>
                Mengetahui,<br>
                {{ $kasek['jabatan'] ?? ($labelJabatan['kepala_sekolah'] ?? '-') }},<div class="space"></div>
                <strong>{{ $kasek['nama'] ?? '-' }}</strong>
                @if(!empty($kasek['nip']))<br>NIP. {{ $kasek['nip'] }}@endif
            </td>
        </tr>
    </table>
</body>
</html>