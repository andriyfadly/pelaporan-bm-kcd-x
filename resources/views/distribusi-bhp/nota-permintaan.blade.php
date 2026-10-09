<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Nota Permintaan Barang</title>
    <style>
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { font-size: 11px; color: #0f172a; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.data th, table.data td { border: 1px solid #cbd5e1; padding: 5px 6px; }
        table.data th { background: #eff6ff; text-transform: uppercase; font-size: 10px; text-align: center; }
        .right { text-align: right; }
        .center { text-align: center; }
        .meta { margin-bottom: 12px; }
        .meta td { padding: 2px 0; vertical-align: top; }
        .meta .label { width: 130px; color: #475569; }
        .ttd { width: 100%; margin-top: 40px; }
        .ttd td { text-align: center; vertical-align: top; }
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

    <p style="margin:0 0 8px;"><strong>Pihak yang meminta:</strong> {{ $pengajuan->divisi->nama ?? '-' }}</p>

    <table class="data">
        <thead>
            <tr>
                <th style="width:30px;">NO.</th>
                <th>Nama Barang</th>
                <th style="width:50px;">Jumlah</th>
                <th style="width:60px;">Satuan</th>
                <th>Keperluan</th>
                <th style="width:80px;">Ket.</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($pengajuan->details as $i => $detail)
                <tr>
                    <td class="center">{{ $i + 1 }}</td>
                    <td>{{ $detail->barang->nama_barang ?? '-' }}</td>
                    <td class="center">{{ $detail->jumlah_diminta }}</td>
                    <td class="center">{{ $detail->barang->satuan ?? '-' }}</td>
                    <td>{{ $detail->keperluan ?? '-' }}</td>
                    <td>{{ $detail->keterangan ?? '' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;color:#64748b;">Tidak ada item.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="ttd">
        <tr>
            <td></td>
            <td>
                {{ optional($dokumen->tanggal_titimangsa)->format('d F Y') ?? ($penandatangan['lokasi'] ?? 'Kabupaten') }}, {{ $dokumen->created_at?->format('d F Y') ?? now()->format('d F Y') }}
                <div class="space"></div>
                <strong>{{ $penandatangan['pembantu_pengurus_barang']['nama'] ?? $pemohon ?? '-' }}</strong>
                @if(!empty($penandatangan['pembantu_pengurus_barang']['nip']))
                    <br>NIP. {{ $penandatangan['pembantu_pengurus_barang']['nip'] }}
                @endif
            </td>
        </tr>
    </table>
</body>
</html>