export const BULAN_LIST = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
] as const;

export function getNamaBulan(bulan: number): string {
    return BULAN_LIST[bulan - 1] ?? `Bulan ${bulan}`;
}

export function formatRupiah(value: number | string | null | undefined): string {
    const num = Number(value) || 0;
    return `Rp ${num.toLocaleString('id-ID')}`;
}

/**
 * Tanggal dari backend bisa berupa ISO penuh (`2026-06-25T00:00:00.000000Z`)
 * atau sudah `Y-m-d`. Ambil 10 karakter pertama saja supaya `<input type="date">`
 * punya nilai valid. Untuk tampilan tabel pakai {@link formatTanggalTabel}.
 */
export function formatTanggal(value: string | null | undefined, kosong = '-'): string {
    if (!value) return kosong;
    return value.slice(0, 10);
}

/**
 * Tanggal untuk kolom tabel: `dd-mm-yyyy`, sama dengan template cetak legacy.
 * Tanggal ISO mentah (`2026-06-25`) sulit dibaca operator dan berbeda dari PDF.
 */
export function formatTanggalTabel(value: string | null | undefined, kosong = '-'): string {
    const iso = formatTanggal(value, '');
    if (iso.length !== 10) return kosong;
    const [tahun, bulan, hari] = iso.split('-');
    return `${hari}-${bulan}-${tahun}`;
}

/**
 * Tanggal hari ini menurut zona waktu lokal, bukan UTC. `toISOString()` di
 * WIB sebelum pukul 07:00 masih tanggal kemarin.
 */
export function tanggalHariIni(): string {
    const now = new Date();
    const geser = new Date(now.getTime() - now.getTimezoneOffset() * 60_000);

    return geser.toISOString().slice(0, 10);
}
