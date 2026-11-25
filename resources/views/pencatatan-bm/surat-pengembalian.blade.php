{{--
    Surat Permohonan Pengembalian — narasi dan tata letak persis
    surat_permohonan_kembali.php acuan.
--}}
@php
    $payload = $dokumen->payload ?? [];
    $sekolah = $payload['sekolah'] ?? [];
    $pejabat = $payload['pejabat'] ?? [];
    $penerima = $payload['penerima'] ?? [];
    $units = $payload['units'] ?? [];
    $kepala = $pejabat['kepala_sekolah'] ?? null;
    $tanggal = $dokumen->tanggal;
    $namaPenerima = \App\Support\NamaGelar::format($penerima['nama'] ?? null);
    $namaSekolah = $sekolah['nama_sekolah'] ?? '-';
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

<table class="data" style="line-height: 1.5;">
    <tr>
        <td style="width: 50%; vertical-align: top;">
            <table class="data">
                <tr><td style="width: 26%;">Nomor</td><td style="width: 4%;">:</td><td style="width: 70%;">{{ $dokumen->nomor ?: '..................................................' }}</td></tr>
                <tr><td>Sifat</td><td>:</td><td>Penting</td></tr>
                <tr><td>Lampiran</td><td>:</td><td>-</td></tr>
                <tr><td style="vertical-align: top;">Perihal</td><td style="vertical-align: top;">:</td><td style="text-align: justify;">Permohonan Pengembalian Barang Milik Daerah (BMD)</td></tr>
            </table>
        </td>
        <td style="width: 8%;"></td>
        <td style="width: 42%; vertical-align: top;">
            Kepada,<br>
            <table class="data">
                <tr><td style="width: 15%;">Yth.</td><td style="width: 85%;"><b>Bpk./Ibu {{ $namaPenerima }}</b></td></tr>
            </table>
            di<br>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Tempat
        </td>
    </tr>
</table>

<table style="width: 100%; border-collapse: collapse; margin-top: 6px;">
    <tr>
        <td style="width: 15%;"></td>
        <td style="width: 85%;">

            <p style="text-indent: 35px;">
                Disampaikan dengan hormat, sehubungan dengan telah berakhirnya masa tugas atau jabatan Bapak/Ibu sebagai <b>{{ ($penerima['jabatan'] ?? null) ?: 'Staf Kepegawaian' }}</b> di <b>{{ $namaSekolah }}</b>. Berdasarkan hasil inventarisasi Barang Milik Daerah (BMD) yang dilakukan oleh pengelola sarpras, masih terdapat barang Milik Pemerintah Daerah (BMD) yang tercatat berada dalam tanggungjawab Bapak/Ibu. Berdasarkan data pengelola sarpras, rincian barang yang dimaksud adalah sebagai berikut:
            </p>

            <table class="border" style="margin: 8px 0;">
                <thead>
                <tr>
                    <th style="width: 8%;">No</th>
                    <th style="width: 37%;">Nama Barang</th>
                    <th style="width: 25%;">Kode Barang</th>
                    <th style="width: 15%;">Register</th>
                    <th style="width: 15%;">Jumlah</th>
                </tr>
                </thead>
                <tbody>
                @forelse($units as $i => $unit)
                    <tr>
                        <td style="text-align: center;">{{ $i + 1 }}</td>
                        <td>{{ $unit['nama_barang'] ?? '-' }}</td>
                        <td>{{ $unit['kode_barang'] ?? '-' }}</td>
                        <td style="text-align: center;">{{ $unit['nomor_register'] ?? '-' }}</td>
                        <td style="text-align: center;">1</td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="text-align: center;">Tidak ada data aset terikat.</td></tr>
                @endforelse
                </tbody>
            </table>

            <p style="text-indent: 35px;">
                Sehubungan dengan hal tersebut, kami mohon kepada Bapak/Ibu agar dapat mengembalikan Barang Milik Daerah (BMD) diatas kepada Pengelola Sarpras <b>{{ $namaSekolah }}</b> paling lambat 7 (tujuh) hari kerja sejak dikeluarkannya surat ini.
            </p>

            <p style="text-indent: 35px;">
                Apabila terdapat kendala dalam proses pengembalian, diharapkan Bapak/Ibu segera berkoordinasi dengan
                Pengelola Sarpras agar dapat ditindaklanjuti sesuai ketentuan yang berlaku.
            </p>

            <p style="text-indent: 35px;">
                Demikian surat ini disampaikan, atas perhatian dan kerjasamanya diucapkan terima kasih.
            </p>
        </td>
    </tr>
</table>

<table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
    <tr>
        <td style="width: 45%;"></td>
        <td style="width: 55%; text-align: center;">
            {{ $sekolah['kota_kab'] ?? '-' }}, {{ $tanggal?->translatedFormat('d F Y') ?? '-' }}<br>
            <b>KEPALA SEKOLAH</b><br>
            <b>{{ mb_strtoupper($namaSekolah) }}</b>
            <div style="height: 20mm;"></div>
            <b>{{ \App\Support\NamaGelar::format($kepala['nama'] ?? null) }}</b><br>
            NIP. {{ ($kepala['nip'] ?? null) ?: '-' }}
        </td>
    </tr>
</table>
</body>
</html>
