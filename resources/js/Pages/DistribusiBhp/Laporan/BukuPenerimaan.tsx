import { Head } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import PeriodeFilter, { periodeQuery, type PeriodeValue } from '@/Components/PeriodeFilter';
import { FileSpreadsheet, Printer } from 'lucide-react';

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
    const query = `sumber_dana=${sumberDana}&${periodeQuery(periode)}`;
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
                        <PeriodeFilter path="/distribusi-bhp/laporan/buku-penerimaan" periode={periode} sumberDana={sumberDana} />
                        <a
                            href={`/distribusi-bhp/laporan/export?sumber_dana=${sumberDana}&jenis=penerimaan&${periodeQuery(periode)}`}
                            className="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold bg-emerald-600 text-white hover:bg-emerald-700"
                        >
                            <FileSpreadsheet className="w-4 h-4" /> Export
                        </a>
                        <a
                            href={`/distribusi-bhp/laporan/buku-penerimaan/cetak?sumber_dana=${sumberDana}&${periodeQuery(periode)}`}
                            className="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold bg-blue-600 text-white hover:bg-blue-700"
                        >
                            <Printer className="w-4 h-4" /> Cetak PDF
                        </a>
                    </div>
                </div>

                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div className="overflow-x-auto">
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
                                {items.length === 0 ? (
                                    <tr>
                                        <td colSpan={8} className="py-10 text-center text-slate-400 text-xs">
                                            Belum ada penerimaan pada periode ini.
                                        </td>
                                    </tr>
                                ) : (
                                    items.map((row, i) => (
                                        <tr key={i}>
                                            <td className="py-3 px-4 whitespace-nowrap">{row.tanggal ?? '-'}</td>
                                            <td className="py-3 px-4 font-mono text-xs text-slate-600">
                                                {row.no_bast ?? '-'}
                                                {row.is_saldo_awal && <span className="ml-2 text-[11px] text-slate-400">saldo awal</span>}
                                            </td>
                                            <td className="py-3 px-4 text-slate-600">{row.no_nota_invoice ?? '-'}</td>
                                            <td className="py-3 px-4">{row.penyedia ?? '-'}</td>
                                            <td className="py-3 px-4">
                                                {row.nama_barang ?? '-'} <span className="text-xs text-slate-400">({row.satuan ?? '-'})</span>
                                            </td>
                                            <td className="py-3 px-4 text-right">{row.jumlah}</td>
                                            <td className="py-3 px-4 text-right">{row.harga_satuan.toLocaleString('id-ID')}</td>
                                            <td className="py-3 px-4 text-right font-bold">{row.nilai_total.toLocaleString('id-ID')}</td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                            <tfoot>
                                <tr className="bg-slate-50 font-bold">
                                    <td colSpan={7} className="py-3 px-4 text-right">Total Nilai</td>
                                    <td className="py-3 px-4 text-right">{total.toLocaleString('id-ID')}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
