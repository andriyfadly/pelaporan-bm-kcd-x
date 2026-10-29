import { Head } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import EmptyState from '@/Components/EmptyState';
import PeriodeFilter, { periodeQuery, type PeriodeValue } from '@/Components/PeriodeFilter';
import { FileSpreadsheet, Printer } from 'lucide-react';
import { formatTanggalTabel } from '@/Utils/format';

interface PenerimaanRow {
    tanggal: string | null;
    no_bast: string | null;
    no_nota_invoice: string | null;
    penyedia: string | null;
    kode_barang: string | null;
    nama_barang: string | null;
    satuan: string | null;
    jumlah: number;
    harga_satuan: number;
    nilai_total: number;
    is_saldo_awal: boolean;
}

export default function BukuPenerimaan({
    jenisLabel,
    sumberDana,
    periode,
    items,
}: {
    jenisLabel: string;
    sumberDana: string;
    periode: PeriodeValue;
    items: PenerimaanRow[];
}) {
    const prefix = `/distribusi-bhp/${sumberDana.toLowerCase()}`;
    const query = `jenis=penerimaan&${periodeQuery(periode)}`;
    const total = items.reduce((jumlah, row) => jumlah + row.nilai_total, 0);

    return (
        <AppLayout title={jenisLabel}>
            <Head title={`${jenisLabel} | BHP`} />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-lg font-bold text-slate-900">{jenisLabel} — {sumberDana}</h1>
                        <p className="text-sm text-slate-500">Barang masuk pada {periode.label}.</p>
                    </div>
                    <div className="flex flex-wrap items-end gap-3">
                        <PeriodeFilter path={`${prefix}/laporan/buku-penerimaan`} periode={periode} sumberDana={sumberDana} />
                        <a
                            href={`${prefix}/laporan/export?${query}`}
                            className="inline-flex min-h-10 items-center justify-center gap-2 px-4 rounded-xl text-sm font-semibold bg-emerald-600 text-white hover:bg-emerald-700"
                        >
                            <FileSpreadsheet className="w-4 h-4" /> Export
                        </a>
                        <a
                            href={`${prefix}/laporan/buku-penerimaan/cetak?${periodeQuery(periode)}`}
                            className="inline-flex min-h-10 items-center justify-center gap-2 px-4 rounded-xl text-sm font-semibold bg-blue-600 text-white hover:bg-blue-700"
                        >
                            <Printer className="w-4 h-4" /> Cetak PDF
                        </a>
                    </div>
                </div>

                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    {items.length === 0 ? (
                        <EmptyState
                            title="Belum Ada Penerimaan"
                            description={`Belum ada barang masuk pada ${periode.label}.`}
                        />
                    ) : (
                        <>
                            <ul className="md:hidden divide-y divide-slate-100">
                                {items.map((row, i) => (
                                    <li key={i} className="p-4 space-y-1">
                                        <div className="flex items-start justify-between gap-2">
                                            <span className="font-semibold text-slate-800 break-words">
                                                {row.nama_barang ?? '-'} <span className="text-xs font-normal text-slate-500">({row.satuan ?? '-'})</span>
                                            </span>
                                            <span className="shrink-0 text-sm font-bold text-slate-900">{row.jumlah}</span>
                                        </div>
                                        <div className="text-xs text-slate-500">
                                            {formatTanggalTabel(row.tanggal)} · {row.no_bast ?? 'tanpa BAST'}
                                            {row.is_saldo_awal && ' · saldo awal'}
                                        </div>
                                        <div className="flex flex-wrap gap-x-3 text-xs text-slate-600">
                                            <span>{row.penyedia ?? '-'}</span>
                                            <span>Nilai: <strong>{row.nilai_total.toLocaleString('id-ID')}</strong></span>
                                        </div>
                                    </li>
                                ))}
                            </ul>

                            <div className="hidden md:block overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="bg-slate-50 text-slate-500 uppercase text-xs">
                                        <tr>
                                            <th className="text-left py-3 px-4">Tanggal</th>
                                            <th className="text-left py-3 px-4">No. BAST</th>
                                            <th className="text-left py-3 px-4">Nota/Invoice</th>
                                            <th className="text-left py-3 px-4">Penyedia</th>
                                            <th className="text-left py-3 px-4">Barang</th>
                                            <th className="text-right py-3 px-4">Jumlah</th>
                                            <th className="text-right py-3 px-4">Harga</th>
                                            <th className="text-right py-3 px-4">Nilai</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {items.map((row, i) => (
                                            <tr key={i}>
                                                <td className="py-3 px-4 whitespace-nowrap">{formatTanggalTabel(row.tanggal)}</td>
                                                <td className="py-3 px-4 font-mono text-xs text-slate-600">
                                                    {row.no_bast ?? '-'}
                                                    {row.is_saldo_awal && <span className="ml-2 text-xs text-slate-500">saldo awal</span>}
                                                </td>
                                                <td className="py-3 px-4 text-slate-600">{row.no_nota_invoice ?? '-'}</td>
                                                <td className="py-3 px-4">{row.penyedia ?? '-'}</td>
                                                <td className="py-3 px-4">
                                                    {row.nama_barang ?? '-'} <span className="text-xs text-slate-500">({row.satuan ?? '-'})</span>
                                                </td>
                                                <td className="py-3 px-4 text-right">{row.jumlah}</td>
                                                <td className="py-3 px-4 text-right">{row.harga_satuan.toLocaleString('id-ID')}</td>
                                                <td className="py-3 px-4 text-right font-bold">{row.nilai_total.toLocaleString('id-ID')}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                    <tfoot>
                                        <tr className="bg-slate-50 font-bold">
                                            <td colSpan={7} className="py-3 px-4 text-right">Total Nilai</td>
                                            <td className="py-3 px-4 text-right">{total.toLocaleString('id-ID')}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <div className="md:hidden border-t border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold text-slate-800">
                                Total Nilai: {total.toLocaleString('id-ID')}
                            </div>
                        </>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
