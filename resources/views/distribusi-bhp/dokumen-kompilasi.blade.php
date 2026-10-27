<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Batch Dokumen BHP</title>
    <style>
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { font-size: 11px; color: #0f172a; }
        .page { page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        .kop { text-align: center; border-bottom: 2px solid #1e3a8a; padding-bottom: 8px; margin-bottom: 14px; }
        .kop h1 { font-size: 15px; margin: 0; text-transform: uppercase; }
        .kop h2 { font-size: 12px; margin: 2px 0 0; font-weight: normal; }
        .meta { width: 100%; margin-bottom: 12px; }
        .meta td { padding: 2px 0; vertical-align: top; }
        .meta .label { width: 130px; color: #475569; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.data th, table.data td { border: 1px solid #cbd5e1; padding: 5px 6px; }
        table.data th { background: #eff6ff; text-transform: uppercase; font-size: 10px; text-align: center; }
        .right { text-align: right; }
        .center { text-align: center; }
        .ttd { width: 100%; margin-top: 40px; }
        .ttd td { width: 50%; text-align: center; vertical-align: top; }
        .ttd .space { height: 60px; }
        .muted { color: #64748b; }
        .judul-dokumen { text-align: center; text-transform: uppercase; font-size: 13px; font-weight: bold; margin: 0 0 4px; }
    </style>
</head>
<body>
    @foreach ($dokumenList as $dokumen)
        <div class="page">
            @if(in_array($dokumen->jenis, ['nota_permintaan', 'surat_penyaluran', 'bast', 'bap', 'stok_opname']))
                @include('distribusi-bhp.partials.kop-surat', [
                    'sekolah' => $sekolah,
                    'alamat' => $alamat ?? '',
                    'telepon' => $telepon ?? '',
                    'website' => $website ?? '',
                    'email' => $email ?? '',
                    'kodePos' => $kodePos ?? '',
                ])
            @endif
            <p class="judul-dokumen">{{ $judulMap[$dokumen->jenis] ?? ucwords(str_replace('_', ' ', $dokumen->jenis)) }}</p>
            <p style="text-align:center;margin:0 0 12px;font-weight:bold;">{{ $dokumen->nomor }}</p>

            <table class="meta">
                <tr>
                    <td class="label">Nomor Dokumen</td>
                    <td>: {{ $dokumen->nomor }}</td>
                    <td class="label">Sumber Dana</td>
                    <td>: {{ $pengajuan->sumber_dana }}</td>
                </tr>
                <tr>
                    <td class="label">Tanggal</td>
                    <td>: {{ optional($dokumen->tanggal_titimangsa)->format('d F Y') ?? '-' }}</td>
                    <td class="label">Sekolah</td>
                    <td>: {{ $sekolah }}</td>
                </tr>
            </table>

            @php
                $ppb = $penandatangan['pembantu_pengurus_barang'] ?? [];
                $ws = $penandatangan['wakasek_sarpras'] ?? [];
                $kasek = $penandatangan['kepala_sekolah'] ?? [];
            @endphp

            @if($dokumen->jenis === 'nota_permintaan')
                <p style="margin:0 0 8px;"><strong>Pihak yang meminta:</strong> {{ $pengajuan->divisi->nama ?? '-' }}</p>
            @endif

            @if($dokumen->jenis === 'bast')
                @php $sppb = $pengajuan->dokumen->where('jenis','surat_penyaluran')->first(); @endphp
                <table class="meta">
                    <tr><td class="label"><strong>PIHAK KESATU</strong></td><td>: {{ $ws['nama'] ?? '-' }}</td></tr>
                    <tr><td class="label">&nbsp;&nbsp;Jabatan</td><td>: {{ $ws['jabatan'] ?? 'Wakasek Sarpras' }}</td></tr>
                    <tr><td class="label"><strong>PIHAK KEDUA</strong></td><td>: {{ $ppb['nama'] ?? '-' }}</td></tr>
                    <tr><td class="label">&nbsp;&nbsp;Jabatan</td><td>: {{ $ppb['jabatan'] ?? 'Pembantu Pengurus Barang' }}</td></tr>
                </table>
            @endif

            @if($dokumen->jenis === 'stok_opname')
                <table class="meta">
                    <tr><td class="label">Kepala Sekolah</td><td>: {{ $kasek['nama'] ?? '-' }} ({{ $kasek['nip'] ?? '-' }})</td></tr>
                    <tr><td class="label">Wakasek Sarpras</td><td>: {{ $ws['nama'] ?? '-' }} ({{ $ws['nip'] ?? '-' }})</td></tr>
                    <tr><td class="label">Pembantu Pengurus Barang</td><td>: {{ $ppb['nama'] ?? '-' }} ({{ $ppb['nip'] ?? '-' }})</td></tr>
                </table>
            @endif

            <table class="data">
                <thead>
                    <tr>
                        <th style="width:34px;">No</th>
                        <th>Kode Barang</th>
                        <th>Nama Barang</th>
                        <th>Satuan</th>
                        <th class="right">Diminta</th>
                        <th class="right">Disetujui</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pengajuan->details as $index => $detail)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $detail->barang->kode_barang ?? '-' }}</td>
                            <td>{{ $detail->barang->nama_barang ?? '-' }}</td>
                            <td>{{ $detail->barang->satuan ?? '-' }}</td>
                            <td class="right">{{ $detail->jumlah_diminta }}</td>
                            <td class="right">{{ $detail->jumlah_disetujui ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted">Tidak ada item.</td></tr>
                    @endforelse
                </tbody>
            </table>

            @if(in_array($dokumen->jenis, ['nota_permintaan', 'bast', 'bap', 'surat_penyaluran']))
                <table class="ttd">
                    <tr>
                        @if(in_array($dokumen->jenis, ['nota_permintaan']))
                            <td></td>
                            <td>
                                {{ optional($dokumen->tanggal_titimangsa)->format('d F Y') ?? now()->format('d F Y') }}
                                <div class="space"></div>
                                <strong>{{ $ppb['nama'] ?? $pemohon }}</strong>
                            </td>
                        @elseif(in_array($dokumen->jenis, ['bast']))
                            <td>PIHAK KEDUA,<div class="space"></div><strong>{{ $ppb['nama'] ?? '-' }}</strong></td>
                            <td>PIHAK KESATU,<div class="space"></div><strong>{{ $ws['nama'] ?? '-' }}</strong></td>
                        @elseif(in_array($dokumen->jenis, ['bap']))
                            <td></td>
                            <td>
                                {{ optional($dokumen->tanggal_titimangsa)->format('d F Y') ?? now()->format('d F Y') }}
                                <div class="space"></div>
                                <strong>{{ $ppb['nama'] ?? '-' }}</strong>
                            </td>
                        @else
                            <td></td>
                            <td>
                                {{ optional($dokumen->tanggal_titimangsa)->format('d F Y') ?? now()->format('d F Y') }}
                                <div class="space"></div>
                                <strong>{{ $ws['nama'] ?? '-' }}</strong>
                            </td>
                        @endif
                    </tr>
                </table>
            @endif
        </div>
    @endforeach
</body>
</html>