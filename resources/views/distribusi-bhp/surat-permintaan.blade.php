<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Surat Permintaan Barang</title>
    <style>
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { font-size: 9px; color: #0f172a; }
        .title { text-align: center; margin-bottom: 10px; }
        .title h1 { font-size: 13px; margin: 0; text-transform: uppercase; }
        .title h2 { font-size: 11px; margin: 2px 0 0; }
        .meta { margin-bottom: 8px; font-size: 9px; }
        .meta td { padding: 1px 0; vertical-align: top; }
        .meta .label { width: 100px; color: #475569; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: 1px solid #cbd5e1; padding: 3px 4px; }
        table.data th { background: #eff6ff; text-transform: uppercase; font-size: 8px; text-align: center; }
        .right { text-align: right; }
        .center { text-align: center; }
        .ttd { width: 100%; margin-top: 30px; font-size: 9px; }
        .ttd td { text-align: center; vertical-align: top; width: 50%; }
        .ttd .space { height: 50px; }
    </style>
</head>
<body>
    <div class="title">
        <h1>SURAT PERMINTAAN BARANG</h1>
        <h2>{{ $dokumen->nomor }}</h2>
    </div>

    <table class="meta">
        <tr><td class="label">Dasar Permintaan</td><td></td></tr>
        <tr><td class="label">&nbsp;&nbsp;a. Nomor</td><td>: {{ $dokumen->payload['nomor_nota_permintaan'] ?? $pengajuan->dokumen->where('jenis','nota_permintaan')->first()?->nomor ?? '-' }}</td></tr>
        <tr><td class="label">&nbsp;&nbsp;b. Tanggal</td><td>: {{ optional($pengajuan->tanggal)->translatedFormat('d F Y') ?? '-' }}</td></tr>
        <tr><td class="label">&nbsp;&nbsp;c. Pihak yang meminta</td><td>: {{ $pengajuan->divisi->nama ?? '-' }}</td></tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th rowspan="2" style="width:24px;">No</th>
                <th rowspan="2">Kode Barang</th>
                <th rowspan="2">Nama Barang</th>
                <th rowspan="2">NUSP</th>
                <th rowspan="2">Spesifikasi Nama Barang</th>
                <th colspan="2">Pengajuan Permintaan</th>
                <th colspan="2">Informasi Sisa Barang Persediaan</th>
                <th colspan="2">Usulan Pengajuan Persetujuan</th>
                <th rowspan="2">Keperluan</th>
                <th rowspan="2">Ket</th>
            </tr>
            <tr>
                <th>Jumlah</th><th>Satuan</th>
                <th>Jumlah</th><th>Satuan</th>
                <th>Jumlah</th><th>Satuan</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($pengajuan->details as $i => $detail)
                @php
                    $barang = $detail->barang;
                    $sisaStok = $stokPerBarang[$barang->id]['tersedia'] ?? 0;
                    $diminta = (int) $detail->jumlah_diminta;
                    $disetujui = (int) ($detail->jumlah_disetujui ?? $diminta);
                @endphp
                <tr>
                    <td class="center">{{ $i + 1 }}</td>
                    <td>{{ $barang->kode_barang ?? '-' }}</td>
                    <td>{{ $barang->nama_barang ?? '-' }}</td>
                    <td>{{ $barang->spesifikasi_barang_nusp ?? '-' }}</td>
                    <td>{{ $barang->spesifikasi_nama_barang ?? '-' }}</td>
                    <td class="center">{{ $diminta }}</td>
                    <td class="center">{{ $barang->satuan ?? '-' }}</td>
                    <td class="center">{{ $sisaStok }}</td>
                    <td class="center">{{ $barang->satuan ?? '-' }}</td>
                    <td class="center">{{ $disetujui }}</td>
                    <td class="center">{{ $barang->satuan ?? '-' }}</td>
                    <td>{{ $detail->keperluan ?? '-' }}</td>
                    <td>{{ $detail->keterangan ?? '' }}</td>
                </tr>
            @empty
                <tr><td colspan="13" style="text-align:center;color:#64748b;">Tidak ada item.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="ttd">
        <tr>
            <td>
                {{ optional($dokumen->tanggal_titimangsa)->translatedFormat('d F Y') ?? now()->translatedFormat('d F Y') }}
                <div class="space"></div>
                <strong>{{ $penandatangan['wakasek_sarpras']['nama'] ?? '-' }}</strong>
                @if(!empty($penandatangan['wakasek_sarpras']['nip']))
                    <br>NIP. {{ $penandatangan['wakasek_sarpras']['nip'] }}
                @endif
            </td>
            <td></td>
        </tr>
    </table>
</body>
</html>