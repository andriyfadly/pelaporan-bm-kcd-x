import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import EmptyState from '@/Components/EmptyState';
import Modal from '@/Components/Modal';
import Pagination from '@/Components/Pagination';
import { formatTanggalTabel } from '@/Utils/format';
import { useState } from 'react';

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

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
}

export default function LunasBayar({
    items,
    sumberDana,
}: {
    items: Paginated<PenerimaanItem>;
    sumberDana: string;
}) {
    const [bayar, setBayar] = useState<PenerimaanItem | null>(null);
    const form = useForm({ no_kwitansi: '', tanggal_pembayaran: '' });

    const buka = (item: PenerimaanItem) => {
        form.reset();
        form.clearErrors();
        setBayar(item);
    };

    const simpan = (e: React.FormEvent) => {
        e.preventDefault();

        if (!bayar) return;

        form.patch(`/distribusi-bhp/${sumberDana.toLowerCase()}/penerimaan/${bayar.id}/pembayaran`, {
            preserveScroll: true,
            onSuccess: () => setBayar(null),
        });
    };

    return (
        <AppLayout title={`Lunas Bayar — ${sumberDana}`}>
            <Head title={`Lunas Bayar — ${sumberDana}`} />

            <div className="space-y-6">
                <div>
                    <h1 className="text-lg font-bold text-slate-900">Lunas Bayar — {sumberDana}</h1>
                    <p className="text-sm text-slate-500 mt-1">
                        Barang sudah diterima tetapi transaksi keuangannya belum terjadi. Isi No. Kwitansi dan Tanggal
                        Pembayaran agar baris keluar dari daftar ini dan keterangan “Lunas Bayar” hilang dari Buku
                        Penerimaan.
                    </p>
                </div>

                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    {items.data.length === 0 ? (
                        <EmptyState
                            title="Semua Sudah Dibayar"
                            description="Tidak ada penerimaan yang menunggu transaksi keuangan."
                        />
                    ) : (
                        <>
                            <ul className="md:hidden divide-y divide-slate-100">
                                {items.data.map((item) => (
                                    <li key={item.id} className="p-4 space-y-3">
                                        <div>
                                            <div className="font-semibold text-slate-800 break-words">
                                                {item.no_nota_invoice ?? '(tanpa nota)'}
                                            </div>
                                            <div className="text-xs text-slate-500">
                                                {formatTanggalTabel(item.tanggal_bast ?? item.tanggal_titimangsa)} ·{' '}
                                                {item.details.length} barang
                                            </div>
                                        </div>
                                        <dl className="grid grid-cols-2 gap-x-3 gap-y-1 text-xs">
                                            <dt className="text-slate-500">No. BAST</dt>
                                            <dd className="text-right text-slate-700">{item.no_bast ?? '-'}</dd>
                                            <dt className="text-slate-500">Penyedia</dt>
                                            <dd className="text-right text-slate-700">{item.penyedia?.nama ?? '-'}</dd>
                                        </dl>
                                        <button
                                            type="button"
                                            onClick={() => buka(item)}
                                            className="min-h-10 w-full rounded-xl bg-blue-600 text-sm font-bold text-white"
                                        >
                                            Tandai Bayar
                                        </button>
                                    </li>
                                ))}
                            </ul>

                            <div className="hidden md:block overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="bg-slate-50 text-slate-500 uppercase text-xs">
                                        <tr>
                                            <th className="text-left py-3 px-4">No. Invoice/Nota</th>
                                            <th className="text-left py-3 px-4">No. BAST</th>
                                            <th className="text-left py-3 px-4">Penyedia</th>
                                            <th className="text-left py-3 px-4">Tanggal Efektif</th>
                                            <th className="text-right py-3 px-4">Jumlah Barang</th>
                                            <th className="text-center py-3 px-4">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {items.data.map((item) => (
                                            <tr key={item.id}>
                                                <td className="py-3 px-4">{item.no_nota_invoice ?? '-'}</td>
                                                <td className="py-3 px-4">{item.no_bast ?? '-'}</td>
                                                <td className="py-3 px-4">{item.penyedia?.nama ?? '-'}</td>
                                                <td className="py-3 px-4 whitespace-nowrap">{formatTanggalTabel(item.tanggal_bast ?? item.tanggal_titimangsa)}</td>
                                                <td className="py-3 px-4 text-right">{item.details.length}</td>
                                                <td className="py-3 px-4 text-center">
                                                    <button
                                                        type="button"
                                                        onClick={() => buka(item)}
                                                        className="inline-flex min-h-9 items-center px-3 rounded-lg text-sm font-semibold text-blue-600 hover:bg-blue-50"
                                                    >
                                                        Tandai Bayar
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
            </div>

            <Modal isOpen={bayar !== null} onClose={() => setBayar(null)} title="Tandai Pembayaran">
                <form onSubmit={simpan} className="space-y-4">
                    <p className="text-sm text-slate-600">
                        Penerimaan {bayar?.no_bast ?? '-'} akan ditandai sudah dibayar.
                    </p>

                    <div>
                        <label htmlFor="bayar-kwitansi" className="block text-xs font-bold uppercase text-slate-500 mb-1">
                            No. Kwitansi
                        </label>
                        <input
                            id="bayar-kwitansi"
                            value={form.data.no_kwitansi}
                            onChange={(e) => form.setData('no_kwitansi', e.target.value)}
                            className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm"
                        />
                        {form.errors.no_kwitansi && <p className="text-xs text-rose-600 mt-1">{form.errors.no_kwitansi}</p>}
                    </div>

                    <div>
                        <label htmlFor="bayar-tanggal" className="block text-xs font-bold uppercase text-slate-500 mb-1">
                            Tanggal Pembayaran
                        </label>
                        <input
                            id="bayar-tanggal"
                            type="date"
                            value={form.data.tanggal_pembayaran}
                            onChange={(e) => form.setData('tanggal_pembayaran', e.target.value)}
                            className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm"
                        />
                        {form.errors.tanggal_pembayaran && (
                            <p className="text-xs text-rose-600 mt-1">{form.errors.tanggal_pembayaran}</p>
                        )}
                    </div>

                    <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button
                            type="button"
                            onClick={() => setBayar(null)}
                            className="min-h-10 px-4 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-100"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            disabled={form.processing}
                            className="min-h-10 px-4 rounded-xl text-sm font-bold bg-blue-600 text-white disabled:opacity-50"
                        >
                            Simpan
                        </button>
                    </div>
                </form>
            </Modal>
        </AppLayout>
    );
}
