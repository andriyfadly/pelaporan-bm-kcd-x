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
