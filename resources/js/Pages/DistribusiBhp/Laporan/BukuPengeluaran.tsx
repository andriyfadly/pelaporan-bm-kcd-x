import { Head } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import EmptyState from '@/Components/EmptyState';
import PeriodeFilter, { periodeQuery, type PeriodeValue } from '@/Components/PeriodeFilter';
import { FileSpreadsheet } from 'lucide-react';
import { formatTanggalTabel } from '@/Utils/format';

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
    const prefix = `/distribusi-bhp/${sumberDana.toLowerCase()}`;

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
                        <PeriodeFilter path={`${prefix}/laporan/buku-pengeluaran`} periode={periode} sumberDana={sumberDana} />
                        <a
                            href={`${prefix}/laporan/export?jenis=pengeluaran&${periodeQuery(periode)}`}
                            className="inline-flex min-h-10 items-center justify-center gap-2 px-4 rounded-xl text-sm font-semibold bg-emerald-600 text-white hover:bg-emerald-700"
                        >
                            <FileSpreadsheet className="w-4 h-4" /> Export
                        </a>
                    </div>
                </div>

                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    {items.length === 0 ? (
                        <EmptyState
                            title="Belum Ada Pengeluaran"
                            description={`Belum ada transaksi disetujui pada ${periode.label}.`}
                        />
                    ) : (
                        <>
                            <ul className="md:hidden divide-y divide-slate-100">
                                {items.map((row, i) => (
                                    <li key={i} className="p-4 space-y-1">
                                        <div className="flex items-start justify-between gap-2">
                                            <span className="font-semibold text-slate-800 break-words">
                                                {row.nama_barang} <span className="text-xs font-normal text-slate-500">({row.satuan ?? '-'})</span>
                                            </span>
                                            <span className="shrink-0 text-sm font-bold text-slate-900">{row.jumlah}</span>
                                        </div>
                                        <div className="text-xs text-slate-500">
                                            {formatTanggalTabel(row.tanggal)} · {row.nomor_pengajuan ?? '-'}
                                        </div>
                                        {row.keperluan && <div className="text-xs text-slate-500">{row.keperluan}</div>}
                                    </li>
                                ))}
                            </ul>

                            <div className="hidden md:block overflow-x-auto">
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
                                        {items.map((row, i) => (
                                            <tr key={i}>
                                                <td className="py-3 px-4">{formatTanggalTabel(row.tanggal)}</td>
                                                <td className="py-3 px-4 font-mono text-slate-600">{row.nomor_pengajuan ?? '-'}</td>
                                                <td className="py-3 px-4">
                                                    {row.nama_barang} <span className="text-xs text-slate-500">({row.satuan ?? '-'})</span>
                                                </td>
                                                <td className="py-3 px-4 text-right">{row.jumlah}</td>
                                                <td className="py-3 px-4">{row.keperluan ?? '-'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
