import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import ConfirmDialog from '@/Components/ConfirmDialog';
import EmptyState from '@/Components/EmptyState';
import Modal from '@/Components/Modal';
import Pagination from '@/Components/Pagination';
import { useEffect, useState } from 'react';
import { formatTanggalTabel, tanggalHariIni } from '@/Utils/format';

interface Detail {
    id: string;
    barang_id: string | null;
    jumlah_diminta: number;
    jumlah_disetujui: number | null;
    keperluan: string | null;
    barang?: { id: string; nama_barang: string; satuan: string | null } | null;
}

interface PengajuanItem {
    id: string;
    tanggal: string | null;
    sumber_dana: string;
    status: string;
    rejection_reason?: string | null;
    details: Detail[];
}

interface Barang {
    id: string;
    kode_barang: string | null;
    nama_barang: string;
    satuan: string | null;
    tersedia: number;
}

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
}

const statusStyle: Record<string, string> = {
    submitted: 'bg-amber-50 text-amber-700',
    approved: 'bg-emerald-50 text-emerald-700',
    rejected: 'bg-rose-50 text-rose-700',
};

const statusLabel: Record<string, string> = {
    submitted: 'Menunggu Review',
    approved: 'Disetujui',
    rejected: 'Ditolak',
};

type Row = { barang_id: string; jumlah_diminta: number; keperluan: string };

const emptyRow = (): Row => ({ barang_id: '', jumlah_diminta: 1, keperluan: '' });

/** Isian yang belum diajukan: disimpan di perangkat, bukan di database. */
const pulihkanDraf = (kunci: string, idBarang: string[]): { tanggal: string; rows: Row[] } => {
    const kosong = { tanggal: tanggalHariIni(), rows: [emptyRow()] };
    try {
        const tersimpan = JSON.parse(localStorage.getItem(kunci) ?? 'null') as { tanggal?: string; rows?: Row[] } | null;
        // Barang yang sudah dihapus dari master dibuang, kalau tidak form
        // menyimpan id mati yang baru ketahuan saat submit.
        const rows = (tersimpan?.rows ?? []).filter((row) => !row.barang_id || idBarang.includes(row.barang_id));
        if (rows.length) {
            return { tanggal: tersimpan?.tanggal || kosong.tanggal, rows };
        }
    } catch {
        // draf rusak, mulai dari isian kosong
    }
    return kosong;
};

export default function Index({
    items,
    sumberDana,
    barang,
}: {
    items: Paginated<PengajuanItem>;
    sumberDana: string;
    barang: Barang[];
}) {
    // Halaman dokumen hidup di bawah prefix sumber dana: route tanpa prefix
    // memakai sumber dana aktif request, bukan sumber dana pengajuannya.
    const prefix = `/distribusi-bhp/${sumberDana.toLowerCase()}`;
    const draftKey = `distribusi_bhp_pengajuan_${sumberDana}`;

    const [detail, setDetail] = useState<PengajuanItem | null>(null);
    const [akanAjukan, setAkanAjukan] = useState(false);
    const [drafAwal] = useState(() => pulihkanDraf(draftKey, barang.map((b) => b.id)));
    const [rows, setRows] = useState<Row[]>(drafAwal.rows);

    const form = useForm({
        sumber_dana: sumberDana,
        tanggal: drafAwal.tanggal,
        items: rows,
    });

    // Isian disimpan per sumber dana supaya draf BOS tidak menimpa BOPD.
    useEffect(() => {
        const timer = setTimeout(() => {
            localStorage.setItem(draftKey, JSON.stringify({ tanggal: form.data.tanggal, rows }));
        }, 400);
        return () => clearTimeout(timer);
    }, [draftKey, form.data.tanggal, rows]);

    const stokBarang = (id: string) => barang.find((b) => b.id === id)?.tersedia ?? 0;

    const updateRow = (index: number, patch: Partial<Row>) =>
        setRows((prev) => prev.map((row, i) => (i === index ? { ...row, ...patch } : row)));

    const ajukan = (e: React.FormEvent) => {
        e.preventDefault();
        setAkanAjukan(true);
    };

    const kirim = () => {
        setAkanAjukan(false);
        form.transform((data) => ({ ...data, items: rows }));
        form.post(`${prefix}/pengajuan`, {
            // Inertia memanggil onSuccess juga saat server membalas dengan
            // flash error (stok kurang). Isian hanya boleh dibersihkan kalau
            // pengajuannya benar-benar tersimpan.
            onSuccess: (page) => {
                const sukses = (page.props as { flash?: { success?: string } }).flash?.success;
                if (!sukses) return;
                localStorage.removeItem(draftKey);
                setRows([emptyRow()]);
                form.setData('tanggal', tanggalHariIni());
            },
        });
    };

    return (
        <AppLayout title="Pengajuan BHP">
            <Head title="Pengajuan BHP" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-lg font-bold text-slate-900">Pengajuan BHP — {sumberDana}</h1>
                    <p className="text-sm text-slate-500">
                        Isian tersimpan otomatis di perangkat ini. Menekan Ajukan akan mereservasi stok.
                    </p>
                </div>

                <form onSubmit={ajukan} className="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm space-y-5 sm:p-6">
                    <div className="max-w-xs">
                        <label htmlFor="tanggal-pengajuan" className="block text-xs font-bold uppercase text-slate-500 mb-1">
                            Tanggal Pengajuan
                        </label>
                        <input
                            id="tanggal-pengajuan"
                            type="date"
                            value={form.data.tanggal}
                            onChange={(e) => form.setData('tanggal', e.target.value)}
                            className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm"
                        />
                        {form.errors.tanggal && <p className="text-xs text-rose-600 mt-1">{form.errors.tanggal}</p>}
                    </div>

                    <div className="space-y-3">
                        <div className="flex items-center justify-between gap-2">
                            <h2 className="text-xs font-bold uppercase tracking-wider text-slate-500">
                                Barang Diminta ({rows.length} baris)
                            </h2>
                            <button
                                type="button"
                                onClick={() => setRows((prev) => [...prev, emptyRow()])}
                                className="inline-flex min-h-9 items-center px-3 text-sm font-semibold text-blue-600 hover:text-blue-700"
                            >
                                + Tambah Baris
                            </button>
                        </div>
                        {rows.map((row, index) => {
                            const tersedia = row.barang_id ? stokBarang(row.barang_id) : null;
                            const melebihi = tersedia !== null && row.jumlah_diminta > tersedia;

                            return (
                                <div key={index} className="grid grid-cols-1 gap-3 rounded-xl border border-slate-200 p-3 sm:grid-cols-12 sm:items-start">
                                    <div className="sm:col-span-5">
                                        <label htmlFor={`barang-${index}`} className="block text-xs font-semibold text-slate-500 mb-1">
                                            Barang
                                        </label>
                                        <select
                                            id={`barang-${index}`}
                                            value={row.barang_id}
                                            onChange={(e) => updateRow(index, { barang_id: e.target.value })}
                                            className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm"
                                        >
                                            <option value="">Pilih barang...</option>
                                            {barang.map((b) => (
                                                <option key={b.id} value={b.id}>
                                                    {b.nama_barang} (tersedia {b.tersedia} {b.satuan})
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div className="sm:col-span-2">
                                        <label htmlFor={`jumlah-${index}`} className="block text-xs font-semibold text-slate-500 mb-1">
                                            Jumlah
                                        </label>
                                        <input
                                            id={`jumlah-${index}`}
                                            type="number"
                                            min={1}
                                            value={row.jumlah_diminta}
                                            onChange={(e) => updateRow(index, { jumlah_diminta: Number(e.target.value) })}
                                            className={`w-full px-3 py-2 border rounded-xl text-sm ${melebihi ? 'border-rose-300 text-rose-700' : 'border-slate-300'}`}
                                        />
                                        <p className={`text-xs mt-1 ${melebihi ? 'text-rose-600 font-semibold' : 'text-slate-500'}`}>
                                            Tersedia: {tersedia ?? '-'}
                                        </p>
                                    </div>
                                    <div className="sm:col-span-4">
                                        <label htmlFor={`keperluan-${index}`} className="block text-xs font-semibold text-slate-500 mb-1">
                                            Keperluan
                                        </label>
                                        <input
                                            id={`keperluan-${index}`}
                                            value={row.keperluan}
                                            onChange={(e) => updateRow(index, { keperluan: e.target.value })}
                                            placeholder="cth. Kegiatan praktik"
                                            className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm"
                                        />
                                    </div>
                                    <div className="sm:col-span-1 sm:pt-5">
                                        <button
                                            type="button"
                                            onClick={() => setRows((prev) => prev.filter((_, i) => i !== index))}
                                            disabled={rows.length === 1}
                                            aria-label={`Hapus baris ${index + 1}`}
                                            className="inline-flex min-h-10 w-full items-center justify-center px-3 text-rose-600 border border-rose-200 rounded-xl text-sm font-semibold disabled:opacity-40 disabled:cursor-not-allowed"
                                        >
                                            Hapus
                                        </button>
                                    </div>
                                </div>
                            );
                        })}
                        {form.errors.items && <p className="text-xs text-rose-600">{form.errors.items}</p>}
                    </div>

                    <button
                        type="submit"
                        disabled={form.processing}
                        className="min-h-11 w-full px-4 bg-blue-600 text-white rounded-xl text-sm font-bold disabled:opacity-50 sm:w-auto"
                    >
                        Ajukan Pengajuan
                    </button>
                </form>

                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div className="px-4 py-4 border-b border-slate-200 sm:px-6">
                        <h2 className="font-bold text-sm text-slate-700">Riwayat Pengajuan</h2>
                    </div>

                    {items.data.length === 0 ? (
                        <EmptyState
                            title="Belum Ada Pengajuan"
                            description={`Belum ada pengajuan untuk sumber dana ${sumberDana}.`}
                        />
                    ) : (
                        <>
                            <ul className="md:hidden divide-y divide-slate-100">
                                {items.data.map((item) => (
                                    <li key={item.id} className="p-4 space-y-3">
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <div className="font-semibold text-slate-800">
                                                    {formatTanggalTabel(item.tanggal)}
                                                </div>
                                                <div className="text-xs text-slate-500">
                                                    {item.sumber_dana} · {item.details.length} item
                                                </div>
                                            </div>
                                            <span className={`shrink-0 px-2.5 py-1 rounded-lg text-xs font-bold ${statusStyle[item.status] ?? 'bg-slate-100 text-slate-700'}`}>
                                                {statusLabel[item.status] ?? item.status}
                                            </span>
                                        </div>
                                        <div className="flex gap-2">
                                            <button
                                                onClick={() => setDetail(item)}
                                                className="inline-flex min-h-10 flex-1 items-center justify-center border border-slate-300 rounded-xl text-sm font-bold"
                                            >
                                                Detail
                                            </button>
                                            <Link
                                                href={`${prefix}/pengajuan/${item.id}/dokumen`}
                                                className="inline-flex min-h-10 flex-1 items-center justify-center border border-slate-300 rounded-xl text-sm font-bold"
                                            >
                                                Dokumen
                                            </Link>
                                        </div>
                                    </li>
                                ))}
                            </ul>

                            <div className="hidden md:block overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="bg-slate-50 text-slate-500 uppercase text-xs">
                                        <tr>
                                            <th className="text-left py-3 px-4">Tanggal</th>
                                            <th className="text-left py-3 px-4">Sumber Dana</th>
                                            <th className="text-left py-3 px-4">Jumlah Item</th>
                                            <th className="text-left py-3 px-4">Status</th>
                                            <th className="text-right py-3 px-4">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {items.data.map((item) => (
                                            <tr key={item.id}>
                                                <td className="py-3 px-4 font-medium text-slate-700 whitespace-nowrap">{formatTanggalTabel(item.tanggal)}</td>
                                                <td className="py-3 px-4">{item.sumber_dana}</td>
                                                <td className="py-3 px-4">{item.details.length}</td>
                                                <td className="py-3 px-4">
                                                    <span className={`px-2.5 py-1 rounded-lg text-xs font-bold ${statusStyle[item.status] ?? 'bg-slate-100 text-slate-700'}`}>
                                                        {statusLabel[item.status] ?? item.status}
                                                    </span>
                                                </td>
                                                <td className="py-3 px-4 text-right">
                                                    <div className="flex justify-end gap-2">
                                                        <button
                                                            onClick={() => setDetail(item)}
                                                            className="min-h-9 px-3 border border-slate-300 rounded-lg text-xs font-bold"
                                                        >
                                                            Detail
                                                        </button>
                                                        <Link
                                                            href={`${prefix}/pengajuan/${item.id}/dokumen`}
                                                            className="inline-flex min-h-9 items-center px-3 border border-slate-300 rounded-lg text-xs font-bold"
                                                        >
                                                            Dokumen
                                                        </Link>
                                                    </div>
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

            <Modal
                isOpen={detail !== null}
                onClose={() => setDetail(null)}
                title={`Detail Pengajuan — ${detail?.sumber_dana ?? ''}`}
                maxWidth="2xl"
            >
                {detail && (
                    <div className="space-y-3">
                        <div className="flex items-center justify-between">
                            <div className="text-sm text-slate-500">Tanggal: {formatTanggalTabel(detail.tanggal)}</div>
                                            <span className={`px-2.5 py-1 rounded-lg text-xs font-bold ${statusStyle[detail.status] ?? 'bg-slate-100 text-slate-700'}`}>
                                                {statusLabel[detail.status] ?? detail.status}
                                            </span>
                        </div>
                        {detail.rejection_reason && (
                            <div className="bg-rose-50 border border-rose-200 text-rose-700 rounded-xl px-3 py-2 text-sm">
                                Ditolak: {detail.rejection_reason}
                            </div>
                        )}
                        {detail.details.map((d) => (
                            <div key={d.id} className="flex items-center justify-between border border-slate-200 rounded-xl p-3 text-sm">
                                <div>
                                    <div className="font-semibold text-slate-800">{d.barang?.nama_barang ?? '-'}</div>
                                    {d.keperluan && <div className="text-xs text-slate-500">Keperluan: {d.keperluan}</div>}
                                </div>
                                <div className="text-right">
                                    <div>Diminta: {d.jumlah_diminta} {d.barang?.satuan}</div>
                                    {d.jumlah_disetujui !== null && (
                                        <div className="text-emerald-600">Disetujui: {d.jumlah_disetujui}</div>
                                    )}
                                </div>
                            </div>
                        ))}
                        <div className="flex justify-end gap-2 pt-2">
                            <Link
                                href={`${prefix}/pengajuan/${detail.id}/dokumen`}
                                className="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-semibold"
                            >
                                Lihat Dokumen
                            </Link>
                            <button onClick={() => setDetail(null)} className="px-4 py-2 border border-slate-300 rounded-xl text-sm font-semibold">
                                Tutup
                            </button>
                        </div>
                    </div>
                )}
            </Modal>

            <ConfirmDialog
                isOpen={akanAjukan}
                onClose={() => setAkanAjukan(false)}
                onConfirm={kirim}
                title="Ajukan Pengajuan"
                message={`${rows.length} baris barang akan diajukan dan stoknya langsung direservasi. Setelah diajukan, pengajuan tidak bisa diubah lagi.`}
                confirmText="Ya, Ajukan"
                isDestructive={false}
            />
        </AppLayout>
    );
}
