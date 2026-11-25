{{--
    Kop surat dokumen inventaris, sama untuk BAST, Pakta Integritas, Berita Acara
    Pengembalian, dan Surat Permohonan. Teks provinsi/dinas mengikuti acuan
    (hardcoded), sedangkan identitas sekolah diambil dari snapshot payload.
--}}
@php
    $logoSrc = null;
    if (is_file(public_path('images/logo-jabar.png'))) {
        $logoSrc = 'data:image/png;base64,'.base64_encode((string) file_get_contents(public_path('images/logo-jabar.png')));
    }

    $namaSekolah = mb_strtoupper((string) ($sekolah['nama_sekolah'] ?? '-'));
    $alamat = trim((string) ($sekolah['alamat'] ?? ''));
    $email = trim((string) ($sekolah['email'] ?? ''));
    $kota = mb_strtoupper(trim((string) ($sekolah['kota_kab'] ?? '')));
    $kodePos = trim((string) ($sekolah['kode_pos'] ?? ''));
@endphp
<table class="kop">
    <tr>
        <td class="kop-logo">
            @if($logoSrc)
                <img src="{{ $logoSrc }}" alt="Logo Jawa Barat">
            @endif
        </td>
        <td class="kop-teks">
            <div class="kop-baris">PEMERINTAH DAERAH PROVINSI JAWA BARAT</div>
            <div class="kop-baris">DINAS PENDIDIKAN</div>
            <div class="kop-baris">CABANG DINAS PENDIDIKAN WILAYAH X</div>
            <div class="kop-sekolah">{{ $namaSekolah }}</div>
            <div class="kop-kontak">
                {{ $alamat }}@if($email !== '') &nbsp; Email: {{ $email }}@endif
            </div>
            <div class="kop-kota">{{ $kota }}@if($kodePos !== '') - {{ $kodePos }}@endif</div>
        </td>
    </tr>
</table>
<div class="kop-garis-atas"></div>
<div class="kop-garis-bawah"></div>
<style>
    table.kop { width: 100%; border-collapse: collapse; }
    table.kop td { vertical-align: middle; padding: 0; }
    .kop-logo { width: 15%; text-align: center; }
    .kop-logo img { height: 22mm; }
    .kop-teks { width: 85%; text-align: center; line-height: 1.15; }
    .kop-baris { font-size: 13.3px; }
    .kop-sekolah { font-size: 13.3px; font-weight: bold; }
    .kop-kontak { font-size: 9.5px; }
    .kop-kota { font-size: 9.5px; font-weight: bold; }
    /* Dua garis kop: tebal di atas, tipis di bawah. */
    .kop-garis-atas { border-top: 2.1px solid #000; margin-top: 6px; }
    .kop-garis-bawah { border-top: 0.8px solid #000; margin-top: 1px; margin-bottom: 12px; }
</style>
