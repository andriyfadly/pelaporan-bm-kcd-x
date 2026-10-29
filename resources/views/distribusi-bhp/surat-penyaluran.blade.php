<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Surat Perintah Penyaluran Barang</title>
    <style>
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { font-size: 11px; color: #0f172a; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.data th, table.data td { border: 1px solid #cbd5e1; padding: 5px 6px; }
        table.data th { background: #eff6ff; text-transform: uppercase; font-size: 10px; text-align: center; }
        .right { text-align: right; }
        .center { text-align: center; }
        .meta { margin-bottom: 8px; }
        .meta td { padding: 2px 0; vertical-align: top; }
        .meta .label { width: 130px; color: #475569; }
        .ttd { width: 100%; margin-top: 40px; }
        .ttd td { text-align: center; vertical-align: top; width: 50%; }
        .ttd .space { height: 60px; }
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
        $nota = $pengajuan->dokumen->where('jenis','nota_permintaan')->first();
        $suratPermintaan = $pengajuan->dokumen->where('jenis','surat_permintaan')->first();
    @endphp

    <table class="meta">
        <tr><td class="label">Dasar Penyaluran</td><td></td></tr>
        <tr><td class="label">&nbsp;&nbsp;a. Nomor</td><td>: {{ $suratPermintaan->nomor ?? $nota->nomor ?? '-' }}</td></tr>
        <tr><td class="label">&nbsp;&nbsp;b. Tanggal</td><td>: {{ optional($suratPermintaan->tanggal_titimangsa ?? $nota->tanggal_titimangsa ?? $pengajuan->tanggal)->translatedFormat('d F Y') ?? '-' }}</td></tr>
        <tr><td class="label">&nbsp;&nbsp;c. Pihak Peminta</td><td>: {{ $pengajuan->divisi->nama ?? '-' }}</td></tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th style="width:30px;">No</th>
                <th>Kode Barang</th>
                <th>Nama Barang</th>
                <th>Spesifikasi Barang</th>
                <th colspan="2">Persetujuan Pengeluaran</th>
                <th>Ket</th>
            </tr>
            <tr>
                <th colspan="4"></th>
                <th>Jumlah</th><th>Satuan</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($pengajuan->details as $i => $detail)
                <tr>
                    <td class="center">{{ $i + 1 }}</td>
                    <td>{{ $detail->barang->kode_barang ?? '-' }}</td>
                    <td>{{ $detail->barang->nama_barang ?? '-' }}</td>
                    <td>{{ $detail->barang->spesifikasi_nama_barang ?? '-' }}</td>
                    <td class="center">{{ $detail->jumlah_disetujui ?? $detail->jumlah_diminta }}</td>
                    <td class="center">{{ $detail->barang->satuan ?? '-' }}</td>
                    <td>{{ $detail->keterangan ?? '' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" style="text-align:center;color:#64748b;">Tidak ada item.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="ttd">
        <tr>
            <td></td>
            <td>
                {{ optional($dokumen->tanggal_titimangsa)->translatedFormat('d F Y') ?? now()->translatedFormat('d F Y') }}
                <div class="space"></div>
                <strong>{{ $penandatangan['wakasek_sarpras']['nama'] ?? '-' }}</strong>
                @if(!empty($penandatangan['wakasek_sarpras']['pangkat_golongan']))
                    <br>{{ $penandatangan['wakasek_sarpras']['pangkat_golongan'] }}
                @endif
                @if(!empty($penandatangan['wakasek_sarpras']['nip']))
                    <br>NIP. {{ $penandatangan['wakasek_sarpras']['nip'] }}
                @endif
            </td>
        </tr>
    </table>
</body>
</html>