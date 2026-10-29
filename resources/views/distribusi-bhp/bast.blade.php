<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Berita Acara Serah Terima</title>
    <style>
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { font-size: 11px; color: #0f172a; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.data th, table.data td { border: 1px solid #cbd5e1; padding: 5px 6px; }
        table.data th { background: #eff6ff; text-transform: uppercase; font-size: 10px; text-align: center; }
        .center { text-align: center; }
        .meta { margin-bottom: 8px; }
        .meta td { padding: 2px 0; vertical-align: top; }
        .meta .label { width: 130px; color: #475569; }
        .ttd { width: 100%; margin-top: 40px; }
        .ttd td { width: 50%; text-align: center; vertical-align: top; }
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
        $suratPenyaluran = $pengajuan->dokumen->where('jenis','surat_penyaluran')->first();
        $ppb = $penandatangan['pembantu_pengurus_barang'] ?? [];
        $kasek = $penandatangan['kepala_sekolah'] ?? [];
        $ws = $penandatangan['wakasek_sarpras'] ?? [];
    @endphp

    <div class="narasi">
        Pada hari ini, {{ optional($dokumen->tanggal_titimangsa)->isoFormat('dddd') ?? '-' }} tanggal
        {{ optional($dokumen->tanggal_titimangsa)->translatedFormat('d F Y') ?? '-' }},
        bertempat di Gudang {{ $sekolah }},
        telah dilakukan serah terima penyaluran barang persediaan dengan rincian sebagai berikut:
    </div>

    <table class="meta">
        <tr><td class="label"><strong>PIHAK KESATU</strong></td><td>: {{ $ws['nama'] ?? '-' }}</td></tr>
        <tr><td class="label">&nbsp;&nbsp;Jabatan</td><td>: {{ $ws['jabatan'] ?? ($labelJabatan['wakasek_sarpras'] ?? '-') }}</td></tr>
        @if(!empty($ws['nip']))
            <tr><td class="label">&nbsp;&nbsp;NIP</td><td>: {{ $ws['nip'] }}</td></tr>
        @endif
        <tr><td class="label"><strong>PIHAK KEDUA</strong></td><td>: {{ $ppb['nama'] ?? '-' }}</td></tr>
        <tr><td class="label">&nbsp;&nbsp;Jabatan</td><td>: {{ $ppb['jabatan'] ?? ($labelJabatan['pembantu_pengurus_barang'] ?? '-') }}</td></tr>
        @if(!empty($ppb['nip']))
            <tr><td class="label">&nbsp;&nbsp;NIP</td><td>: {{ $ppb['nip'] }}</td></tr>
        @endif
        <tr><td class="label">Dasar</td><td>: SPPB No. {{ $suratPenyaluran->nomor ?? '-' }} tanggal {{ optional($suratPenyaluran->tanggal_titimangsa)->translatedFormat('d F Y') ?? '-' }}</td></tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th style="width:30px;">No.</th>
                <th>Kode Barang</th>
                <th>Nama Barang</th>
                <th>Spesifikasi Barang</th>
                <th style="width:50px;">Jumlah</th>
                <th style="width:60px;">Satuan</th>
                <th>Ket.</th>
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
            <td>
                PIHAK KEDUA,<div class="space"></div>
                <strong>{{ $ppb['nama'] ?? '-' }}</strong>
                @if(!empty($ppb['nip']))
                    <br>NIP. {{ $ppb['nip'] }}
                @endif
            </td>
            <td>
                PIHAK KESATU,<div class="space"></div>
                <strong>{{ $ws['nama'] ?? '-' }}</strong>
                @if(!empty($ws['nip']))
                    <br>NIP. {{ $ws['nip'] }}
                @endif
            </td>
        </tr>
    </table>
</body>
</html>