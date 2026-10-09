import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { FileSpreadsheet } from 'lucide-react';

interface PenerimaanItem {
    id: string;
    no_nota_invoice: string | null;
    no_bast: string | null;
    tanggal_bast: string | null;
    penyedia?: { nama: string } | null;
    details: { id: string; jumlah: number; barang?: { nama_barang: string } | null }[];
}

export default function LunasBayar({
    items,
    sumberDana,
}: {
    items: { data: PenerimaanItem[] };
    sumberDana: string;
}) {
    return (
        <AppLayout title={`Lunas Bayar — ${sumberDana}`}>
            <Head title={`Lunas Bayar — ${sumberDana}`} />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-lg font-bold text-slate-900">Lunas Bayar — {sumberDana}</h1>
                    <div className="flex gap-2">
                        {['BOS', 'BOPD'].map((dana) => (
                            <Link
                                key={dana}
                                href={`/distribusi-bhp/penerimaan/lunas-bayar?sumber_dana=${dana}`}
                                className={`px-3 py-2 rounded-xl text-sm font-semibold border ${dana === sumberDana ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-700 border-slate-300'}`}
                            >
                                {dana}
                            </Link>
                        ))}
                    </div>
                </div>

                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 text-slate-500 uppercase text-xs">
                            <tr>
                                <th className="text-left py-3 px-4">No. Invoice/Nota</th>
                                <th className="text-left py-3 px-4">Penyedia</th>
                                <th className="text-left py-3 px-4">Tanggal BAST</th>
                                <th className="text-right py-3 px-4">Jumlah Barang</th>
                                <th className="text-center py-3 px-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {items.data.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="py-10 text-center text-slate-400 text-xs">
                                        Belum ada penerimaan.
                                    </td>
                                </tr>
                            ) : (
                                items.data.map((item) => (
                                    <tr key={item.id}>
                                        <td className="py-3 px-4">{item.no_nota_invoice ?? '-'}</td>
                                        <td className="py-3 px-4">{item.penyedia?.nama ?? '-'}</td>
                                        <td className="py-3 px-4">{item.tanggal_bast ?? '-'}</td>
                                        <td className="py-3 px-4 text-right">{item.details.length}</td>
                                        <td className="py-3 px-4 text-center">
                                            <Link
                                                href={`/distribusi-bhp/penerimaan/${item.id}/edit`}
                                                className="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold bg-blue-600 text-white hover:bg-blue-700"
                                            >
                                                Detail
                                            </Link>
                                        </td>
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