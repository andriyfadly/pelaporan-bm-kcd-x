import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import ConfirmDialog from '@/Components/ConfirmDialog';
import { useState } from 'react';
import { Trash2 } from 'lucide-react';

interface PenerimaanItem {
    id: string;
    no_nota_invoice: string | null;
    no_bast: string | null;
    tanggal_bast: string | null;
    sumber_dana: string;
    is_saldo_awal: boolean;
    periode_tahun: number | null;
    penyedia?: { nama: string } | null;
    details: { id: string; jumlah: number; barang?: { nama_barang: string } | null }[];
}

interface Baris {
    barang_id: string;
    jumlah: number;
    harga_satuan: number;
}

const barisKosong = (): Baris => ({ barang_id: '', jumlah: 1, harga_satuan: 0 });

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
    const [rows, setRows] = useState<Baris[]>([barisKosong()]);
    const [hapus, setHapus] = useState<PenerimaanItem | null>(null);

    const form = useForm({
        sumber_dana: sumberDana,
        no_nota_invoice: '',
        no_bast: '',
        // Tanggal efektif stok masuk. Wajib untuk penerimaan biasa supaya
        // laporan periode tidak bergantung pada tanggal input.
        tanggal_bast: '',
        no_kwitansi: '',
        // Kosong = barang sudah datang, transaksi keuangan belum terjadi
        // ("Lunas Bayar"). Kalau diisi, tanggal pembayaran wajib ikut.
        tanggal_pembayaran: '',
        penyedia_id: '',
        tanggal_titimangsa: '',
        details: rows,
    });

    const updateRow = (index: number, patch: Partial<Baris>) => {
        setRows((prev) => prev.map((row, i) => (i === index ? { ...row, ...patch } : row)));
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.transform((data) => ({ ...data, details: rows }));
        form.post(`/distribusi-bhp/${sumberDana.toLowerCase()}/penerimaan`, { onSuccess: () => setRows([barisKosong()]) });
    };

    const konfirmasiHapus = () => {
        if (!hapus) return;
        router.delete(`/distribusi-bhp/${sumberDana.toLowerCase()}/penerimaan/${hapus.id}`, { preserveScroll: true });
    };

    const fieldError = (key: string) => {
        const pesan = (form.errors as Record<string, string | undefined>)[key];

        return pesan ? <p className="text-[11px] text-rose-600">{pesan}</p> : null;
    };

    return (
        <AppLayout title="Penerimaan BHP">
            <Head title="Penerimaan BHP" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-lg font-bold text-slate-900">Penerimaan Barang BHP — {sumberDana}</h1>
                    <a
                        href={`/distribusi-bhp/${sumberDana.toLowerCase()}/saldo-awal`}
                        className="text-sm font-semibold text-blue-600"
                    >
                        Saldo Awal →
                    </a>
                </div>

                <form onSubmit={submit} className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-4">
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <input placeholder="No. BAST *" value={form.data.no_bast} onChange={(e) => form.setData('no_bast', e.target.value)} className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm" />
                            {fieldError('no_bast')}
                        </div>
                        <div>
                            <input type="date" aria-label="Tanggal BAST" value={form.data.tanggal_bast} onChange={(e) => form.setData('tanggal_bast', e.target.value)} className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm" />
                            {fieldError('tanggal_bast')}
                        </div>
                        <div>
                            <select aria-label="Penyedia" value={form.data.penyedia_id} onChange={(e) => form.setData('penyedia_id', e.target.value)} className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm">
                                <option value="">Pilih penyedia… *</option>
                                {penyedia.map((p) => <option key={p.id} value={p.id}>{p.nama}</option>)}
                            </select>
                            {fieldError('penyedia_id')}
                        </div>
                        <input placeholder="No. Nota / Invoice" value={form.data.no_nota_invoice} onChange={(e) => form.setData('no_nota_invoice', e.target.value)} className="px-3 py-2 border border-slate-300 rounded-xl text-sm" />
                        <div>
                            <input placeholder="No. Kwitansi" value={form.data.no_kwitansi} onChange={(e) => form.setData('no_kwitansi', e.target.value)} className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm" />
                            {fieldError('no_kwitansi')}
                        </div>
                        <div>
                            <label htmlFor="tanggal-pembayaran" className="block text-[11px] font-semibold text-slate-500 mb-1">Tanggal Pembayaran</label>
                            <input id="tanggal-pembayaran" type="date" value={form.data.tanggal_pembayaran} onChange={(e) => form.setData('tanggal_pembayaran', e.target.value)} className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm" />
                            {fieldError('tanggal_pembayaran')}
                        </div>
                        <div>
                            <label htmlFor="tanggal-titimangsa" className="block text-[11px] font-semibold text-slate-500 mb-1">Tanggal Titimangsa</label>
                            <input id="tanggal-titimangsa" type="date" value={form.data.tanggal_titimangsa} onChange={(e) => form.setData('tanggal_titimangsa', e.target.value)} className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm" />
                        </div>
                    </div>
                    <p className="text-[11px] text-slate-500">
                        Kosongkan No. Kwitansi bila belum dibayar — data akan masuk daftar <strong>Lunas Bayar</strong> dan
                        diberi keterangan “Lunas Bayar” pada Buku Penerimaan bulan berjalan.
                    </p>

                    <div className="space-y-2">
                        {rows.map((row, index) => (
                            <div key={index} className="grid grid-cols-1 sm:grid-cols-4 gap-3">
                                <select value={row.barang_id} onChange={(e) => updateRow(index, { barang_id: e.target.value })} className="px-3 py-2 border border-slate-300 rounded-xl text-sm">
                                    <option value="">Pilih barang…</option>
                                    {barang.map((b) => <option key={b.id} value={b.id}>{b.nama_barang} ({b.satuan})</option>)}
                                </select>
                                <input type="number" min={1} value={row.jumlah} onChange={(e) => updateRow(index, { jumlah: Number(e.target.value) })} placeholder="Jumlah" className="px-3 py-2 border border-slate-300 rounded-xl text-sm" />
                                <input type="number" min={0} value={row.harga_satuan} onChange={(e) => updateRow(index, { harga_satuan: Number(e.target.value) })} placeholder="Harga Satuan" className="px-3 py-2 border border-slate-300 rounded-xl text-sm" />
                                <button
                                    type="button"
                                    disabled={rows.length === 1}
                                    onClick={() => setRows((prev) => prev.filter((_, i) => i !== index))}
                                    className="px-3 py-2 text-rose-600 border border-rose-200 rounded-xl text-sm font-semibold disabled:opacity-40 disabled:cursor-not-allowed"
                                >
                                    Hapus
                                </button>
                            </div>
                        ))}
                        <button type="button" onClick={() => setRows((prev) => [...prev, barisKosong()])} className="text-sm font-semibold text-blue-600">+ Tambah Baris</button>
                    </div>

                    {fieldError('details')}
                    {Array.from(new Set(rows.map((row) => row.barang_id).filter((id) => id !== ''))).length !== rows.filter((r) => r.barang_id !== '').length && (
                        <p className="text-[11px] text-rose-600">Satu barang tidak boleh dipilih lebih dari sekali.</p>
                    )}

                    <button type="submit" disabled={form.processing} className="px-4 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-bold disabled:opacity-50">
                        Simpan Penerimaan
                    </button>
                </form>

                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 text-slate-500 uppercase text-xs">
                            <tr>
                                <th className="text-left py-3 px-4">No. BAST</th>
                                <th className="text-left py-3 px-4">Tanggal BAST</th>
                                <th className="text-left py-3 px-4">No. Nota</th>
                                <th className="text-left py-3 px-4">Penyedia</th>
                                <th className="text-left py-3 px-4">Saldo Awal</th>
                                <th className="text-left py-3 px-4">Jumlah Item</th>
                                <th className="text-center py-3 px-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {items.data.length === 0 ? (
                                <tr><td colSpan={7} className="py-10 text-center text-slate-400 text-xs">Belum ada penerimaan.</td></tr>
                            ) : (
                                items.data.map((item) => (
                                    <tr key={item.id}>
                                        <td className="py-3 px-4">{item.no_bast ?? '-'}</td>
                                        <td className="py-3 px-4">{item.tanggal_bast?.slice(0, 10) ?? '-'}</td>
                                        <td className="py-3 px-4">{item.no_nota_invoice ?? '-'}</td>
                                        <td className="py-3 px-4">{item.penyedia?.nama ?? '-'}</td>
                                        <td className="py-3 px-4">{item.is_saldo_awal ? `Ya (${item.periode_tahun ?? '-'})` : 'Tidak'}</td>
                                        <td className="py-3 px-4">{item.details.length}</td>
                                        <td className="py-3 px-4 text-center">
                                            <button
                                                type="button"
                                                onClick={() => setHapus(item)}
                                                className="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold text-rose-600 hover:bg-rose-50"
                                            >
                                                <Trash2 className="w-3.5 h-3.5" /> Hapus
                                            </button>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                <ConfirmDialog
                    isOpen={hapus !== null}
                    onClose={() => setHapus(null)}
                    onConfirm={konfirmasiHapus}
                    title="Hapus Penerimaan"
                    message={
                        hapus?.is_saldo_awal
                            ? `Saldo awal periode ${hapus?.periode_tahun ?? '-'} akan dihapus seluruhnya. Lanjutkan?`
                            : `Penerimaan ${hapus?.no_bast ?? ''} akan dihapus dan stok fisik berkurang. Penghapusan ditolak bila stok sudah terpakai atau direservasi.`
                    }
                    confirmText="Hapus"
                />
            </div>
        </AppLayout>
    );
}
