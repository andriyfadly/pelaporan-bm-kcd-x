<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Buku Pengeluaran Barang</title>
    <style>
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { font-size: 8px; color: #0f172a; }
        .title { text-align: center; margin-bottom: 8px; }
        .title h1 { font-size: 12px; margin: 0; text-transform: uppercase; }
        .title p { font-size: 10px; margin: 2px 0 0; }
        .header-meta { margin-bottom: 8px; font-size: 9px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: 1px solid #cbd5e1; padding: 3px 4px; font-size: 7px; }
        table.data th { background: #eff6ff; text-transform: uppercase; text-align: center; }
        .right { text-align: right; }
        .center { text-align: center; }
        .ttd { width: 100%; margin-top: 25px; font-size: 9px; }
        .ttd td { width: 50%; text-align: center; vertical-align: top; }
        .ttd .space { height: 45px; }
    </style>
</head>
<body>
    <div class="title">
        <h1>BUKU PENGELUARAN BARANG</h1>
        <p>{{ $dokumen->nomor }}</p>
    </div>

    <table class="header-meta">
        <tr>
            <td style="width:180px;"><strong>Kuasa Pengguna Barang</strong></td>
            <td>: {{ $penandatangan['kuasa_pengguna_barang'] ?? '-' }}</td>
        </tr>
        <tr>
            <td><strong>Pengguna Barang</strong></td>
            <td>: {{ $penandatangan['pengguna_barang'] ?? 'Kepala Dinas Pendidikan Provinsi Jawa Barat' }}</td>
        </tr>
    </table>

    @php
        $ws = $penandatangan['wakasek_sarpras'] ?? [];
        $ppb = $penandatangan['pembantu_pengurus_barang'] ?? [];
        $items = $dokumen->payload['items'] ?? $pengajuan->details->map(fn($d) => [
            'tanggal' => optional($pengajuan->tanggal)->format('d-m-Y'),
            'nomor_dokumen' => $dokumen->nomor,
            'penerima' => $pengajuan->divisi->nama ?? '-',
            'kode_barang' => $d->barang->kode_barang ?? '-',
            'nama_barang' => $d->barang->nama_barang ?? '-',
            'nusp' => $d->barang->spesifikasi_barang_nusp ?? '-',
            'spesifikasi' => $d->barang->spesifikasi_nama_barang ?? '-',
            'jumlah' => $d->jumlah_disetujui ?? $d->jumlah_diminta,
            'satuan' => $d->barang->satuan ?? '-',
            'harga_satuan' => 0,
            'nilai_total' => 0,
            'keterangan' => $d->keperluan ?? '',
        ]);
    @endphp

    <table class="data">
        <thead>
            <tr>
                <th rowspan="2" style="width:18px;">No</th>
                <th colspan="3">Dokumen</th>
                <th rowspan="2">Kode Barang</th>
                <th rowspan="2">Nama Barang</th>
                <th colspan="3">Spesifikasi Barang</th>
                <th rowspan="2">Satuan</th>
                <th rowspan="2">Harga Satuan (Rp)</th>
                <th rowspan="2">Nilai Total (Rp)</th>
                <th rowspan="2">Keterangan</th>
            </tr>
            <tr>
                <th>Tanggal</th><th>Nomor</th><th>Nama</th>
                <th>NUSP</th><th>Spesifikasi</th><th>Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $i => $item)
                <tr>
                    <td class="center">{{ $i + 1 }}</td>
                    <td>{{ $item['tanggal'] ?? '-' }}</td>
                    <td>{{ $item['nomor_dokumen'] ?? $dokumen->nomor }}</td>
                    <td>{{ $item['penerima'] ?? '-' }}</td>
                    <td>{{ $item['kode_barang'] ?? '-' }}</td>
                    <td>{{ $item['nama_barang'] ?? '-' }}</td>
                    <td>{{ $item['nusp'] ?? '-' }}</td>
                    <td>{{ $item['spesifikasi'] ?? '-' }}</td>
                    <td class="center">{{ $item['jumlah'] ?? 0 }}</td>
                    <td class="center">{{ $item['satuan'] ?? '-' }}</td>
                    <td class="right">{{ number_format((int) ($item['harga_satuan'] ?? 0), 0, ',', '.') }}</td>
                    <td class="right">{{ number_format((int) ($item['nilai_total'] ?? 0), 0, ',', '.') }}</td>
                    <td>{{ $item['keterangan'] ?? '' }}</td>
                </tr>
            @empty
                <tr><td colspan="13" style="text-align:center;color:#64748b;">Tidak ada item.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="ttd">
        <tr>
            <td>
                Mengetahui,<br>
                {{ $ws['jabatan'] ?? 'Wakasek Sarpras' }},<div class="space"></div>
                <strong>{{ $ws['nama'] ?? '-' }}</strong>
                @if(!empty($ws['nip']))<br>NIP. {{ $ws['nip'] }}@endif
            </td>
            <td>
                {{ optional($dokumen->tanggal_titimangsa)->format('d F Y') ?? now()->format('d F Y') }}<br>
                Pembantu Pengurus Barang,<div class="space"></div>
                <strong>{{ $ppb['nama'] ?? '-' }}</strong>
                @if(!empty($ppb['nip']))<br>NIP. {{ $ppb['nip'] }}@endif
            </td>
        </tr>
    </table>
</body>
</html>