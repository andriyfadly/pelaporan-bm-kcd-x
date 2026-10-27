import { Head, router, useForm } from '@inertiajs/react';
import React, { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Modal from '@/Components/Modal';
import ConfirmDialog from '@/Components/ConfirmDialog';
import EmptyState from '@/Components/EmptyState';
import { Building2, Pencil, Plus, Trash2 } from 'lucide-react';

interface Ruang {
    id: string;
    nama_ruang: string;
    kode: string | null;
    unit_aktif_count: number;
}

interface Props {
    ruang: Ruang[];
}

export default function Index({ ruang }: Props) {
    const [modalOpen, setModalOpen] = useState(false);
    const [editTarget, setEditTarget] = useState<Ruang | null>(null);
    const [hapusTarget, setHapusTarget] = useState<Ruang | null>(null);

    const form = useForm({ nama_ruang: '', kode: '' });

    const buka = (item: Ruang | null) => {
        setEditTarget(item);
        form.setData({ nama_ruang: item?.nama_ruang ?? '', kode: item?.kode ?? '' });
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
            form.put(`/master-data/ruang/${editTarget.id}`, options);
            return;
        }

        form.post('/master-data/ruang', options);
    };

    const hapus = () => {
        if (!hapusTarget) {
            return;
        }

        router.delete(`/master-data/ruang/${hapusTarget.id}`, {
            preserveScroll: true,
            onFinish: () => setHapusTarget(null),
        });
    };

    return (
        <AppLayout>
            <Head title="Master Ruang" />

            <div className="space-y-5">
                <header className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-bold text-slate-800">Master Ruang</h1>
                        <p className="text-sm text-slate-500">Ruang sekolah dipakai sebagai penempatan unit inventaris.</p>
                    </div>
                    <button
                        type="button"
                        onClick={() => buka(null)}
                        className="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700"
                    >
                        <Plus className="w-4 h-4" /> Tambah Ruang
                    </button>
                </header>

                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    {ruang.length === 0 ? (
                        <EmptyState
                            title="Belum ada ruang"
                            description="Tambahkan ruang terlebih dahulu agar unit inventaris bisa ditempatkan."
                            icon={<Building2 className="w-12 h-12 text-slate-300" />}
                        />
                    ) : (
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-400">
                                    <th className="px-4 py-3">Nama Ruang</th>
                                    <th className="px-4 py-3">Kode</th>
                                    <th className="px-4 py-3 text-right">Unit Aktif</th>
                                    <th className="px-4 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {ruang.map((item) => (
                                    <tr key={item.id} className="border-b border-slate-50 last:border-0">
                                        <td className="px-4 py-3 font-semibold text-slate-700">{item.nama_ruang}</td>
                                        <td className="px-4 py-3 text-slate-500">{item.kode ?? '-'}</td>
                                        <td className="px-4 py-3 text-right text-slate-600">{item.unit_aktif_count}</td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-end gap-1">
                                                <button
                                                    type="button"
                                                    onClick={() => buka(item)}
                                                    aria-label={`Ubah ruang ${item.nama_ruang}`}
                                                    className="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100"
                                                >
                                                    <Pencil className="w-4 h-4" />
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => setHapusTarget(item)}
                                                    aria-label={`Hapus ruang ${item.nama_ruang}`}
                                                    className="rounded-lg p-1.5 text-rose-600 hover:bg-rose-50"
                                                >
                                                    <Trash2 className="w-4 h-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            </div>

            <Modal isOpen={modalOpen} onClose={() => setModalOpen(false)} title={editTarget ? 'Ubah Ruang' : 'Tambah Ruang'}>
                <form onSubmit={simpan} className="space-y-3">
                    <label className="block text-xs font-semibold text-slate-600">
                        Nama Ruang
                        <input
                            type="text"
                            value={form.data.nama_ruang}
                            onChange={(e) => form.setData('nama_ruang', e.target.value)}
                            className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                        />
                        {form.errors.nama_ruang && <p className="mt-1 text-xs text-rose-600">{form.errors.nama_ruang}</p>}
                    </label>
                    <label className="block text-xs font-semibold text-slate-600">
                        Kode
                        <input
                            type="text"
                            value={form.data.kode}
                            onChange={(e) => form.setData('kode', e.target.value)}
                            className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                        />
                        {form.errors.kode && <p className="mt-1 text-xs text-rose-600">{form.errors.kode}</p>}
                    </label>
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
                title="Hapus Ruang"
                message={`Unit aktif di ${hapusTarget?.nama_ruang ?? 'ruang ini'} kehilangan penempatan, tetapi riwayat mutasinya tetap tersimpan.`}
                confirmText="Hapus"
            />
        </AppLayout>
    );
}
