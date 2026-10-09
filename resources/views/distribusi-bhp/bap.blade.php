<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Berita Acara Pemeriksaan Barang</title>
    <style>
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { font-size: 11px; color: #0f172a; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.data th, table.data td { border: 1px solid #cbd5e1; padding: 5px 6px; }
        table.data th { background: #eff6ff; text-transform: uppercase; font-size: 10px; text-align: center; }
        .center { text-align: center; }
        .meta { margin-bottom: 8px; }
        .meta td { padding: 2px 0; vertical-align: top; }
        .meta .label { width: 160px; color: #475569; }
        .ttd { width: 100%; margin-top: 40px; }
        .ttd td { text-align: center; vertical-align: top; }
        .ttd .space { height: 60px; }
        .narasi { line-height: 1.6; margin-bottom: 12px; text-align: justify; }
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
        $ppb = $penandatangan['pembantu_pengurus_barang'] ?? [];
        $payload = $dokumen->payload ?? [];
    @endphp

    <div class="narasi">
        Pada hari ini, {{ optional($dokumen->tanggal_titimangsa)->isoFormat('dddd') ?? '-' }} tanggal
        {{ optional($dokumen->tanggal_titimangsa)->format('d F Y') ?? '-' }},
        Tim/Petugas Pemeriksa Barang menerangkan bahwa telah dilakukan pemeriksaan barang persediaan
        dengan hasil sebagai berikut:
    </div>

    <table class="meta">
        <tr><td class="label">Petugas Pemeriksa</td><td>: {{ $ppb['nama'] ?? '-' }}</td></tr>
        <tr><td class="label">&nbsp;&nbsp;Jabatan</td><td>: {{ $ppb['jabatan'] ?? 'Pembantu Pengurus Barang' }}</td></tr>
        @if(!empty($ppb['nip']))
            <tr><td class="label">&nbsp;&nbsp;NIP</td><td>: {{ $ppb['nip'] }}</td></tr>
        @endif
        <tr><td class="label">No. Nota/Invoice</td><td>: {{ $payload['no_nota_invoice'] ?? '-' }}</td></tr>
        <tr><td class="label">Penyedia Barang</td><td>: {{ $payload['penyedia'] ?? '-' }}</td></tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th style="width:30px;">No</th>
                <th>Nama Barang</th>
                <th style="width:50px;">Jumlah</th>
                <th style="width:60px;">Satuan</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($pengajuan->details as $i => $detail)
                <tr>
                    <td class="center">{{ $i + 1 }}</td>
                    <td>{{ $detail->barang->nama_barang ?? '-' }}</td>
                    <td class="center">{{ $detail->jumlah_disetujui ?? $detail->jumlah_diminta }}</td>
                    <td class="center">{{ $detail->barang->satuan ?? '-' }}</td>
                    <td>{{ $detail->keterangan ?? '' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" style="text-align:center;color:#64748b;">Tidak ada item.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="narasi" style="margin-top:16px;">
        Berdasarkan pemeriksaan tanggal {{ optional($dokumen->tanggal_titimangsa)->format('d F Y') ?? '-' }},
        barang tersebut telah diperiksa dan dinyatakan telah sesuai.
    </div>

    <table class="ttd">
        <tr>
            <td></td>
            <td>
                {{ optional($dokumen->tanggal_titimangsa)->format('d F Y') ?? now()->format('d F Y') }}
                <div class="space"></div>
                <strong>{{ $ppb['nama'] ?? '-' }}</strong>
                @if(!empty($ppb['nip']))
                    <br>NIP. {{ $ppb['nip'] }}
                @endif
            </td>
        </tr>
    </table>
</body>
</html>