<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Buku Penyaluran Persediaan</title>
    <style>
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { font-size: 8px; color: #0f172a; }
        .title { text-align: center; margin-bottom: 8px; }
        .title h1 { font-size: 12px; margin: 0; text-transform: uppercase; }
        .title p { font-size: 10px; margin: 2px 0 0; }
        .header-meta { margin-bottom: 8px; font-size: 9px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: 1px solid #cbd5e1; padding: 3px 3px; font-size: 7px; }
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
        <h1>BUKU PENYALURAN PERSEDIAAN</h1>
        <p>{{ $dokumen->nomor }}</p>
    </div>

    @php
        $ws = $penandatangan['wakasek_sarpras'] ?? [];
        $ppb = $penandatangan['pembantu_pengurus_barang'] ?? [];
        $kasek = $penandatangan['kepala_sekolah'] ?? [];
        $bast = $pengajuan->dokumen->where('jenis','bast')->first();
        $sppb = $pengajuan->dokumen->where('jenis','surat_penyaluran')->first();
        $items = $dokumen->payload['items'] ?? $pengajuan->details->map(fn($d) => [
            'bast_tanggal' => optional($bast?->tanggal_titimangsa)->format('d-m-Y'),
            'bast_nomor' => $bast?->nomor ?? '-',
            'kode_barang' => $d->barang->kode_barang ?? '-',
            'nama_barang' => $d->barang->nama_barang ?? '-',
            'nusp' => $d->barang->spesifikasi_barang_nusp ?? '-',
            'spesifikasi' => $d->barang->spesifikasi_nama_barang ?? '-',
            'jumlah' => $d->jumlah_disetujui ?? $d->jumlah_diminta,
            'harga_satuan' => 0,
            'nilai_total' => 0,
            'sppb_tanggal' => optional($sppb?->tanggal_titimangsa)->format('d-m-Y'),
            'sppb_nomor' => $sppb?->nomor ?? '-',
            'penerima' => $pengajuan->divisi->nama ?? '-',
            'keterangan' => $d->keperluan ?? '',
        ]);
    @endphp

    <table class="header-meta">
        <tr>
            <td style="width:180px;"><strong>Kuasa Pengguna Barang</strong></td>
            <td>: {{ $penandatangan['kuasa_pengguna_barang'] ?? $kasek['nama'] ?? '-' }}</td>
        </tr>
        <tr>
            <td><strong>Pengguna Barang</strong></td>
            <td>: {{ $penandatangan['pengguna_barang'] ?? 'Kepala Dinas Pendidikan Provinsi Jawa Barat' }}</td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th rowspan="2" style="width:16px;">No.</th>
                <th colspan="2">BAST</th>
                <th rowspan="2">Kode Barang</th>
                <th rowspan="2">Nama Barang</th>
                <th colspan="2">Spesifikasi Barang</th>
                <th rowspan="2" style="width:30px;">Jumlah</th>
                <th rowspan="2" style="width:55px;">Harga Satuan (Rp)</th>
                <th rowspan="2" style="width:55px;">Nilai Total (Rp)</th>
                <th colspan="2">SPPB</th>
                <th rowspan="2">Penerima</th>
                <th rowspan="2">Keterangan</th>
            </tr>
            <tr>
                <th>Tanggal</th><th>Nomor</th>
                <th>NUSP</th><th>Nama Barang</th>
                <th>Tanggal</th><th>Nomor</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $i => $item)
                <tr>
                    <td class="center">{{ $i + 1 }}</td>
                    <td>{{ $item['bast_tanggal'] ?? '-' }}</td>
                    <td>{{ $item['bast_nomor'] ?? '-' }}</td>
                    <td>{{ $item['kode_barang'] ?? '-' }}</td>
                    <td>{{ $item['nama_barang'] ?? '-' }}</td>
                    <td>{{ $item['nusp'] ?? '-' }}</td>
                    <td>{{ $item['spesifikasi'] ?? '-' }}</td>
                    <td class="center">{{ $item['jumlah'] ?? 0 }}</td>
                    <td class="right">{{ number_format((int) ($item['harga_satuan'] ?? 0), 0, ',', '.') }}</td>
                    <td class="right">{{ number_format((int) ($item['nilai_total'] ?? 0), 0, ',', '.') }}</td>
                    <td>{{ $item['sppb_tanggal'] ?? '-' }}</td>
                    <td>{{ $item['sppb_nomor'] ?? '-' }}</td>
                    <td>{{ $item['penerima'] ?? '-' }}</td>
                    <td>{{ $item['keterangan'] ?? '' }}</td>
                </tr>
            @empty
                <tr><td colspan="14" style="text-align:center;color:#64748b;">Tidak ada item.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="ttd">
        <tr>
            <td>
                Mengetahui,<br>
                {{ $ws['jabatan'] ?? ($labelJabatan['wakasek_sarpras'] ?? '-') }},<div class="space"></div>
                <strong>{{ $ws['nama'] ?? '-' }}</strong>
                @if(!empty($ws['nip']))<br>NIP. {{ $ws['nip'] }}@endif
            </td>
            <td>
                {{ optional($dokumen->tanggal_titimangsa)->translatedFormat('d F Y') ?? now()->translatedFormat('d F Y') }}<br>
                {{ $labelJabatan['pembantu_pengurus_barang'] ?? '-' }},<div class="space"></div>
                <strong>{{ $ppb['nama'] ?? '-' }}</strong>
                @if(!empty($ppb['nip']))<br>NIP. {{ $ppb['nip'] }}@endif
            </td>
        </tr>
    </table>
</body>
</html>