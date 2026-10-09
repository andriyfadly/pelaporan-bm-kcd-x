import { Head, Link, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useState } from 'react';

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
}

const statusStyle: Record<string, string> = {
    draft: 'bg-slate-100 text-slate-700',
    submitted: 'bg-amber-50 text-amber-700',
    approved: 'bg-emerald-50 text-emerald-700',
    rejected: 'bg-rose-50 text-rose-700',
};

type Row = { barang_id: string; jumlah_diminta: number; keperluan: string };

const emptyRow = (): Row => ({ barang_id: '', jumlah_diminta: 1, keperluan: '' });

export default function Index({
    items,
    sumberDana,
    barang,
}: {
    items: Paginated<PengajuanItem>;
    sumberDana: string;
    barang: Barang[];
}) {
    const [rows, setRows] = useState<Row[]>([emptyRow()]);
    const [detail, setDetail] = useState<PengajuanItem | null>(null);
    const [editing, setEditing] = useState<PengajuanItem | null>(null);
    const [editRows, setEditRows] = useState<Row[]>([emptyRow()]);
    const [editTanggal, setEditTanggal] = useState('');

    const form = useForm({
        sumber_dana: sumberDana,
        tanggal: new Date().toISOString().slice(0, 10),
        items: rows,
    });

    const stokBarang = (id: string) => barang.find((b) => b.id === id)?.tersedia ?? 0;

    const updateRow = (index: number, patch: Partial<Row>) =>
        setRows((prev) => prev.map((row, i) => (i === index ? { ...row, ...patch } : row)));

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.transform((data) => ({ ...data, items: rows }));
        form.post('/distribusi-bhp/pengajuan', {
            onSuccess: () => {
                setRows([emptyRow()]);
                form.reset('tanggal');
            },
        });
    };

    const startEdit = (item: PengajuanItem) => {
        setDetail(null);
        setEditing(item);
        setEditTanggal(item.tanggal ?? new Date().toISOString().slice(0, 10));
        setEditRows(
            item.details.length
                ? item.details.map((d) => ({
                      barang_id: d.barang_id ?? '',
                      jumlah_diminta: d.jumlah_diminta,
                      keperluan: d.keperluan ?? '',
                  }))
                : [emptyRow()],
        );
    };

    const saveEdit = () => {
        if (!editing) return;
        router.put(
            `/distribusi-bhp/pengajuan/${editing.id}`,
            { sumber_dana: editing.sumber_dana, tanggal: editTanggal, items: editRows },
            { onSuccess: () => setEditing(null) },
        );
    };

    const doSubmit = (item: PengajuanItem) =>
        router.post(`/distribusi-bhp/pengajuan/${item.id}/submit`, {}, { onSuccess: () => setDetail(null) });

    const doDelete = (item: PengajuanItem) => {
        if (!confirm('Hapus pengajuan draft ini?')) return;
        router.delete(`/distribusi-bhp/pengajuan/${item.id}`, { onSuccess: () => setDetail(null) });
    };

    const editUpdateRow = (index: number, patch: Partial<Row>) =>
        setEditRows((prev) => prev.map((row, i) => (i === index ? { ...row, ...patch } : row)));

    return (
        <AppLayout title="Pengajuan BHP">
            <Head title="Pengajuan BHP" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-lg font-bold text-slate-900">Pengajuan BHP — {sumberDana}</h1>
                        <p className="text-sm text-slate-500">Buat draft, lalu submit untuk mereservasi stok.</p>
                    </div>
                    <div className="flex gap-2">
                        {['BOS', 'BOPD'].map((dana) => (
                            <Link
                                key={dana}
                                href={`/distribusi-bhp/pengajuan?sumber_dana=${dana}`}
                                className={`px-3 py-2 rounded-xl text-sm font-semibold border ${
                                    dana === sumberDana
                                        ? 'bg-blue-600 text-white border-blue-600'
                                        : 'bg-white text-slate-700 border-slate-300'
                                }`}
                            >
                                {dana}
                            </Link>
                        ))}
                    </div>
                </div>

                <form onSubmit={submit} className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-4">
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <input
                            type="date"
                            value={form.data.tanggal}
                            onChange={(e) => form.setData('tanggal', e.target.value)}
                            className="px-3 py-2 border border-slate-300 rounded-xl text-sm"
                        />
                        {form.errors.tanggal && <span className="text-xs text-rose-600">{form.errors.tanggal}</span>}
                        {form.errors.items && <span className="text-xs text-rose-600 col-span-2">{form.errors.items}</span>}
                    </div>

                    <div className="space-y-2">
                        {rows.map((row, index) => (
                            <div key={index} className="grid grid-cols-1 sm:grid-cols-4 gap-3 items-center">
                                <select
                                    value={row.barang_id}
                                    onChange={(e) => updateRow(index, { barang_id: e.target.value })}
                                    className="px-3 py-2 border border-slate-300 rounded-xl text-sm"
                                >
                                    <option value="">Pilih barang...</option>
                                    {barang.map((b) => (
                                        <option key={b.id} value={b.id}>
                                            {b.nama_barang} (tersedia {b.tersedia} {b.satuan})
                                        </option>
                                    ))}
                                </select>
                                <div className="text-xs text-slate-500">
                                    Tersedia: <strong>{row.barang_id ? stokBarang(row.barang_id) : '-'}</strong>
                                </div>
                                <input
                                    type="number"
                                    min={1}
                                    value={row.jumlah_diminta}
                                    onChange={(e) => updateRow(index, { jumlah_diminta: Number(e.target.value) })}
                                    placeholder="Jumlah"
                                    className="px-3 py-2 border border-slate-300 rounded-xl text-sm"
                                />
                                <div className="flex gap-2">
                                    <input
                                        value={row.keperluan}
                                        onChange={(e) => updateRow(index, { keperluan: e.target.value })}
                                        placeholder="Keperluan"
                                        className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setRows((prev) => prev.filter((_, i) => i !== index))}
                                        className="px-3 py-2 text-rose-600 border border-rose-200 rounded-xl text-sm font-semibold"
                                    >
                                        Hapus
                                    </button>
                                </div>
                            </div>
                        ))}
                        <button type="button" onClick={() => setRows((prev) => [...prev, emptyRow()])} className="text-sm font-semibold text-blue-600">
                            + Tambah Baris
                        </button>
                    </div>

                    <button type="submit" disabled={form.processing} className="px-4 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-bold disabled:opacity-50">
                        Simpan sebagai Draft
                    </button>
                </form>

                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
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
                            {items.data.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="py-10 text-center text-slate-400 text-xs">
                                        Belum ada pengajuan untuk {sumberDana}.
                                    </td>
                                </tr>
                            ) : (
                                items.data.map((item) => (
                                    <tr key={item.id}>
                                        <td className="py-3 px-4 font-medium text-slate-700">{item.tanggal ?? '-'}</td>
                                        <td className="py-3 px-4">{item.sumber_dana}</td>
                                        <td className="py-3 px-4">{item.details.length}</td>
                                        <td className="py-3 px-4">
                                            <span className={`px-2.5 py-1 rounded-lg text-[11px] font-bold ${statusStyle[item.status] ?? 'bg-slate-100 text-slate-700'}`}>
                                                {item.status}
                                            </span>
                                        </td>
                                        <td className="py-3 px-4 text-right">
                                            <div className="flex justify-end gap-2">
                                                <button onClick={() => setDetail(item)} className="px-3 py-1.5 border border-slate-300 rounded-lg text-xs font-bold">
                                                    Detail
                                                </button>
                                                {item.status === 'draft' && (
                                                    <>
                                                        <button onClick={() => startEdit(item)} className="px-3 py-1.5 border border-blue-300 text-blue-700 rounded-lg text-xs font-bold">
                                                            Edit
                                                        </button>
                                                        <button onClick={() => doSubmit(item)} className="px-3 py-1.5 bg-emerald-600 text-white rounded-lg text-xs font-bold">
                                                            Submit
                                                        </button>
                                                    </>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {detail && (
                <div className="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
                    <div className="bg-white rounded-2xl w-full max-w-2xl shadow-xl">
                        <div className="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                            <h2 className="font-bold text-slate-900">Detail Pengajuan — {detail.sumber_dana}</h2>
                            <span className={`px-2.5 py-1 rounded-lg text-[11px] font-bold ${statusStyle[detail.status] ?? 'bg-slate-100 text-slate-700'}`}>
                                {detail.status}
                            </span>
                        </div>
                        <div className="p-6 space-y-3">
                            <div className="text-sm text-slate-500">Tanggal: {detail.tanggal ?? '-'}</div>
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
                        </div>
                        <div className="px-6 py-4 border-t border-slate-200 flex justify-end gap-2">
                            {detail.status === 'draft' && (
                                <button onClick={() => doDelete(detail)} className="px-4 py-2 border border-rose-300 text-rose-700 rounded-xl text-sm font-semibold">
                                    Hapus Draft
                                </button>
                            )}
                            {detail.status === 'draft' && (
                                <button onClick={() => doSubmit(detail)} className="px-4 py-2 bg-emerald-600 text-white rounded-xl text-sm font-semibold">
                                    Submit
                                </button>
                            )}
                            <button onClick={() => setDetail(null)} className="px-4 py-2 border border-slate-300 rounded-xl text-sm font-semibold">
                                Tutup
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {editing && (
                <div className="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
                    <div className="bg-white rounded-2xl w-full max-w-2xl shadow-xl">
                        <div className="px-6 py-4 border-b border-slate-200">
                            <h2 className="font-bold text-slate-900">Ubah Draft Pengajuan — {editing.sumber_dana}</h2>
                        </div>
                        <div className="p-6 space-y-4">
                            <input
                                type="date"
                                value={editTanggal}
                                onChange={(e) => setEditTanggal(e.target.value)}
                                className="px-3 py-2 border border-slate-300 rounded-xl text-sm"
                            />
                            {editRows.map((row, index) => (
                                <div key={index} className="grid grid-cols-1 sm:grid-cols-4 gap-3 items-center">
                                    <select
                                        value={row.barang_id}
                                        onChange={(e) => editUpdateRow(index, { barang_id: e.target.value })}
                                        className="px-3 py-2 border border-slate-300 rounded-xl text-sm"
                                    >
                                        <option value="">Pilih barang...</option>
                                        {barang.map((b) => (
                                            <option key={b.id} value={b.id}>
                                                {b.nama_barang} (tersedia {b.tersedia} {b.satuan})
                                            </option>
                                        ))}
                                    </select>
                                    <input
                                        type="number"
                                        min={1}
                                        value={row.jumlah_diminta}
                                        onChange={(e) => editUpdateRow(index, { jumlah_diminta: Number(e.target.value) })}
                                        className="px-3 py-2 border border-slate-300 rounded-xl text-sm"
                                    />
                                    <input
                                        value={row.keperluan}
                                        onChange={(e) => editUpdateRow(index, { keperluan: e.target.value })}
                                        placeholder="Keperluan"
                                        className="px-3 py-2 border border-slate-300 rounded-xl text-sm"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setEditRows((prev) => prev.filter((_, i) => i !== index))}
                                        className="px-3 py-2 text-rose-600 border border-rose-200 rounded-xl text-sm font-semibold"
                                    >
                                        Hapus
                                    </button>
                                </div>
                            ))}
                            <button type="button" onClick={() => setEditRows((prev) => [...prev, emptyRow()])} className="text-sm font-semibold text-blue-600">
                                + Tambah Baris
                            </button>
                        </div>
                        <div className="px-6 py-4 border-t border-slate-200 flex justify-end gap-2">
                            <button onClick={() => setEditing(null)} className="px-4 py-2 border border-slate-300 rounded-xl text-sm font-semibold">
                                Batal
                            </button>
                            <button onClick={saveEdit} className="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-semibold">
                                Simpan Perubahan
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
