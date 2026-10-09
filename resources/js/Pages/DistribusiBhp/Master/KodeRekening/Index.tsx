import { Head, useForm, usePage, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useState } from 'react';
import { Save, Loader2 } from 'lucide-react';

interface KodeRekeningItem {
    id: string;
    kode: string;
    nama: string;
    sumber_dana: string;
    kode_barang?: string | null;
    uraian_kode_barang?: string | null;
}

interface KodeBarangOption {
    kode: string;
    uraian: string;
}

interface Paginated<T> {
    data: T[];
}

export default function Index({ items, sumberDana, kodeBarangOptions }: { items: Paginated<KodeRekeningItem>; sumberDana: string; kodeBarangOptions: KodeBarangOption[] }) {
    const { auth } = usePage<any>().props;
    const roles: string[] = auth?.user?.roles || [];
    const isAdmin = roles.includes('admin_kcd') || roles.includes('super_admin');

    const form = useForm({ kode: '', nama: '', sumber_dana: sumberDana, kode_barang: '', uraian_kode_barang: '' });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/distribusi-bhp/master/kode-rekening', { onSuccess: () => form.reset('kode', 'nama', 'kode_barang', 'uraian_kode_barang') });
    };

    const [assign, setAssign] = useState<Record<string, { kode_barang: string; uraian_kode_barang: string }>>({});
    const [saving, setSaving] = useState(false);

    const handleAssign = (id: string, kode: string) => {
        const opt = kodeBarangOptions.find(o => o.kode === kode);
        setAssign(p => ({ ...p, [id]: { kode_barang: kode, uraian_kode_barang: opt?.uraian ?? '' } }));
    };

    const saveAssign = () => {
        const vals = Object.entries(assign).map(([id, v]) => ({ id, ...v }));
        if (!vals.length) return;
        setSaving(true);
        router.post('/distribusi-bhp/master/kode-rekening/batch-assign', { assignments: vals }, {
            onSuccess: () => { setAssign({}); setSaving(false); },
            onError: () => setSaving(false),
        });
    };

    const recordsWithoutKodeBarang = items.data.filter(i => !i.kode_barang);
    const hasLocalAssignments = Object.keys(assign).length > 0;

    return (
        <AppLayout title="Kode Rekening BHP">
            <Head title="Kode Rekening BHP" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-lg font-bold text-slate-900">Kode Rekening BHP — {sumberDana}</h1>
                </div>

                {isAdmin && (
                    <form onSubmit={submit} className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
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
                        <div>
                            <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Kode Barang</label>
                            <select value={form.data.kode_barang} onChange={(e) => {
                                const selected = kodeBarangOptions.find(o => o.kode === e.target.value);
                                form.setData({ kode_barang: e.target.value, uraian_kode_barang: selected?.uraian ?? '' });
                            }} className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm bg-white">
                                <option value="">— Pilih —</option>
                                {kodeBarangOptions.map((o) => (
                                    <option key={o.kode} value={o.kode}>{o.kode} — {o.uraian}</option>
                                ))}
                            </select>
                        </div>
                        <button type="submit" disabled={form.processing} className="px-4 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-bold disabled:opacity-50">
                            Tambah Kode Rekening
                        </button>
                    </form>
                )}

                {recordsWithoutKodeBarang.length > 0 && hasLocalAssignments && (
                    <div className="flex justify-end">
                        <button onClick={saveAssign} disabled={saving} className="flex items-center gap-2 px-4 py-2 bg-orange-600 text-white rounded-xl text-sm font-bold disabled:opacity-50">
                            {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                            {saving ? 'Menyimpan...' : `Simpan ${Object.keys(assign).length} Assignment`}
                        </button>
                    </div>
                )}

                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 text-slate-500 uppercase text-xs">
                            <tr>
                                <th className="text-left py-3 px-4">Kode</th>
                                <th className="text-left py-3 px-4">Nama</th>
                                <th className="text-left py-3 px-4">Kode Barang</th>
                                <th className="text-left py-3 px-4">Uraian Barang</th>
                                <th className="text-left py-3 px-4">Sumber Dana</th>
                                {!isAdmin && <th className="text-left py-3 px-4">Assign</th>}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {items.data.length === 0 ? (
                                <tr><td colSpan={6} className="py-10 text-center text-slate-400 text-xs">Belum ada kode rekening.</td></tr>
                            ) : (
                                items.data.map((item) => (
                                    <tr key={item.id}>
                                        <td className="py-3 px-4 font-mono text-slate-700">{item.kode}</td>
                                        <td className="py-3 px-4">{item.nama}</td>
                                        <td className="py-3 px-4 font-mono text-xs text-slate-500">{item.kode_barang ?? '—'}</td>
                                        <td className="py-3 px-4 text-xs text-slate-500">{item.uraian_kode_barang ?? '—'}</td>
                                        <td className="py-3 px-4">{item.sumber_dana}</td>
                                        {!isAdmin && (
                                            <td className="py-3 px-4">
                                                {item.kode_barang ? (
                                                    <span className="text-xs text-green-600 font-medium">✓ Sudah</span>
                                                ) : (
                                                    <select
                                                        value={assign[item.id]?.kode_barang ?? ''}
                                                        onChange={(e) => handleAssign(item.id, e.target.value)}
                                                        className="w-full px-2 py-1.5 border border-slate-300 rounded-lg text-xs bg-white"
                                                    >
                                                        <option value="">— Pilih —</option>
                                                        {kodeBarangOptions.map((o) => (
                                                            <option key={o.kode} value={o.kode}>{o.kode} — {o.uraian}</option>
                                                        ))}
                                                    </select>
                                                )}
                                            </td>
                                        )}
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