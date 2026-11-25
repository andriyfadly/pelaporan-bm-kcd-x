{{--
    Tanda tangan dua kolom. Dipakai BAST dan Berita Acara Pengembalian.
    Parameter: $kiri = ['label' => ..., 'nama' => ..., 'nip' => ...], $kanan idem.
--}}
<table class="ttd">
    <tr>
        <td class="ttd-kolom" style="width: 50%;">
            <div class="ttd-label">{{ $kiri['label'] ?? '' }}</div>
            <div class="ttd-ruang"></div>
            <div class="ttd-nama">{{ $kiri['nama'] ?? '-' }}</div>
            <div class="ttd-nip">NIP. {{ ($kiri['nip'] ?? null) ?: '-' }}</div>
        </td>
        <td class="ttd-jarak"></td>
        <td class="ttd-kolom" style="width: 40%;">
            <div class="ttd-label">{{ $kanan['label'] ?? '' }}</div>
            <div class="ttd-ruang"></div>
            <div class="ttd-nama">{{ $kanan['nama'] ?? '-' }}</div>
            <div class="ttd-nip">NIP. {{ ($kanan['nip'] ?? null) ?: '-' }}</div>
        </td>
    </tr>
</table>
<style>
    table.ttd { width: 100%; border-collapse: collapse; margin-top: 6px; }
    table.ttd td { vertical-align: top; padding: 0; }
    .ttd-kolom { text-align: left; }
    .ttd-jarak { width: 10%; }
    .ttd-label { font-weight: bold; }
    .ttd-ruang { height: 22mm; }
    .ttd-nama { font-weight: bold; }
</style>
