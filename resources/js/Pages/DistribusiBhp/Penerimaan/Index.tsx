import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import ConfirmDialog from '@/Components/ConfirmDialog';
import EmptyState from '@/Components/EmptyState';
import Pagination from '@/Components/Pagination';
import { useState } from 'react';
import { Trash2 } from 'lucide-react';
import { formatTanggalTabel } from '@/Utils/format';

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

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
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
    items: Paginated<PenerimaanItem>;
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

        return pesan ? <p className="text-xs text-rose-600">{pesan}</p> : null;
    };

    return (
        <AppLayout title="Penerimaan BHP">
            <Head title="Penerimaan BHP" />

            <div className="space-y-6">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <h1 className="text-lg font-bold text-slate-900">Penerimaan Barang BHP — {sumberDana}</h1>
                    <a
                        href={`/distribusi-bhp/${sumberDana.toLowerCase()}/saldo-awal`}
                        className="inline-flex min-h-10 items-center text-sm font-semibold text-blue-600 hover:text-blue-700"
                    >
                        Saldo Awal →
                    </a>
                </div>

                <form onSubmit={submit} className="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm space-y-5 sm:p-6">
                    <div className="space-y-4">
                        <h2 className="text-xs font-bold uppercase tracking-wider text-slate-500">
                            Dokumen Penerimaan
                        </h2>
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label htmlFor="no-bast" className="block text-xs font-semibold text-slate-500 mb-1">No. BAST *</label>
                            <input id="no-bast" placeholder="cth. 123/BAST/2026" value={form.data.no_bast} onChange={(e) => form.setData('no_bast', e.target.value)} className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm" />
                            {fieldError('no_bast')}
                        </div>
                        <div>
                            <label htmlFor="tanggal-bast" className="block text-xs font-semibold text-slate-500 mb-1">Tanggal BAST *</label>
                            <input id="tanggal-bast" type="date" value={form.data.tanggal_bast} onChange={(e) => form.setData('tanggal_bast', e.target.value)} className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm" />
                            {fieldError('tanggal_bast')}
                        </div>
                        <div>
                            <label htmlFor="penyedia" className="block text-xs font-semibold text-slate-500 mb-1">Penyedia *</label>
                            <select id="penyedia" value={form.data.penyedia_id} onChange={(e) => form.setData('penyedia_id', e.target.value)} className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm">
                                <option value="">Pilih penyedia…</option>
                                {penyedia.map((p) => <option key={p.id} value={p.id}>{p.nama}</option>)}
                            </select>
                            {fieldError('penyedia_id')}
                            {penyedia.length === 0 && (
                                <p className="text-xs text-amber-600 mt-1">
                                    Belum ada penyedia. Tambahkan dulu di menu <strong>Penyedia</strong>.
                                </p>
                            )}
                        </div>
                        <div>
                            <label htmlFor="no-nota" className="block text-xs font-semibold text-slate-500 mb-1">No. Nota / Invoice</label>
                            <input id="no-nota" placeholder="Opsional" value={form.data.no_nota_invoice} onChange={(e) => form.setData('no_nota_invoice', e.target.value)} className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm" />
                        </div>
                        <div>
                            <label htmlFor="no-kwitansi" className="block text-xs font-semibold text-slate-500 mb-1">No. Kwitansi</label>
                            <input id="no-kwitansi" placeholder="Kosongkan bila belum dibayar" value={form.data.no_kwitansi} onChange={(e) => form.setData('no_kwitansi', e.target.value)} className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm" />
                            {fieldError('no_kwitansi')}
                        </div>
                        <div>
                            <label htmlFor="tanggal-pembayaran" className="block text-xs font-semibold text-slate-500 mb-1">Tanggal Pembayaran</label>
                            <input id="tanggal-pembayaran" type="date" value={form.data.tanggal_pembayaran} onChange={(e) => form.setData('tanggal_pembayaran', e.target.value)} className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm" />
                            {fieldError('tanggal_pembayaran')}
                        </div>
                        <div>
                            <label htmlFor="tanggal-titimangsa" className="block text-xs font-semibold text-slate-500 mb-1">Tanggal Titimangsa</label>
                            <input id="tanggal-titimangsa" type="date" value={form.data.tanggal_titimangsa} onChange={(e) => form.setData('tanggal_titimangsa', e.target.value)} className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm" />
                        </div>
                    </div>
                        <p className="text-xs text-slate-500">
                            Kosongkan No. Kwitansi bila belum dibayar — data akan masuk daftar <strong>Lunas Bayar</strong> dan
                            diberi keterangan “Lunas Bayar” pada Buku Penerimaan bulan berjalan.
                        </p>
                    </div>

                    <div className="space-y-3">
                        <div className="flex items-center justify-between gap-2">
                            <h2 className="text-xs font-bold uppercase tracking-wider text-slate-500">
                                Barang Masuk ({rows.length} baris)
                            </h2>
                            <button
                                type="button"
                                onClick={() => setRows((prev) => [...prev, barisKosong()])}
                                className="inline-flex min-h-9 items-center px-3 text-sm font-semibold text-blue-600 hover:text-blue-700"
                            >
                                + Tambah Baris
                            </button>
                        </div>
                        {rows.map((row, index) => (
                            <div key={index} className="grid grid-cols-1 gap-3 rounded-xl border border-slate-200 p-3 sm:grid-cols-12 sm:items-start">
                                <div className="sm:col-span-5">
                                    <label htmlFor={`penerimaan-barang-${index}`} className="block text-xs font-semibold text-slate-500 mb-1">Barang</label>
                                    <select id={`penerimaan-barang-${index}`} value={row.barang_id} onChange={(e) => updateRow(index, { barang_id: e.target.value })} className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm">
                                        <option value="">Pilih barang…</option>
                                        {barang.map((b) => <option key={b.id} value={b.id}>{b.nama_barang} ({b.satuan})</option>)}
                                    </select>
                                </div>
                                <div className="sm:col-span-2">
                                    <label htmlFor={`penerimaan-jumlah-${index}`} className="block text-xs font-semibold text-slate-500 mb-1">Jumlah</label>
                                    <input id={`penerimaan-jumlah-${index}`} type="number" min={1} value={row.jumlah} onChange={(e) => updateRow(index, { jumlah: Number(e.target.value) })} className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm" />
                                </div>
                                <div className="sm:col-span-3">
                                    <label htmlFor={`penerimaan-harga-${index}`} className="block text-xs font-semibold text-slate-500 mb-1">Harga Satuan</label>
                                    <input id={`penerimaan-harga-${index}`} type="number" min={0} value={row.harga_satuan} onChange={(e) => updateRow(index, { harga_satuan: Number(e.target.value) })} className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm" />
                                </div>
                                <div className="sm:col-span-2 sm:pt-5">
                                    <button
                                        type="button"
                                        disabled={rows.length === 1}
                                        onClick={() => setRows((prev) => prev.filter((_, i) => i !== index))}
                                        aria-label={`Hapus baris ${index + 1}`}
                                        className="inline-flex min-h-10 w-full items-center justify-center px-3 text-rose-600 border border-rose-200 rounded-xl text-sm font-semibold disabled:opacity-40 disabled:cursor-not-allowed"
                                    >
                                        Hapus
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>

                    {fieldError('details')}
                    {Array.from(new Set(rows.map((row) => row.barang_id).filter((id) => id !== ''))).length !== rows.filter((r) => r.barang_id !== '').length && (
                        <p className="text-xs text-rose-600">Satu barang tidak boleh dipilih lebih dari sekali.</p>
                    )}

                    <button
                        type="submit"
                        disabled={form.processing}
                        className="min-h-11 w-full px-4 bg-blue-600 text-white rounded-xl text-sm font-bold disabled:opacity-50 sm:w-auto"
                    >
                        {form.processing ? 'Menyimpan…' : 'Simpan Penerimaan'}
                    </button>
                </form>

                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div className="px-4 py-4 border-b border-slate-200 sm:px-6">
                        <h2 className="font-bold text-sm text-slate-700">Riwayat Penerimaan</h2>
                    </div>

                    {/* Mobile: 7 kolom tidak mungkin dibaca di ponsel tanpa geser
                        sana-sini; tampilkan ringkasan + aksi hapus saja. */}
                    {items.data.length === 0 ? (
                        <EmptyState
                            title="Belum Ada Penerimaan"
                            description={`Belum ada barang masuk untuk sumber dana ${sumberDana}.`}
                        />
                    ) : (
                        <>
                            <ul className="md:hidden divide-y divide-slate-100">
                                {items.data.map((item) => (
                                    <li key={item.id} className="p-4 space-y-2">
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <div className="font-semibold text-slate-800 break-words">
                                                    {item.no_bast ?? '(tanpa BAST)'}
                                                </div>
                                                <div className="text-xs text-slate-500">
                                                    {formatTanggalTabel(item.tanggal_bast)} · {item.details.length} item
                                                </div>
                                            </div>
                                            <button
                                                type="button"
                                                onClick={() => setHapus(item)}
                                                aria-label={`Hapus penerimaan ${item.no_bast ?? ''}`}
                                                className="inline-flex min-h-10 shrink-0 items-center gap-1 px-3 rounded-xl text-sm font-semibold text-rose-600 border border-rose-200"
                                            >
                                                <Trash2 className="w-4 h-4" /> Hapus
                                            </button>
                                        </div>
                                        <dl className="grid grid-cols-2 gap-x-3 gap-y-1 text-xs">
                                            <dt className="text-slate-500">Penyedia</dt>
                                            <dd className="text-right text-slate-700">{item.penyedia?.nama ?? '-'}</dd>
                                            <dt className="text-slate-500">No. Nota</dt>
                                            <dd className="text-right text-slate-700">{item.no_nota_invoice ?? '-'}</dd>
                                            <dt className="text-slate-500">Saldo Awal</dt>
                                            <dd className="text-right text-slate-700">
                                                {item.is_saldo_awal ? `Ya (${item.periode_tahun ?? '-'})` : 'Tidak'}
                                            </dd>
                                        </dl>
                                    </li>
                                ))}
                            </ul>

                            <div className="hidden md:block overflow-x-auto">
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
                                        {items.data.map((item) => (
                                            <tr key={item.id}>
                                                <td className="py-3 px-4">{item.no_bast ?? '-'}</td>
                                                <td className="py-3 px-4 whitespace-nowrap">{formatTanggalTabel(item.tanggal_bast)}</td>
                                                <td className="py-3 px-4">{item.no_nota_invoice ?? '-'}</td>
                                                <td className="py-3 px-4">{item.penyedia?.nama ?? '-'}</td>
                                                <td className="py-3 px-4">{item.is_saldo_awal ? `Ya (${item.periode_tahun ?? '-'})` : 'Tidak'}</td>
                                                <td className="py-3 px-4">{item.details.length}</td>
                                                <td className="py-3 px-4 text-center">
                                                    <button
                                                        type="button"
                                                        onClick={() => setHapus(item)}
                                                        className="inline-flex min-h-9 items-center gap-1 px-3 rounded-lg text-sm font-semibold text-rose-600 hover:bg-rose-50"
                                                    >
                                                        <Trash2 className="w-4 h-4" /> Hapus
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </>
                    )}

                    <div className="border-t border-slate-100 px-4">
                        <Pagination links={items.links} total={items.total} />
                    </div>
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
