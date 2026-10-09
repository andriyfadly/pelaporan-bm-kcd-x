import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

interface KodeRekeningItem {
    id: string;
    kode: string;
    nama: string;
    sumber_dana: string;
}

interface Paginated<T> {
    data: T[];
}

export default function Index({ items, sumberDana }: { items: Paginated<KodeRekeningItem>; sumberDana: string }) {
    const form = useForm({ kode: '', nama: '', sumber_dana: sumberDana });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/distribusi-bhp/master/kode-rekening', { onSuccess: () => form.reset('kode', 'nama') });
    };

    return (
        <AppLayout title="Kode Rekening BHP">
            <Head title="Kode Rekening BHP" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-lg font-bold text-slate-900">Kode Rekening BHP — {sumberDana}</h1>
                    
                </div>

                <form onSubmit={submit} className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                    <div>
                        <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Kode</label>
                        <input value={form.data.kode} onChange={(e) => form.setData('kode', e.target.value)} placeholder="Contoh: 5.1.02.01.01.0001" className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm" />
                        {form.errors.kode && <p className="text-xs text-rose-600 mt-1">{form.errors.kode}</p>}
                    </div>
                    <div>
                        <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Nama</label>
                        <input value={form.data.nama} onChange={(e) => form.setData('nama', e.target.value)} placeholder="Masukkan nama rekening" className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm" />
                        {form.errors.nama && <p className="text-xs text-rose-600 mt-1">{form.errors.nama}</p>}
                    </div>
                    <button type="submit" disabled={form.processing} className="px-4 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-bold disabled:opacity-50">
                        Tambah Kode Rekening
                    </button>
                </form>

                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 text-slate-500 uppercase text-xs">
                            <tr>
                                <th className="text-left py-3 px-4">Kode</th>
                                <th className="text-left py-3 px-4">Nama</th>
                                <th className="text-left py-3 px-4">Sumber Dana</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {items.data.length === 0 ? (
                                <tr><td colSpan={3} className="py-10 text-center text-slate-400 text-xs">Belum ada kode rekening.</td></tr>
                            ) : (
                                items.data.map((item) => (
                                    <tr key={item.id}>
                                        <td className="py-3 px-4 font-mono text-slate-700">{item.kode}</td>
                                        <td className="py-3 px-4">{item.nama}</td>
                                        <td className="py-3 px-4">{item.sumber_dana}</td>
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
