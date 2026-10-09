import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

interface BarangItem {
    id: string;
    kode_barang: string | null;
    nama_barang: string;
    satuan: string | null;
    sumber_dana: string;
    kode_rekening?: { kode: string } | null;
    harga: { id: string; harga_satuan: number }[];
}

interface Paginated<T> {
    data: T[];
}

export default function Index({
    items,
    sumberDana,
    kodeRekening,
}: {
    items: Paginated<BarangItem>;
    sumberDana: string;
    kodeRekening: { id: string; kode: string; nama: string }[];
}) {
    const form = useForm({
        kode_barang: '',
        nama_barang: '',
        satuan: '',
        kode_rekening_id: '',
        sumber_dana: sumberDana,
        harga_satuan: 0,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/distribusi-bhp/master/barang', { onSuccess: () => form.reset('kode_barang', 'nama_barang', 'satuan', 'kode_rekening_id', 'harga_satuan') });
    };

    return (
        <AppLayout title="Data Barang BHP">
            <Head title="Data Barang BHP" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-lg font-bold text-slate-900">Data Barang BHP — {sumberDana}</h1>
                    
                </div>

                <form onSubmit={submit} className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 items-end">
                    <div>
                        <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Kode Barang</label>
                        <input value={form.data.kode_barang} onChange={(e) => form.setData('kode_barang', e.target.value)} className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm" />
                    </div>
                    <div>
                        <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Nama Barang</label>
                        <input value={form.data.nama_barang} onChange={(e) => form.setData('nama_barang', e.target.value)} placeholder="Masukkan nama barang" className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm" />
                        {form.errors.nama_barang && <p className="text-xs text-rose-600 mt-1">{form.errors.nama_barang}</p>}
                    </div>
                    <div>
                        <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Satuan</label>
                        <input value={form.data.satuan} onChange={(e) => form.setData('satuan', e.target.value)} placeholder="Unit, Pcs, Kg, Liter, dll" className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm" />
                    </div>
                    <div>
                        <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Kode Rekening</label>
                        <select value={form.data.kode_rekening_id} onChange={(e) => form.setData('kode_rekening_id', e.target.value)} className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm">
                            <option value="">Pilih kode rekening...</option>
                            {kodeRekening.map((rek) => (
                                <option key={rek.id} value={rek.id}>{rek.kode} — {rek.nama}</option>
                            ))}
                        </select>
                        {form.errors.kode_rekening_id && <p className="text-xs text-rose-600 mt-1">{form.errors.kode_rekening_id}</p>}
                    </div>
                    <div>
                        <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Harga Satuan</label>
                        <input type="number" value={form.data.harga_satuan} onChange={(e) => form.setData('harga_satuan', Number(e.target.value))} placeholder="Masukkan harga satuan" className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm" />
                        {form.errors.harga_satuan && <p className="text-xs text-rose-600 mt-1">{form.errors.harga_satuan}</p>}
                    </div>
                    <button type="submit" disabled={form.processing} className="px-4 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-bold disabled:opacity-50">
                        Tambah Barang
                    </button>
                </form>

                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 text-slate-500 uppercase text-xs">
                            <tr>
                                <th className="text-left py-3 px-4">Kode</th>
                                <th className="text-left py-3 px-4">Nama</th>
                                <th className="text-left py-3 px-4">Satuan</th>
                                <th className="text-left py-3 px-4">Rekening</th>
                                <th className="text-right py-3 px-4">Harga Terakhir</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {items.data.length === 0 ? (
                                <tr><td colSpan={5} className="py-10 text-center text-slate-400 text-xs">Belum ada barang.</td></tr>
                            ) : (
                                items.data.map((item) => (
                                    <tr key={item.id}>
                                        <td className="py-3 px-4 font-mono text-slate-700">{item.kode_barang ?? '-'}</td>
                                        <td className="py-3 px-4">{item.nama_barang}</td>
                                        <td className="py-3 px-4">{item.satuan ?? '-'}</td>
                                        <td className="py-3 px-4">{item.kode_rekening?.kode ?? '-'}</td>
                                        <td className="py-3 px-4 text-right">{item.harga.at(-1)?.harga_satuan?.toLocaleString('id-ID') ?? '-'}</td>
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
