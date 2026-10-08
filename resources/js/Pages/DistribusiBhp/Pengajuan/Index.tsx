import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

interface PengajuanItem {
    id: string;
    tanggal: string | null;
    sumber_dana: string;
    status: string;
    details: { id: string }[];
}

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
}

const statusStyle: Record<string, string> = {
    draft: 'bg-slate-100 text-slate-700',
    submitted: 'bg-amber-50 text-amber-700',
    approved: 'bg-emerald-50 text-emerald-700',
    rejected: 'bg-rose-50 text-rose-700',
};

export default function Index({ items, sumberDana }: { items: Paginated<PengajuanItem>; sumberDana: string }) {
    return (
        <AppLayout title="Pengajuan BHP">
            <Head title="Pengajuan BHP" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-lg font-bold text-slate-900">Pengajuan BHP — {sumberDana}</h1>
                        <p className="text-sm text-slate-500">Domain terpisah dari Pelaporan Belanja Modal.</p>
                    </div>
                    <div className="flex gap-2">
                        {['BOS', 'BOPD'].map((dana) => (
                            <Link
                                key={dana}
                                href={`/distribusi-bhp/pengajuan?sumber_dana=${dana}`}
                                className={`px-3 py-2 rounded-xl text-sm font-semibold border ${
                                    dana === sumberDana
                                        ? 'bg-blue-600 text-white border-blue-600'
                                        : 'bg-white text-slate-700 border-slate-300'
                                }`}
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
                                <th className="text-left py-3 px-4">Tanggal</th>
                                <th className="text-left py-3 px-4">Sumber Dana</th>
                                <th className="text-left py-3 px-4">Jumlah Item</th>
                                <th className="text-left py-3 px-4">Status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {items.data.length === 0 ? (
                                <tr>
                                    <td colSpan={4} className="py-10 text-center text-slate-400 text-xs">
                                        Belum ada pengajuan untuk {sumberDana}.
                                    </td>
                                </tr>
                            ) : (
                                items.data.map((item) => (
                                    <tr key={item.id}>
                                        <td className="py-3 px-4 font-medium text-slate-700">{item.tanggal ?? '-'}</td>
                                        <td className="py-3 px-4">{item.sumber_dana}</td>
                                        <td className="py-3 px-4">{item.details.length}</td>
                                        <td className="py-3 px-4">
                                            <span className={`px-2.5 py-1 rounded-lg text-[11px] font-bold ${statusStyle[item.status] ?? 'bg-slate-100 text-slate-700'}`}>
                                                {item.status}
                                            </span>
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
