@php
    // Logo provinsi selalu ikut tercetak: sebelumnya partial hanya merender
    // gambar bila pemanggil mengirim `$logo`, dan tidak ada controller yang
    // mengirimnya sehingga semua KOP tampil tanpa logo. Data URI dipakai agar
    // tidak bergantung pada chroot dompdf.
    $logoSrc = $logo ?? null;
    if (! $logoSrc && is_file(public_path('images/logo-jabar.png'))) {
        $logoSrc = 'data:image/png;base64,'.base64_encode((string) file_get_contents(public_path('images/logo-jabar.png')));
    }
@endphp
<table class="kop">
    <tr>
        <td class="kop-logo">
            @if($logoSrc)
                <img src="{{ $logoSrc }}" alt="Logo Jawa Barat">
            @endif
        </td>
        <td class="kop-teks">
            <h1>PEMERINTAH PROVINSI JAWA BARAT</h1>
            <h2>DINAS PENDIDIKAN</h2>
            <h2>CABANG DINAS PENDIDIKAN WILAYAH X</h2>
            <div class="kop-sekolah">{{ $sekolah }}</div>
            <p>
                {{ $alamat ?? '' }}
                @if($telepon ?? null) Tlp: {{ $telepon }} @endif
            </p>
            @if(($website ?? null) || ($email ?? null))
                <p>
                    @if($website ?? null) Website: {{ $website }} @endif
                    @if($email ?? null) E-mail: {{ $email }} @endif
                    @if($kodePos ?? null) Kode Pos: {{ $kodePos }} @endif
                </p>
            @endif
        </td>
        <td class="kop-logo"></td>
    </tr>
</table>
<div class="kop-garis"></div>
<style>
    table.kop { width: 100%; border-collapse: collapse; text-align: center; }
    table.kop td { vertical-align: middle; padding: 0; }
    .kop-logo { width: 27mm; }
    .kop-logo img { width: 25mm; height: 30mm; }
    .kop-teks { font-family: 'Times New Roman', 'Times', serif; }
    /* Baris pertama menempel garis pembatas, jadi tidak perlu margin bawah. */
    .kop-teks h1 { font-family: 'Times New Roman', 'Times', serif; font-size: 15px; font-weight: bold; margin: 0; text-transform: uppercase; }
    .kop-teks h2 { font-family: 'Times New Roman', 'Times', serif; font-size: 13px; font-weight: normal; margin: 1px 0 0; text-transform: uppercase; }
    .kop-sekolah { font-family: 'Times New Roman', 'Times', serif; font-size: 16px; font-weight: bold; margin-top: 4px; text-transform: uppercase; }
    .kop-teks p { font-family: 'Times New Roman', 'Times', serif; font-size: 10px; margin: 1px 0 0; }
    /* Garis ganda: tebal di atas, tipis di bawah, seperti kop baku. */
    .kop-garis { border-top: 2px solid #000; border-bottom: 1px solid #000; height: 1mm; margin-bottom: 12px; }
</style>
