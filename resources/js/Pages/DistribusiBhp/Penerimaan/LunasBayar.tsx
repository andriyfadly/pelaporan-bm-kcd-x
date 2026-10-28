import { Head } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { formatTanggal } from '@/Utils/format';

interface PenerimaanItem {
    id: string;
    no_nota_invoice: string | null;
    no_bast: string | null;
    no_kwitansi: string | null;
    tanggal_bast: string | null;
    tanggal_titimangsa: string | null;
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
                    
                </div>

                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 text-slate-500 uppercase text-xs">
                            <tr>
                                <th className="text-left py-3 px-4">No. Invoice/Nota</th>
                                <th className="text-left py-3 px-4">No. BAST</th>
                                <th className="text-left py-3 px-4">No. Kwitansi</th>
                                <th className="text-left py-3 px-4">Penyedia</th>
                                <th className="text-left py-3 px-4">Tanggal Efektif</th>
                                <th className="text-right py-3 px-4">Jumlah Barang</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {items.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="py-10 text-center text-slate-400 text-xs">
                                        Belum ada penerimaan yang lunas.
                                    </td>
                                </tr>
                            ) : (
                                items.data.map((item) => (
                                    <tr key={item.id}>
                                        <td className="py-3 px-4">{item.no_nota_invoice ?? '-'}</td>
                                        <td className="py-3 px-4">{item.no_bast ?? '-'}</td>
                                        <td className="py-3 px-4">{item.no_kwitansi ?? '-'}</td>
                                        <td className="py-3 px-4">{item.penyedia?.nama ?? '-'}</td>
                                        <td className="py-3 px-4">{formatTanggal(item.tanggal_bast ?? item.tanggal_titimangsa)}</td>
                                        <td className="py-3 px-4 text-right">{item.details.length}</td>
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