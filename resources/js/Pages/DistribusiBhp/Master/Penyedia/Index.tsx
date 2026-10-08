import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

interface PenyediaItem {
    id: string;
    nama: string;
    alamat: string | null;
    telepon: string | null;
    email: string | null;
}

export default function Index({ items }: { items: { data: PenyediaItem[] } }) {
    const form = useForm({ nama: '', alamat: '', telepon: '', email: '' });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/distribusi-bhp/master/penyedia', { onSuccess: () => form.reset() });
    };

    return (
        <AppLayout title="Penyedia BHP">
            <Head title="Penyedia BHP" />

            <div className="space-y-6">
                <h1 className="text-lg font-bold text-slate-900">Penyedia BHP</h1>

                <form onSubmit={submit} className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
                    <div>
                        <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Nama</label>
                        <input value={form.data.nama} onChange={(e) => form.setData('nama', e.target.value)} className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm" />
                        {form.errors.nama && <p className="text-xs text-rose-600 mt-1">{form.errors.nama}</p>}
                    </div>
                    <div>
                        <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Telepon</label>
                        <input value={form.data.telepon} onChange={(e) => form.setData('telepon', e.target.value)} className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm" />
                    </div>
                    <div>
                        <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Email</label>
                        <input value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm" />
                    </div>
                    <button type="submit" disabled={form.processing} className="px-4 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-bold disabled:opacity-50">
                        Tambah Penyedia
                    </button>
                </form>

                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 text-slate-500 uppercase text-xs">
                            <tr>
                                <th className="text-left py-3 px-4">Nama</th>
                                <th className="text-left py-3 px-4">Telepon</th>
                                <th className="text-left py-3 px-4">Email</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {items.data.length === 0 ? (
                                <tr><td colSpan={3} className="py-10 text-center text-slate-400 text-xs">Belum ada penyedia.</td></tr>
                            ) : (
                                items.data.map((item) => (
                                    <tr key={item.id}>
                                        <td className="py-3 px-4 font-medium text-slate-800">{item.nama}</td>
                                        <td className="py-3 px-4">{item.telepon ?? '-'}</td>
                                        <td className="py-3 px-4">{item.email ?? '-'}</td>
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
