{{--
    Kartu barang hasil pindai QR, mengikuti `salur.php` acuan: kop sekolah,
    kode barang + register besar, identitas aset, dan foto barang.
--}}
@php
    $kodeBarang = $unit->kode_barang;
    $rekeningAset = $unit->jenis_aset ?? $unit->katalog?->jenis_aset;
    $foto = $unit->foto_path
        ? 'data:'.(pathinfo($unit->foto_path, PATHINFO_EXTENSION) === 'png' ? 'image/png' : 'image/jpeg')
            .';base64,'.base64_encode((string) @file_get_contents(storage_path('app/public/'.$unit->foto_path)))
        : null;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kartu Barang — {{ $unit->nama_barang }}</title>
    @include('pencatatan-bm.partials.gaya')
    <style>
        .kode-besar { font-size: 18pt; font-weight: bold; }
        table.identitas { width: 100%; border-collapse: collapse; margin-top: 6px; font-size: 11pt; }
        table.identitas td { padding: 3px 0; vertical-align: top; }
        .foto-kotak { width: 100%; height: 60mm; border: 1px dashed #000; text-align: center; margin-top: 8px; }
        .foto-kotak img { max-width: 100%; max-height: 60mm; }
        .foto-kosong { padding-top: 26mm; font-style: italic; }
    </style>
</head>
<body>
@include('pencatatan-bm.partials.kop', ['sekolah' => [
    'nama_sekolah' => $unit->sekolah?->nama_sekolah,
    'alamat' => $unit->sekolah?->alamat,
    'email' => $unit->sekolah?->email,
    'kota_kab' => $unit->sekolah?->kota_kab,
    'kode_pos' => $unit->sekolah?->kode_pos,
]])

<div class="kode-besar">
    KODE BARANG : {{ $kodeBarang }}<br>
    REGISTER : {{ $unit->nomor_register }}
</div>
<p style="margin: 4px 0 0;">REKENING ASET : {{ $rekeningAset ?: '-' }}</p>

<table class="identitas">
    <tr><td style="width: 32%;">Nama Barang</td><td style="width: 2%;">:</td><td>{{ $unit->nama_barang ?? '-' }}</td></tr>
    <tr><td>Type / Merk</td><td>:</td><td>{{ $unit->merk_tipe ?? '-' }}</td></tr>
    <tr><td>Bulan Perolehan</td><td>:</td><td>{{ $unit->bulan_perolehan ?? '-' }}</td></tr>
    <tr><td>Tahun Perolehan</td><td>:</td><td>{{ $unit->tahun_perolehan }}</td></tr>
    <tr>
        <td>Umur Ekonomis</td><td>:</td>
        <td>{{ $unit->katalog?->umur_ekonomis ? $unit->katalog->umur_ekonomis.' Tahun' : '-' }}</td>
    </tr>
</table>

<div class="foto-kotak">
    @if($foto)
        <img src="{{ $foto }}" alt="Foto {{ $unit->nama_barang }}">
    @else
        <p class="foto-kosong">Foto tidak tersedia</p>
    @endif
</div>
</body>
</html>
