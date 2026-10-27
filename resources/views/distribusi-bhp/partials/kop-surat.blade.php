<div class="kop">
    @isset($logo)
        <img src="{{ $logo }}" alt="Logo" style="position: absolute; left: 15mm; top: 10mm; width: 25mm; height: 30mm;">
    @endisset
    <h1>PEMERINTAH PROVINSI JAWA BARAT</h1>
    <h2>DINAS PENDIDIKAN</h2>
    <h2 style="font-size:11px;font-weight:normal;">CABANG DINAS PENDIDIKAN WILAYAH X</h2>
    <h1 style="font-size:14px;margin-top:4px;">{{ $sekolah }}</h1>
    <p style="font-size:9px;margin:2px 0 0;color:#475569;">
        {{ $alamat ?? '' }}
        @if($telepon ?? null) Tlp: {{ $telepon }} @endif
    </p>
    @if($website ?? null || $email ?? null)
        <p style="font-size:9px;margin:0;color:#475569;">
            @if($website ?? null) Website: {{ $website }} @endif
            @if($email ?? null) E-mail: {{ $email }} @endif
            @if($kodePos ?? null) Kode Pos: {{ $kodePos }} @endif
        </p>
    @endif
</div>
<style>
    .kop { text-align: center; border-bottom: 2px solid #1e3a8a; padding-bottom: 8px; margin-bottom: 14px; position: relative; }
    .kop h1 { font-size: 14px; margin: 0; text-transform: uppercase; }
    .kop h2 { font-size: 11px; margin: 1px 0 0; font-weight: normal; }
</style>