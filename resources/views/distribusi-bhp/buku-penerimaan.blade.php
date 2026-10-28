<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Buku Penerimaan Barang</title>
    <style>
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { font-size: 9px; color: #0f172a; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.data th, table.data td { border: 1px solid #cbd5e1; padding: 4px 5px; }
        table.data th { background: #eff6ff; text-transform: uppercase; font-size: 8px; text-align: center; }
        .right { text-align: right; }
        .center { text-align: center; }
        .judul { text-align: center; margin: 0 0 4px; text-transform: uppercase; font-size: 13px; }
        .nomor { text-align: center; margin: 0 0 10px; font-weight: bold; }
        .meta { margin-bottom: 8px; font-size: 9px; }
        .meta td { padding: 2px 0; vertical-align: top; }
        .meta .label { width: 150px; color: #475569; }
        tfoot td { font-weight: bold; }
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

    <h2 class="judul">Buku Penerimaan Barang</h2>
    <p class="nomor">{{ $sumberDana }} — {{ $periode->label() }}</p>

    <table class="data">
        <thead>
            <tr>
                <th style="width:26px;">No</th>
                <th style="width:52px;">Tanggal</th>
                <th>No. BAST</th>
                <th>No. Nota/Invoice</th>
                <th>Penyedia</th>
                <th>Kode Barang</th>
                <th>Nama Barang</th>
                <th style="width:40px;">Jumlah</th>
                <th style="width:40px;">Satuan</th>
                <th style="width:62px;">Harga Satuan</th>
                <th style="width:72px;">Nilai Total</th>
            </tr>
        </thead>
        <tbody>
            @php $totalNilai = 0; @endphp
            @forelse ($items as $i => $item)
                @php $totalNilai += (int) $item['nilai_total']; @endphp
                <tr>
                    <td class="center">{{ $i + 1 }}</td>
                    <td class="center">{{ $item['tanggal'] ?? '-' }}</td>
                    <td>{{ $item['no_bast'] ?? '-' }}</td>
                    <td>{{ $item['no_nota_invoice'] ?? '-' }}</td>
                    <td>{{ $item['penyedia'] ?? '-' }}</td>
                    <td>{{ $item['kode_barang'] ?? '-' }}</td>
                    <td>{{ $item['nama_barang'] ?? '-' }}</td>
                    <td class="center">{{ $item['jumlah'] }}</td>
                    <td class="center">{{ $item['satuan'] ?? '-' }}</td>
                    <td class="right">{{ number_format((int) $item['harga_satuan'], 0, ',', '.') }}</td>
                    <td class="right">{{ number_format((int) $item['nilai_total'], 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="11" class="center" style="color:#64748b;">Tidak ada penerimaan pada periode ini.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="10" class="right">Jumlah</td>
                <td class="right">{{ number_format($totalNilai, 0, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
