import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useState } from 'react';

interface PenerimaanItem {
    id: string;
    no_nota_invoice: string | null;
    sumber_dana: string;
    is_saldo_awal: boolean;
    periode_tahun: number | null;
    penyedia?: { nama: string } | null;
    details: { id: string; jumlah: number; barang?: { nama_barang: string } | null }[];
}

export default function Index({
    items,
    sumberDana,
    barang,
    penyedia,
}: {
    items: { data: PenerimaanItem[] };
    sumberDana: string;
    barang: { id: string; kode_barang: string | null; nama_barang: string; satuan: string | null }[];
    penyedia: { id: string; nama: string }[];
}) {
    const [rows, setRows] = useState([{ barang_id: '', jumlah: 1, harga_satuan: 0 }]);
    const form = useForm({
        sumber_dana: sumberDana,
        no_nota_invoice: '',
        no_kwitansi: '',
        penyedia_id: '',
        tanggal_titimangsa: '',
        is_saldo_awal: false,
        periode_tahun: new Date().getFullYear(),
        details: rows,
    });

    const updateRow = (index: number, patch: Partial<(typeof rows)[number]>) => {
        setRows((prev) => prev.map((row, i) => (i === index ? { ...row, ...patch } : row)));
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.transform((data) => ({ ...data, details: rows }));
        form.post('/distribusi-bhp/penerimaan', { onSuccess: () => setRows([{ barang_id: '', jumlah: 1, harga_satuan: 0 }]) });
    };

    return (
        <AppLayout title="Penerimaan BHP">
            <Head title="Penerimaan BHP" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-lg font-bold text-slate-900">Penerimaan Barang BHP — {sumberDana}</h1>
                    
                </div>

                <form onSubmit={submit} className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-4">
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <input placeholder="No. Nota / Invoice" value={form.data.no_nota_invoice} onChange={(e) => form.setData('no_nota_invoice', e.target.value)} className="px-3 py-2 border border-slate-300 rounded-xl text-sm" />
                        <input placeholder="No. Kwitansi" value={form.data.no_kwitansi} onChange={(e) => form.setData('no_kwitansi', e.target.value)} className="px-3 py-2 border border-slate-300 rounded-xl text-sm" />
                        <input type="date" value={form.data.tanggal_titimangsa} onChange={(e) => form.setData('tanggal_titimangsa', e.target.value)} className="px-3 py-2 border border-slate-300 rounded-xl text-sm" />
                        <select value={form.data.penyedia_id} onChange={(e) => form.setData('penyedia_id', e.target.value)} className="px-3 py-2 border border-slate-300 rounded-xl text-sm">
                            <option value="">Pilih penyedia...</option>
                            {penyedia.map((p) => <option key={p.id} value={p.id}>{p.nama}</option>)}
                        </select>
                        <label className="flex items-center gap-2 text-sm font-semibold text-slate-700">
                            <input type="checkbox" checked={form.data.is_saldo_awal} onChange={(e) => form.setData('is_saldo_awal', e.target.checked)} />
                            Saldo Awal
                        </label>
                        <input type="number" placeholder="Periode Tahun" value={form.data.periode_tahun} onChange={(e) => form.setData('periode_tahun', Number(e.target.value))} className="px-3 py-2 border border-slate-300 rounded-xl text-sm" />
                    </div>

                    <div className="space-y-2">
                        {rows.map((row, index) => (
                            <div key={index} className="grid grid-cols-1 sm:grid-cols-4 gap-3">
                                <select value={row.barang_id} onChange={(e) => updateRow(index, { barang_id: e.target.value })} className="px-3 py-2 border border-slate-300 rounded-xl text-sm">
                                    <option value="">Pilih barang...</option>
                                    {barang.map((b) => <option key={b.id} value={b.id}>{b.nama_barang} ({b.satuan})</option>)}
                                </select>
                                <input type="number" min={1} value={row.jumlah} onChange={(e) => updateRow(index, { jumlah: Number(e.target.value) })} placeholder="Jumlah" className="px-3 py-2 border border-slate-300 rounded-xl text-sm" />
                                <input type="number" min={0} value={row.harga_satuan} onChange={(e) => updateRow(index, { harga_satuan: Number(e.target.value) })} placeholder="Harga Satuan" className="px-3 py-2 border border-slate-300 rounded-xl text-sm" />
                                <button type="button" onClick={() => setRows((prev) => prev.filter((_, i) => i !== index))} className="px-3 py-2 text-rose-600 border border-rose-200 rounded-xl text-sm font-semibold">Hapus</button>
                            </div>
                        ))}
                        <button type="button" onClick={() => setRows((prev) => [...prev, { barang_id: '', jumlah: 1, harga_satuan: 0 }])} className="text-sm font-semibold text-blue-600">+ Tambah Baris</button>
                    </div>

                    <button type="submit" disabled={form.processing} className="px-4 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-bold disabled:opacity-50">
                        Simpan Penerimaan
                    </button>
                </form>

                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 text-slate-500 uppercase text-xs">
                            <tr>
                                <th className="text-left py-3 px-4">No. Nota</th>
                                <th className="text-left py-3 px-4">Penyedia</th>
                                <th className="text-left py-3 px-4">Saldo Awal</th>
                                <th className="text-left py-3 px-4">Jumlah Item</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {items.data.length === 0 ? (
                                <tr><td colSpan={4} className="py-10 text-center text-slate-400 text-xs">Belum ada penerimaan.</td></tr>
                            ) : (
                                items.data.map((item) => (
                                    <tr key={item.id}>
                                        <td className="py-3 px-4">{item.no_nota_invoice ?? '-'}</td>
                                        <td className="py-3 px-4">{item.penyedia?.nama ?? '-'}</td>
                                        <td className="py-3 px-4">{item.is_saldo_awal ? `Ya (${item.periode_tahun ?? '-'})` : 'Tidak'}</td>
                                        <td className="py-3 px-4">{item.details.length}</td>
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
