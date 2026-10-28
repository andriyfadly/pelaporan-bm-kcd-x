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
 * atau sudah `Y-m-d`. Ambil 10 karakter pertama saja supaya tabel tidak
 * menampilkan timestamp, dan supaya `<input type="date">` punya nilai valid.
 */
export function formatTanggal(value: string | null | undefined, kosong = '-'): string {
    if (!value) return kosong;
    return value.slice(0, 10);
}
