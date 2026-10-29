import { router } from '@inertiajs/react';
import { BULAN_LIST } from '@/Utils/format';

export interface PeriodeValue {
    tahun: number;
    bulan: number;
    label: string;
}

/**
 * Pemilih bulan/tahun laporan BHP. Satu-satunya tempat parameter `bulan` dan
 * `tahun` dibentuk, supaya halaman, tabel, dan tombol export memakai periode
 * yang sama.
 */
export default function PeriodeFilter({
    path,
    periode,
    sumberDana,
    extra = {},
    className = '',
}: {
    path: string;
    periode: PeriodeValue;
    sumberDana: string;
    extra?: Record<string, string>;
    className?: string;
}) {
    const tahunIni = new Date().getFullYear();
    const daftarTahun = Array.from({ length: 5 }, (_, i) => tahunIni - i);

    const pindah = (patch: { tahun?: number; bulan?: number }) => {
        router.get(
            path,
            { sumber_dana: sumberDana, tahun: periode.tahun, bulan: periode.bulan, ...extra, ...patch },
            { preserveState: true },
        );
    };

    return (
        <div className={`flex w-full flex-wrap items-end gap-3 sm:w-auto ${className}`}>
            <div className="flex-1 sm:flex-none">
                <label htmlFor="filter-bulan" className="block text-xs font-bold uppercase text-slate-500 mb-1">Bulan</label>
                <select
                    id="filter-bulan"
                    value={periode.bulan}
                    onChange={(e) => pindah({ bulan: Number(e.target.value) })}
                    className="min-h-10 w-full px-3 border border-slate-300 rounded-xl text-sm"
                >
                    {BULAN_LIST.map((nama, i) => (
                        <option key={nama} value={i + 1}>{nama}</option>
                    ))}
                </select>
            </div>
            <div className="flex-1 sm:flex-none">
                <label htmlFor="filter-tahun" className="block text-xs font-bold uppercase text-slate-500 mb-1">Tahun</label>
                <select
                    id="filter-tahun"
                    value={periode.tahun}
                    onChange={(e) => pindah({ tahun: Number(e.target.value) })}
                    className="min-h-10 w-full px-3 border border-slate-300 rounded-xl text-sm"
                >
                    {daftarTahun.map((t) => (
                        <option key={t} value={t}>{t}</option>
                    ))}
                </select>
            </div>
        </div>
    );
}

/** Query string periode untuk tautan export/print biasa (bukan router.get). */
export function periodeQuery(periode: PeriodeValue): string {
    return `tahun=${periode.tahun}&bulan=${periode.bulan}`;
}
