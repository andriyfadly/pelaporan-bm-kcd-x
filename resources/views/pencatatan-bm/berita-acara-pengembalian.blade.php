{{--
    Berita Acara Pengembalian — narasi persis cetak_pengembalian.php acuan.
--}}
@php
    $payload = $dokumen->payload ?? [];
    $sekolah = $payload['sekolah'] ?? [];
    $pejabat = $payload['pejabat'] ?? [];
    $penerima = $payload['penerima'] ?? [];
    $units = $payload['units'] ?? (isset($payload['unit']) ? [$payload['unit']] : []);
    $wakasek = $pejabat['wakasek_sarpras'] ?? null;
    $tanggal = $dokumen->tanggal;
    $namaPenerima = \App\Support\NamaGelar::format($penerima['nama'] ?? null);
    $labelPenerima = ['B' => 'Baik', 'RR' => 'Rusak Ringan', 'RB' => 'Rusak Berat', 'H' => 'Hilang'];
    $nomorBast = $payload['nomor_bast'] ?? null;
    $daftarBast = collect($payload['bast_per_unit'] ?? [])
        ->filter(fn ($nomor) => is_string($nomor) && $nomor !== '')
        ->unique()
        ->values();
    $nomorBastTampil = $nomorBast ?: ($daftarBast->isNotEmpty() ? $daftarBast->implode(', ') : null);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $judul }} {{ $dokumen->nomor ?? '' }}</title>
    @include('pencatatan-bm.partials.gaya')
</head>
<body>
@include('pencatatan-bm.partials.kop', ['sekolah' => $sekolah])

<div class="judul-dokumen">
    <div class="judul-baris">BERITA ACARA PENGEMBALIAN</div>
    <div class="judul-baris">BARANG MILIK DAERAH (BMD)</div>
    <div>Nomor: {{ $dokumen->nomor ?? '.........' }}</div>
</div>

<p style="margin-top: 5px;">
    Pada hari ini <b>{{ $tanggal?->translatedFormat('l') ?? '-' }}</b>
    tanggal <b>{{ \App\Support\Terbilang::angka((int) ($tanggal?->day ?? 0)) }}</b>
    bulan <b>{{ $tanggal?->translatedFormat('F') ?? '-' }}</b>
    tahun <b>{{ \App\Support\Terbilang::angka((int) ($tanggal?->year ?? 0)) }}</b>
    bertempat di {{ $sekolah['nama_sekolah'] ?? '-' }}, yang bertandatangan dibawah ini:
</p>

<br>

<table class="data">
    <tr>
        <td style="width: 5%;">1.</td>
        <td style="width: 28%;">Nama</td>
        <td style="width: 2%;">:</td>
        <td style="width: 65%;"><b>{{ mb_strtoupper((string) ($wakasek['nama'] ?? '-')) }}</b></td>
    </tr>
    <tr><td></td><td>NIP</td><td>:</td><td>{{ $wakasek['nip'] ?? '-' }}</td></tr>
    <tr><td></td><td>Pangkat/Gol.</td><td>:</td><td>{{ $wakasek['pangkat_golongan'] ?? '-' }}</td></tr>
    <tr><td></td><td>Jabatan</td><td>:</td><td>Wakasek Sarpras</td></tr>
</table>
<p>
    Dalam hal ini bertindak sebagai Pengelola Sarpras dan selanjutnya disebut sebagai <b>PIHAK KESATU</b>.
</p>

<table class="data">
    <tr>
        <td style="width: 5%;">2.</td>
        <td style="width: 28%;">Nama</td>
        <td style="width: 2%;">:</td>
        <td style="width: 65%;"><b>{{ $namaPenerima }}</b></td>
    </tr>
    <tr><td></td><td>NIP</td><td>:</td><td>{{ $penerima['nip'] ?? '-' }}</td></tr>
    <tr><td></td><td>Pangkat/Gol.</td><td>:</td><td>{{ $penerima['pangkat_golongan'] ?? '-' }}</td></tr>
    <tr>
        <td></td><td>Jabatan</td><td>:</td>
        <td>{{ ($penerima['jabatan'] ?? null) ? ucwords(mb_strtolower((string) $penerima['jabatan'])) : '-' }}</td>
    </tr>
</table>
<p>
    Dalam hal ini bertindak sebagai Pemegang Barang Inventaris pada Pengguna Barang dan selanjutnya disebut sebagai <b>PIHAK KEDUA</b>.
</p>

<p>
    Dengan ini menerangkan bahwa <b>PIHAK KESATU</b> telah menerima pengembalian Barang Milik Daerah (BMD) dari
    <b>PIHAK KEDUA</b> sesuai Berita Acara Penyaluran nomor:
    {{ $nomorBastTampil ?: '........./07/'.\Illuminate\Support\Carbon::parse($dokumen->tanggal)->year.'/AR.03.05-CADISDIK.WIL-X' }}
    dikarenakan telah berakhirnya masa kerja, masa jabatan, atau berakhirnya waktu penggunaan <b>PIHAK KEDUA</b>
    yang selanjutnya akan dilakukan pencatatan kembali ke dalam administrasi dengan rincian hasil pemeriksaan
    <b>PIHAK KESATU</b> sebagai berikut:
</p>

@foreach($units as $unit)
    <table class="data rincian">
        <tr><td style="width: 32%;">Nama Barang</td><td style="width: 2%;">:</td><td style="width: 66%;">{{ $unit['nama_barang'] ?? '-' }}</td></tr>
        <tr><td>Merk/Spesifikasi</td><td>:</td><td>{{ $unit['merk_tipe'] ?? '-' }}</td></tr>
        <tr><td>Tahun Pengadaan</td><td>:</td><td>{{ $unit['tahun_perolehan'] ?? '-' }}</td></tr>
        <tr><td>Kode Barang</td><td>:</td><td>{{ $unit['kode_barang'] ?? '-' }}</td></tr>
        <tr><td>Register</td><td>:</td><td>{{ $unit['nomor_register'] ?? '-' }}</td></tr>
        <tr>
            <td>Kondisi Barang</td><td>:</td>
            <td><b>{{ $labelPenerima[$unit['kondisi'] ?? ''] ?? ($unit['kondisi'] ?? '-') }}</b></td>
        </tr>
        <tr><td>Keterangan</td><td>:</td><td>{{ $unit['keterangan'] ?? '-' }}</td></tr>
    </table>
@endforeach

<p>
    Apabila terdapat kerusakan, kekurangan, atau kehilangan barang, maka akan ditindaklanjuti sesuai pengelolaan
    Barang Milik Daerah (BMD) yang berlaku. Dengan ditandatanganinya Berita Acara ini, tanggungjawab atas Barang
    Milik Daerah (BMD) tersebut beralih dan dibebankan kembali kepada <b>PIHAK KESATU</b> sebagai Pengelola Sarpras
    untuk selanjutnya dilakukan penatausahaan sesuai ketentuan.
</p>

<p>
    Demikian Berita Acara Pengembalian Barang Milik Daerah (BMD) ini dibuat dalam rangkap 2 (dua) untuk digunakan
    sebagaimana mestinya.
</p>

@include('pencatatan-bm.partials.ttd', [
    'kiri' => ['label' => 'PIHAK KEDUA', 'nama' => $namaPenerima, 'nip' => $penerima['nip'] ?? ''],
    'kanan' => ['label' => 'PIHAK PERTAMA', 'nama' => mb_strtoupper((string) ($wakasek['nama'] ?? '-')), 'nip' => $wakasek['nip'] ?? ''],
])
</body>
</html>
