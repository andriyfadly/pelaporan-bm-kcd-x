import { Head, useForm } from '@inertiajs/react';
import React, { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Modal from '@/Components/Modal';
import ConfirmDialog from '@/Components/ConfirmDialog';
import EmptyState from '@/Components/EmptyState';
import { BadgeCheck, Lock, Pencil, Plus, Trash2 } from 'lucide-react';

interface Jabatan {
    id: string;
    kode: string;
    nama: string;
    kategori: string | null;
    aktif: boolean;
    wajib: boolean;
    penugasan_aktif_count: number;
}

interface Props {
    jabatan: Jabatan[];
    kategoriList: string[];
}

export default function Index({ jabatan, kategoriList }: Props) {
    const [modalOpen, setModalOpen] = useState(false);
    const [editTarget, setEditTarget] = useState<Jabatan | null>(null);
    const [hapusTarget, setHapusTarget] = useState<Jabatan | null>(null);

    const form = useForm({ kode: '', nama: '', kategori: '', aktif: true as boolean });

    const buka = (item: Jabatan | null) => {
        setEditTarget(item);
        form.setData({
            kode: item?.kode ?? '',
            nama: item?.nama ?? '',
            kategori: item?.kategori ?? '',
            aktif: item?.aktif ?? true,
        });
        form.clearErrors();
        setModalOpen(true);
    };

    const simpan = (e: React.FormEvent) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setModalOpen(false);
                form.reset();
            },
        };

        if (editTarget) {
            form.put(`/master-data/jabatan/${editTarget.id}`, options);
            return;
        }

        form.post('/master-data/jabatan', options);
    };

    const hapus = () => {
        if (!hapusTarget) {
            return;
        }

        form.delete(`/master-data/jabatan/${hapusTarget.id}`, {
            preserveScroll: true,
            onFinish: () => setHapusTarget(null),
        });
    };

    return (
        <AppLayout>
            <Head title="Master Jabatan" />

            <div className="space-y-5">
                <header className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-bold text-slate-800">Master Jabatan</h1>
                        <p className="text-sm text-slate-500">
                            Jabatan sekolah yang bisa diampu pegawai. Kepala Sekolah, Wakasek Sarpras, dan Pembantu Pengurus
                            Barang wajib ada karena dipakai sebagai penandatangan dokumen.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={() => buka(null)}
                        className="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700"
                    >
                        <Plus className="w-4 h-4" /> Tambah Jabatan
                    </button>
                </header>

                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    {jabatan.length === 0 ? (
                        <EmptyState
                            title="Belum ada jabatan"
                            description="Tambahkan jabatan agar pegawai punya riwayat penugasan."
                            icon={<BadgeCheck className="w-12 h-12 text-slate-300" />}
                        />
                    ) : (
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-400">
                                    <th className="px-4 py-3">Nama Jabatan</th>
                                    <th className="px-4 py-3">Kode</th>
                                    <th className="px-4 py-3">Kategori</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3 text-right">Pemegang Aktif</th>
                                    <th className="px-4 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {jabatan.map((item) => (
                                    <tr key={item.id} className="border-b border-slate-50 last:border-0">
                                        <td className="px-4 py-3 font-semibold text-slate-700">
                                            {item.nama}
                                            {item.wajib && (
                                                <span className="ml-2 rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-bold text-blue-700">
                                                    Wajib
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 font-mono text-xs text-slate-500">{item.kode}</td>
                                        <td className="px-4 py-3 text-slate-600">{item.kategori ?? '-'}</td>
                                        <td className="px-4 py-3">
                                            <span
                                                className={`rounded-full px-2 py-0.5 text-[11px] font-bold ${
                                                    item.aktif ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'
                                                }`}
                                            >
                                                {item.aktif ? 'Aktif' : 'Nonaktif'}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-right text-slate-600">{item.penugasan_aktif_count}</td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-end gap-1">
                                                <button
                                                    type="button"
                                                    onClick={() => buka(item)}
                                                    aria-label={`Ubah jabatan ${item.nama}`}
                                                    className="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100"
                                                >
                                                    <Pencil className="w-4 h-4" />
                                                </button>
                                                {item.wajib ? (
                                                    <span
                                                        title="Jabatan wajib penandatangan tidak dapat dihapus"
                                                        className="rounded-lg p-1.5 text-slate-300"
                                                    >
                                                        <Lock className="w-4 h-4" />
                                                    </span>
                                                ) : (
                                                    <button
                                                        type="button"
                                                        onClick={() => setHapusTarget(item)}
                                                        aria-label={`Hapus jabatan ${item.nama}`}
                                                        className="rounded-lg p-1.5 text-rose-600 hover:bg-rose-50"
                                                    >
                                                        <Trash2 className="w-4 h-4" />
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            </div>

            <Modal isOpen={modalOpen} onClose={() => setModalOpen(false)} title={editTarget ? 'Ubah Jabatan' : 'Tambah Jabatan'}>
                <form onSubmit={simpan} className="space-y-3">
                    <label className="block text-xs font-semibold text-slate-600">
                        Kode
                        <input
                            type="text"
                            value={form.data.kode}
                            onChange={(e) => form.setData('kode', e.target.value)}
                            placeholder="kepala_sekolah"
                            disabled={editTarget?.wajib ?? false}
                            className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 font-mono text-sm disabled:bg-slate-50 disabled:text-slate-400"
                        />
                        {form.errors.kode && <p className="mt-1 text-xs text-rose-600">{form.errors.kode}</p>}
                    </label>
                    <label className="block text-xs font-semibold text-slate-600">
                        Nama Jabatan
                        <input
                            type="text"
                            value={form.data.nama}
                            onChange={(e) => form.setData('nama', e.target.value)}
                            disabled={editTarget?.wajib ?? false}
                            className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm disabled:bg-slate-50 disabled:text-slate-400"
                        />
                        {form.errors.nama && <p className="mt-1 text-xs text-rose-600">{form.errors.nama}</p>}
                    </label>
                    {editTarget?.wajib && (
                        <p className="rounded-xl bg-blue-50 px-3 py-2 text-[11px] font-semibold text-blue-700">
                            Jabatan wajib penandatangan: kode dan nama dikunci, hanya kategori yang dapat diubah.
                        </p>
                    )}
                    <label className="block text-xs font-semibold text-slate-600">
                        Kategori
                        <select
                            value={form.data.kategori}
                            onChange={(e) => form.setData('kategori', e.target.value)}
                            className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                        >
                            <option value="">-</option>
                            {kategoriList.map((kategori) => (
                                <option key={kategori} value={kategori}>
                                    {kategori}
                                </option>
                            ))}
                        </select>
                    </label>
                    {!editTarget?.wajib && (
                        <label className="flex items-center gap-2 text-xs font-semibold text-slate-600">
                            <input
                                type="checkbox"
                                checked={form.data.aktif}
                                onChange={(e) => form.setData('aktif', e.target.checked)}
                                className="rounded border-slate-300"
                            />
                            Aktif (muncul sebagai pilihan penugasan)
                        </label>
                    )}
                    <div className="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            onClick={() => setModalOpen(false)}
                            className="rounded-xl px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            disabled={form.processing}
                            className="rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700 disabled:opacity-60"
                        >
                            Simpan
                        </button>
                    </div>
                </form>
            </Modal>

            <ConfirmDialog
                isOpen={hapusTarget !== null}
                onClose={() => setHapusTarget(null)}
                onConfirm={hapus}
                title="Hapus Jabatan"
                message={
                    hapusTarget && hapusTarget.penugasan_aktif_count > 0
                        ? `${hapusTarget.nama} masih dipegang pegawai. Jabatan akan dinonaktifkan agar riwayat penugasan tetap utuh.`
                        : `${hapusTarget?.nama ?? 'Jabatan ini'} akan dihapus. Riwayat penugasan yang sudah ada tetap tersimpan.`
                }
                confirmText={hapusTarget && hapusTarget.penugasan_aktif_count > 0 ? 'Nonaktifkan' : 'Hapus'}
            />
        </AppLayout>
    );
}
