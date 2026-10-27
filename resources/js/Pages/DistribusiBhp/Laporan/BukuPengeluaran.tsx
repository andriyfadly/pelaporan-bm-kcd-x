import { Head } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import PeriodeFilter, { periodeQuery, type PeriodeValue } from '@/Components/PeriodeFilter';
import { FileSpreadsheet } from 'lucide-react';

interface PengeluaranRow {
    tanggal: string | null;
    nomor_pengajuan: string | null;
    nama_barang: string;
    satuan: string | null;
    jumlah: number;
    keperluan: string | null;
}

export default function BukuPengeluaran({
    jenisLabel,
    sumberDana,
    periode,
    items,
}: {
    jenisLabel: string;
    sumberDana: string;
    periode: PeriodeValue;
    items: PengeluaranRow[];
}) {
    return (
        <AppLayout title={jenisLabel}>
            <Head title={`${jenisLabel} | BHP`} />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-lg font-bold text-slate-900">{jenisLabel} — {sumberDana}</h1>
                        <p className="text-sm text-slate-500">Transaksi disetujui pada {periode.label}.</p>
                    </div>
                    <div className="flex flex-wrap items-end gap-3">
                        <PeriodeFilter path="/distribusi-bhp/laporan/buku-pengeluaran" periode={periode} sumberDana={sumberDana} />
                        <a
                            href={`/distribusi-bhp/laporan/export?sumber_dana=${sumberDana}&jenis=pengeluaran&${periodeQuery(periode)}`}
                            className="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold bg-emerald-600 text-white hover:bg-emerald-700"
                        >
                            <FileSpreadsheet className="w-4 h-4" /> Export
                        </a>
                    </div>
                </div>

                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 text-slate-500 uppercase text-xs">
                            <tr>
                                <th className="text-left py-3 px-4">Tanggal</th>
                                <th className="text-left py-3 px-4">No Pengajuan</th>
                                <th className="text-left py-3 px-4">Barang</th>
                                <th className="text-right py-3 px-4">Jumlah</th>
                                <th className="text-left py-3 px-4">Keperluan</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {items.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="py-10 text-center text-slate-400 text-xs">
                                        Belum ada pengeluaran pada periode ini.
                                    </td>
                                </tr>
                            ) : (
                                items.map((row, i) => (
                                    <tr key={i}>
                                        <td className="py-3 px-4">{row.tanggal ?? '-'}</td>
                                        <td className="py-3 px-4 font-mono text-slate-600">{row.nomor_pengajuan ?? '-'}</td>
                                        <td className="py-3 px-4">
                                            {row.nama_barang} <span className="text-xs text-slate-400">({row.satuan ?? '-'})</span>
                                        </td>
                                        <td className="py-3 px-4 text-right">{row.jumlah}</td>
                                        <td className="py-3 px-4">{row.keperluan ?? '-'}</td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
