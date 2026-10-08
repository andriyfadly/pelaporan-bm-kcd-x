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
        table.data th { background: #eff6ff; text-transform: uppercase; font-size: 10px; }
        .right { text-align: right; }
        .ttd { width: 100%; margin-top: 40px; }
        .ttd td { width: 50%; text-align: center; vertical-align: top; }
        .ttd .space { height: 60px; }
    </style>
</head>
<body>
    @foreach ($dokumenList as $dokumen)
        <div class="page">
            <div class="kop">
                <h1>{{ $judulMap[$dokumen->jenis] ?? ucwords(str_replace('_', ' ', $dokumen->jenis)) }}</h1>
                <h2>Sistem Digitalisasi Pelaporan Aset — Distribusi BHP</h2>
            </div>

            <table class="meta">
                <tr>
                    <td class="label">Nomor Dokumen</td>
                    <td>: {{ $dokumen->nomor }}</td>
                    <td class="label">Sumber Dana</td>
                    <td>: {{ $pengajuan->sumber_dana }}</td>
                </tr>
                <tr>
                    <td class="label">Tanggal Titimangsa</td>
                    <td>: {{ optional($dokumen->tanggal_titimangsa)->format('d F Y') ?? '-' }}</td>
                    <td class="label">Sekolah</td>
                    <td>: {{ $sekolah }}</td>
                </tr>
            </table>

            <table class="data">
                <thead>
                    <tr>
                        <th style="width: 34px;">No</th>
                        <th>Kode Barang</th>
                        <th>Nama Barang</th>
                        <th>Satuan</th>
                        <th class="right">Diminta</th>
                        <th class="right">Disetujui</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pengajuan->details as $index => $detail)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $detail->barang->kode_barang ?? '-' }}</td>
                            <td>{{ $detail->barang->nama_barang ?? '-' }}</td>
                            <td>{{ $detail->barang->satuan ?? '-' }}</td>
                            <td class="right">{{ $detail->jumlah_diminta }}</td>
                            <td class="right">{{ $detail->jumlah_disetujui ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if (isset($penandatangan['kepala_sekolah']))
                <table class="ttd">
                    <tr>
                        <td></td>
                        <td>
                            <div class="space" style="height: 40px;"></div>
                            Mengetahui/Menyetujui,<br>
                            {{ $penandatangan['kepala_sekolah']['jabatan'] }},<div class="space"></div>
                            <strong>{{ $penandatangan['kepala_sekolah']['nama'] }}</strong>
                            @if (!empty($penandatangan['kepala_sekolah']['nip']))
                                <br>NIP. {{ $penandatangan['kepala_sekolah']['nip'] }}
                            @endif
                        </td>
                    </tr>
                </table>
            @endif
        </div>
    @endforeach
</body>
</html>
