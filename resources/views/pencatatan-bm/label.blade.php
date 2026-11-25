{{--
    Label aset 2-up, mengikuti cetak_label.php acuan: kode lokasi 11.01.32.{wilayah}.000001.{sub}.{tahun},
    kode barang + register 4 digit, uraian dari katalog kode barang, QR ke kartu barang.
--}}
@php
    $logoSrc = is_file(public_path('images/logo-jabar.png'))
        ? 'data:image/png;base64,'.base64_encode((string) file_get_contents(public_path('images/logo-jabar.png')))
        : null;

    $kodeWilayah = trim((string) ($sekolah->kode_wilayah ?? '')) ?: '00';
    $kodeSub = str_pad(trim((string) ($sekolah->kode_sub_pengguna ?? '')) ?: '000001', 6, '0', STR_PAD_LEFT);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Label Aset — {{ $sekolah->nama_sekolah }}</title>
    <style>
        @page { size: 21.5cm 33cm portrait; margin: 0.7cm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #000; margin: 0; }
        table.label-row { width: 100%; border-collapse: collapse; page-break-inside: avoid; }
        table.label-row td { width: 50%; vertical-align: top; padding: 0 4px 0 0; }
        table.label-row td + td { padding: 0 0 0 4px; border-left: 1px dashed #000; }
        .label-box { height: 80px; border: 1px solid #000; display: table; width: 100%; }
        .label-box > div { display: table-cell; vertical-align: middle; }
        .label-logo { width: 74px; text-align: center; }
        .label-logo img { width: 68px; }
        .label-isi { padding: 0 4px; }
        .label-qr { width: 74px; text-align: center; }
        .label-qr img { width: 68px; height: 68px; }
        .kode1, .kode2 { font-size: 9px; margin: 0; }
        .garis { border-top: 2px solid #000; margin: 2px 0; }
        .uraian { font-size: 9px; }
        .jarak { height: 6px; }
    </style>
</head>
<body>
@foreach($units->chunk(2) as $pasangan)
    <table class="label-row">
        <tr>
            @foreach($pasangan as $unit)
                @php
                    $kode1 = sprintf('11.01.32.%s.000001.%s.%s', $kodeWilayah, $kodeSub, $unit->tahun_perolehan);
                    $kode2 = $unit->kode_barang.'.'.str_pad((string) (int) $unit->nomor_register, 4, '0', STR_PAD_LEFT);
                    $uraian = $label[$unit->id]['uraian'] ?? ($unit->nama_barang ?? '-');
                    $qrSvg = $label[$unit->id]['qr'] ?? null;
                @endphp
                <td>
                    <div class="label-box">
                        <div class="label-logo">
                            @if($logoSrc)
                                <img src="{{ $logoSrc }}" alt="Logo">
                            @endif
                        </div>
                        <div class="label-isi">
                            <div class="kode1">{{ $kode1 }}</div>
                            <div class="garis"></div>
                            <div class="kode2">{{ $kode2 }}</div>
                            <div class="garis"></div>
                            <div class="uraian">{{ $uraian }}</div>
                        </div>
                        <div class="label-qr">
                            @if($qrSvg)
                                <img src="data:image/svg+xml;base64,{{ base64_encode($qrSvg) }}" alt="QR {{ $unit->nomor_register }}">
                            @endif
                        </div>
                    </div>
                </td>
            @endforeach
            @if($pasangan->count() === 1)
                <td></td>
            @endif
        </tr>
    </table>
    <div class="jarak"></div>
@endforeach
</body>
</html>
