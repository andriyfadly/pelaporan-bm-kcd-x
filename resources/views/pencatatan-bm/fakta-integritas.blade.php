{{--
    Pakta Integritas — narasi dan urutan persis fakta_pdf.php acuan. Tanpa baris
    Nomor (nomor tetap tersimpan di dokumen untuk rekap).
--}}
@php
    $payload = $dokumen->payload ?? [];
    $sekolah = $payload['sekolah'] ?? [];
    $pejabat = $payload['pejabat'] ?? [];
    $pegawai = $payload['pegawai'] ?? [];
    $units = $payload['units'] ?? [];
    $wakasek = $pejabat['wakasek_sarpras'] ?? null;
    $kepala = $pejabat['kepala_sekolah'] ?? null;
    $namaPenerima = \App\Support\NamaGelar::format($pegawai['nama_lengkap'] ?? null);
    $labelPenerima = ['B' => 'Baik', 'RR' => 'Rusak Ringan', 'RB' => 'Rusak Berat', 'H' => 'Hilang'];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $judul }}</title>
    @include('pencatatan-bm.partials.gaya')
</head>
<body>
@include('pencatatan-bm.partials.kop', ['sekolah' => $sekolah])

<div class="judul-dokumen">
    <div class="judul-baris" style="font-size: 14pt;">PAKTA INTEGRITAS</div>
    <div class="judul-baris" style="font-size: 14pt;">PEMANFAATAN INVENTARIS BARANG MILIK DAERAH (BMD)</div>
</div>

<p style="margin-top: 10px;">Saya yang bertandatangan dibawah ini:</p>

<table class="data">
    <tr>
        <td style="width: 30%;">Nama</td><td style="width: 3%;">:</td>
        <td style="width: 67%;"><b>{{ $namaPenerima }}</b></td>
    </tr>
    <tr><td>NIP</td><td>:</td><td>{{ $pegawai['nip'] ?? '-' }}</td></tr>
    <tr><td>Pangkat/Gol.</td><td>:</td><td>{{ $pegawai['pangkat_golongan'] ?? '-' }}</td></tr>
    <tr>
        <td>Sub. Bidang</td><td>:</td>
        <td>{{ ($pegawai['jabatan'] ?? null) ? ucwords(mb_strtolower((string) $pegawai['jabatan'])) : '-' }}</td>
    </tr>
</table>

<p>Dengan ini menyatakan bahwa saya:</p>

<table class="data">
    <tr>
        <td style="width: 4%;">1.</td>
        <td style="width: 96%;" colspan="3">
            Akan bertanggungjawab atas Inventaris Barang Milik Daerah (BMD) dengan seluruh resiko yang melekat
            terhadap Barang Inventaris tersebut, dengan rincian barang sebagai berikut:
        </td>
    </tr>
    @foreach($units as $unit)
        <tr>
            <td></td><td style="width: 26%;">Nama Barang</td><td style="width: 3%;">:</td>
            <td style="width: 67%;">{{ $unit['nama_barang'] ?? '-' }}</td>
        </tr>
        <tr><td></td><td>Merk/Spesifikasi</td><td>:</td><td>{{ $unit['merk_tipe'] ?? '-' }}</td></tr>
        <tr><td></td><td>Tahun Pengadaan</td><td>:</td><td>{{ $unit['tahun_perolehan'] ?? '-' }}</td></tr>
        <tr><td></td><td>Register</td><td>:</td><td>{{ $unit['nomor_register'] ?? '-' }}</td></tr>
        <tr>
            <td></td><td>Kondisi Barang</td><td>:</td>
            <td><b>{{ $labelPenerima[$unit['kondisi'] ?? ''] ?? ($unit['kondisi'] ?? '-') }}</b></td>
        </tr>
    @endforeach
    <tr>
        <td>2.</td>
        <td colspan="3">Akan memanfaatkan Inventaris BMD sesuai Tugas, Pokok dan Fungsi Jabatan yang melekat;</td>
    </tr>
    <tr>
        <td>3.</td>
        <td colspan="3">Akan menjaga dan memelihara Inventaris BMD sesuai umur ekonomis BMD tersebut;</td>
    </tr>
    <tr>
        <td>4.</td>
        <td colspan="3">
            Akan segera mengembalikan Inventaris BMD setelah jangka waktu penggunaan berakhir atau terjadi mutasi,
            yang dituangkan dalam <b>Berita Acara Pengembalian</b>;
        </td>
    </tr>
    <tr>
        <td>5.</td>
        <td colspan="3">
            Apabila Inventaris hilang sebagai akibat dari kesalahan atau kelalaian atau penyimpangan dari ketentuan,
            maka saya sebagai penanggung jawab Inventaris BMD siap dikenakan sanksi sesuai dengan ketentuan
            peraturan perundang-undangan.
        </td>
    </tr>
</table>

<p>
    Demikian Pakta Integritas Pemanfaatan Barang Milik Daerah (BMD) ini dibuat dalam rangkap 2 (dua) untuk
    dipergunakan sebagaimana mestinya.
</p>

<br><br>

<table class="data" style="text-align: center;">
    <tr>
        <td style="width: 50%;"></td>
        <td style="width: 50%;">Penanggungjawab Inventaris</td>
    </tr>
    <tr>
        <td>Wakasek Sarpras</td>
        <td>Barang Milik Daerah (BMD)</td>
    </tr>
    <tr>
        <td>
            <div style="height: 22mm;"></div>
            <b>{{ \App\Support\NamaGelar::format($wakasek['nama'] ?? null) }}</b><br>
            NIP. {{ ($wakasek['nip'] ?? null) ?: '-' }}
        </td>
        <td>
            <div style="height: 22mm;"></div>
            <b>{{ $namaPenerima }}</b><br>
            NIP. {{ ($pegawai['nip'] ?? null) ?: '-' }}
        </td>
    </tr>
</table>

<br><br>

<div style="text-align: center;">
    Mengetahui,<br>
    Kepala Sekolah
    <div style="height: 22mm;"></div>
    <b>{{ \App\Support\NamaGelar::format($kepala['nama'] ?? null) }}</b><br>
    NIP. {{ ($kepala['nip'] ?? null) ?: '-' }}
</div>
</body>
</html>
