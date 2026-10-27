import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useState } from 'react';

interface Detail {
    id: string;
    jumlah_diminta: number;
    jumlah_direservasi: number;
    barang?: { nama_barang: string; satuan: string | null } | null;
}

interface PengajuanItem {
    id: string;
    tanggal: string | null;
    sumber_dana: string;
    pemohon?: { name: string | null } | null;
    details: Detail[];
}

interface Paginated<T> {
    data: T[];
}

interface Props {
    items: Paginated<PengajuanItem>;
    pejabatWarning: string | null;
}

export default function Index({ items, pejabatWarning }: Props) {
    const [active, setActive] = useState<PengajuanItem | null>(null);
    const [qty, setQty] = useState<Record<string, number>>({});
    const [alasan, setAlasan] = useState('');

    const open = (item: PengajuanItem) => {
        setActive(item);
        setAlasan('');
        setQty(Object.fromEntries(item.details.map((d) => [d.id, d.jumlah_direservasi || d.jumlah_diminta])));
    };

    const approve = () => {
        if (!active) return;
        router.post(`/distribusi-bhp/review-pengajuan/${active.id}/approve`, { jumlah_disetujui: qty }, { onSuccess: () => setActive(null) });
    };

    const reject = () => {
        if (!active) return;
        router.post(`/distribusi-bhp/review-pengajuan/${active.id}/reject`, { rejection_reason: alasan }, { onSuccess: () => setActive(null) });
    };

    return (
        <AppLayout title="Review Pengajuan BHP">
            <Head title="Review Pengajuan BHP" />

            <div className="space-y-6">
                <h1 className="text-lg font-bold text-slate-900">Review Pengajuan BHP</h1>

                {pejabatWarning && (
                    <div className="bg-amber-50 border border-amber-200 text-amber-800 rounded-2xl px-4 py-3 text-sm">
                        {pejabatWarning} — approval akan ditolak sampai data pejabat dilengkapi di menu <strong>Master Data → Penugasan Pegawai</strong>.
                    </div>
                )}

                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 text-slate-500 uppercase text-xs">
                            <tr>
                                <th className="text-left py-3 px-4">Tanggal</th>
                                <th className="text-left py-3 px-4">Pemohon</th>
                                <th className="text-left py-3 px-4">Sumber Dana</th>
                                <th className="text-left py-3 px-4">Item</th>
                                <th className="text-right py-3 px-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {items.data.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="py-10 text-center text-slate-400 text-xs">
                                        Tidak ada pengajuan menunggu review.
                                    </td>
                                </tr>
                            ) : (
                                items.data.map((item) => (
                                    <tr key={item.id}>
                                        <td className="py-3 px-4">{item.tanggal ?? '-'}</td>
                                        <td className="py-3 px-4">{item.pemohon?.name ?? '-'}</td>
                                        <td className="py-3 px-4">{item.sumber_dana}</td>
                                        <td className="py-3 px-4">{item.details.length}</td>
                                        <td className="py-3 px-4 text-right">
                                            <button onClick={() => open(item)} className="px-3 py-1.5 bg-blue-600 text-white rounded-lg text-xs font-bold">
                                                Review
                                            </button>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {active && (
                <div className="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
                    <div className="bg-white rounded-2xl w-full max-w-2xl shadow-xl">
                        <div className="px-6 py-4 border-b border-slate-200">
                            <h2 className="font-bold text-slate-900">Review Pengajuan Barang</h2>
                        </div>
                        <div className="p-6 space-y-4">
                            {active.details.map((d) => (
                                <div key={d.id} className="flex items-center justify-between gap-4 border border-slate-200 rounded-xl p-3">
                                    <div>
                                        <div className="font-semibold text-slate-800">{d.barang?.nama_barang}</div>
                                        <div className="text-xs text-slate-500">Diminta: {d.jumlah_diminta} {d.barang?.satuan}</div>
                                    </div>
                                    <input
                                        type="number"
                                        min={0}
                                        max={d.jumlah_diminta}
                                        value={qty[d.id] ?? 0}
                                        onChange={(e) => setQty({ ...qty, [d.id]: Number(e.target.value) })}
                                        className="w-28 px-3 py-2 border border-slate-300 rounded-lg text-sm"
                                    />
                                </div>
                            ))}

                            <textarea
                                placeholder="Catatan review / alasan penolakan"
                                value={alasan}
                                onChange={(e) => setAlasan(e.target.value)}
                                className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"
                                rows={3}
                            />
                        </div>
                        <div className="px-6 py-4 border-t border-slate-200 flex justify-end gap-2">
                            <button onClick={() => setActive(null)} className="px-4 py-2 border border-slate-300 rounded-xl text-sm font-semibold">
                                Batal
                            </button>
                            <button onClick={reject} className="px-4 py-2 bg-rose-600 text-white rounded-xl text-sm font-semibold">
                                Tolak
                            </button>
                            <button onClick={approve} className="px-4 py-2 bg-emerald-600 text-white rounded-xl text-sm font-semibold">
                                Setujui Pengajuan
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
