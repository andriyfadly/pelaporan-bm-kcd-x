<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Label {{ $label['register'] }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; margin: 14px; }
        .label { border: 1px solid #0f172a; border-radius: 4px; padding: 8px; width: 250px; }
        .label h4 { margin: 0 0 2px; font-size: 11px; }
        .label p { margin: 1px 0; font-size: 9px; color: #334155; }
        .qr { margin-top: 6px; }
        .qr svg { width: 110px; height: 110px; }
    </style>
</head>
<body>
<div class="label">
    <h4>{{ $label['nama_barang'] ?? $label['kode_barang'] }}</h4>
    <p>Register: <strong>{{ $label['register'] }}</strong></p>
    <p>Kode: {{ $label['kode_barang'] }}</p>
    <p>Merk: {{ $label['merk_tipe'] ?? '-' }}</p>
    <p>Kondisi: {{ $label['kondisi'] }}</p>
    <p>Ruang: {{ $label['ruang'] ?? '-' }}</p>
    <p>PJ: {{ $label['pegawai'] ?? '-' }}</p>
    <p>{{ $label['sekolah'] ?? '' }}</p>
    <div class="qr">{!! $qrSvg !!}</div>
</div>
</body>
</html>
