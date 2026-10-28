import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import Modal from '@/Components/Modal';
import { formatTanggal } from '@/Utils/format';
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

export default function LunasBayar({
    items,
    sumberDana,
}: {
    items: { data: PenerimaanItem[] };
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
                            {items.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="py-10 text-center text-slate-400 text-xs">
                                        Semua penerimaan sudah dibayar.
                                    </td>
                                </tr>
                            ) : (
                                items.data.map((item) => (
                                    <tr key={item.id}>
                                        <td className="py-3 px-4">{item.no_nota_invoice ?? '-'}</td>
                                        <td className="py-3 px-4">{item.no_bast ?? '-'}</td>
                                        <td className="py-3 px-4">{item.penyedia?.nama ?? '-'}</td>
                                        <td className="py-3 px-4">{formatTanggal(item.tanggal_bast ?? item.tanggal_titimangsa)}</td>
                                        <td className="py-3 px-4 text-right">{item.details.length}</td>
                                        <td className="py-3 px-4 text-center">
                                            <button
                                                type="button"
                                                onClick={() => buka(item)}
                                                className="px-3 py-1.5 rounded-lg text-xs font-semibold text-blue-600 hover:bg-blue-50"
                                            >
                                                Tandai Bayar
                                            </button>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
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
                        {form.errors.no_kwitansi && <p className="text-[11px] text-rose-600 mt-1">{form.errors.no_kwitansi}</p>}
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
                            <p className="text-[11px] text-rose-600 mt-1">{form.errors.tanggal_pembayaran}</p>
                        )}
                    </div>

                    <div className="flex justify-end gap-2">
                        <button
                            type="button"
                            onClick={() => setBayar(null)}
                            className="px-4 py-2 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-100"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            disabled={form.processing}
                            className="px-4 py-2 rounded-xl text-sm font-bold bg-blue-600 text-white disabled:opacity-50"
                        >
                            Simpan
                        </button>
                    </div>
                </form>
            </Modal>
        </AppLayout>
    );
}
