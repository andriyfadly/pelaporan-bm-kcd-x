import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Modal from '@/Components/Modal';
import ConfirmDialog from '@/Components/ConfirmDialog';
import { Pencil, Plus, Trash2, Truck } from 'lucide-react';

interface PenyediaItem {
    id: string;
    nama: string;
    alamat: string | null;
    telepon: string | null;
    email: string | null;
}

export default function Index({ items }: { items: { data: PenyediaItem[] } }) {
    const [showCreate, setShowCreate] = useState(false);
    const [editTarget, setEditTarget] = useState<PenyediaItem | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<PenyediaItem | null>(null);

    const createForm = useForm({ nama: '', alamat: '', telepon: '', email: '' });

    const submitCreate = (e: React.FormEvent) => {
        e.preventDefault();
        createForm.post('/distribusi-bhp/penyedia', {
            preserveScroll: true,
            onSuccess: () => {
                createForm.reset();
                setShowCreate(false);
            },
        });
    };

    const editForm = useForm({ nama: '', alamat: '', telepon: '', email: '' });

    const openEdit = (item: PenyediaItem) => {
        setEditTarget(item);
        editForm.setData({
            nama: item.nama,
            alamat: item.alamat ?? '',
            telepon: item.telepon ?? '',
            email: item.email ?? '',
        });
    };

    const submitEdit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editTarget) return;
        editForm.put(`/distribusi-bhp/penyedia/${editTarget.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setEditTarget(null);
                editForm.reset();
            },
        });
    };

    const confirmDelete = () => {
        if (!deleteTarget) return;
        router.delete(`/distribusi-bhp/penyedia/${deleteTarget.id}`, { preserveScroll: true });
    };

    return (
        <AppLayout title="Penyedia BHP">
            <Head title="Penyedia BHP" />

            <div className="space-y-6">
                <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-lg font-bold text-slate-900 flex items-center gap-2">
                            <Truck className="w-5 h-5 text-blue-600" /> Penyedia
                        </h1>
                        <p className="text-sm text-slate-500 mt-1">
                            Data rekanan/supplier untuk pengisian dokumen penerimaan barang.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={() => setShowCreate(true)}
                        className="inline-flex shrink-0 items-center gap-1.5 px-4 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-bold hover:bg-blue-700 transition"
                    >
                        <Plus className="w-4 h-4" /> Tambah Penyedia
                    </button>
                </div>

                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 text-slate-500 uppercase text-xs">
                            <tr>
                                <th className="text-left py-3 px-4">Nama</th>
                                <th className="text-left py-3 px-4">Alamat</th>
                                <th className="text-left py-3 px-4">Telepon</th>
                                <th className="text-left py-3 px-4">Email</th>
                                <th className="text-right py-3 px-4 w-40">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {items.data.length === 0 ? (
                                <tr><td colSpan={5} className="py-10 text-center text-slate-400 text-xs">Belum ada penyedia.</td></tr>
                            ) : (
                                items.data.map((item) => (
                                    <tr key={item.id}>
                                        <td className="py-3 px-4 font-medium text-slate-800">{item.nama}</td>
                                        <td className="py-3 px-4 text-slate-600">{item.alamat ?? '-'}</td>
                                        <td className="py-3 px-4">{item.telepon ?? '-'}</td>
                                        <td className="py-3 px-4">{item.email ?? '-'}</td>
                                        <td className="py-3 px-4">
                                            <div className="flex items-center justify-end gap-2">
                                                <button
                                                    type="button"
                                                    onClick={() => openEdit(item)}
                                                    className="inline-flex items-center gap-1 px-2.5 py-1.5 border border-slate-300 rounded-lg text-xs font-bold text-slate-600 hover:bg-slate-50"
                                                >
                                                    <Pencil className="w-3.5 h-3.5" /> Edit
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => setDeleteTarget(item)}
                                                    className="inline-flex items-center gap-1 px-2.5 py-1.5 border border-rose-200 rounded-lg text-xs font-bold text-rose-600 hover:bg-rose-50"
                                                >
                                                    <Trash2 className="w-3.5 h-3.5" /> Hapus
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <Modal isOpen={showCreate} onClose={() => setShowCreate(false)} title="Tambah Penyedia" maxWidth="2xl">
                <form onSubmit={submitCreate} className="space-y-4">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div className="sm:col-span-2">
                            <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Nama</label>
                            <input value={createForm.data.nama} onChange={(e) => createForm.setData('nama', e.target.value)} placeholder="cth. CV Sumber Rejeki" className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm" />
                            {createForm.errors.nama && <p className="text-xs text-rose-600 mt-1">{createForm.errors.nama}</p>}
                        </div>
                        <div className="sm:col-span-2">
                            <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Alamat</label>
                            <input value={createForm.data.alamat} onChange={(e) => createForm.setData('alamat', e.target.value)} placeholder="cth. Jl. Merdeka No. 10, Bandung" className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm" />
                            {createForm.errors.alamat && <p className="text-xs text-rose-600 mt-1">{createForm.errors.alamat}</p>}
                        </div>
                        <div>
                            <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Telepon</label>
                            <input value={createForm.data.telepon} onChange={(e) => createForm.setData('telepon', e.target.value)} placeholder="cth. 0812-3456-7890" className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm" />
                            {createForm.errors.telepon && <p className="text-xs text-rose-600 mt-1">{createForm.errors.telepon}</p>}
                        </div>
                        <div>
                            <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Email</label>
                            <input value={createForm.data.email} onChange={(e) => createForm.setData('email', e.target.value)} placeholder="cth. supplier@contoh.co.id" className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm" />
                            {createForm.errors.email && <p className="text-xs text-rose-600 mt-1">{createForm.errors.email}</p>}
                        </div>
                    </div>
                    <div className="flex justify-end gap-2">
                        <button type="button" onClick={() => setShowCreate(false)} className="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl">
                            Batal
                        </button>
                        <button type="submit" disabled={createForm.processing} className="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-xl disabled:opacity-50">
                            <Plus className="w-4 h-4" /> Tambah
                        </button>
                    </div>
                </form>
            </Modal>

            <Modal isOpen={editTarget !== null} onClose={() => setEditTarget(null)} title="Edit Penyedia" maxWidth="2xl">
                <form onSubmit={submitEdit} className="space-y-4">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div className="sm:col-span-2">
                            <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Nama</label>
                            <input value={editForm.data.nama} onChange={(e) => editForm.setData('nama', e.target.value)} placeholder="cth. CV Sumber Rejeki" className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm" />
                            {editForm.errors.nama && <p className="text-xs text-rose-600 mt-1">{editForm.errors.nama}</p>}
                        </div>
                        <div className="sm:col-span-2">
                            <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Alamat</label>
                            <input value={editForm.data.alamat} onChange={(e) => editForm.setData('alamat', e.target.value)} placeholder="cth. Jl. Merdeka No. 10, Bandung" className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm" />
                            {editForm.errors.alamat && <p className="text-xs text-rose-600 mt-1">{editForm.errors.alamat}</p>}
                        </div>
                        <div>
                            <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Telepon</label>
                            <input value={editForm.data.telepon} onChange={(e) => editForm.setData('telepon', e.target.value)} placeholder="cth. 0812-3456-7890" className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm" />
                            {editForm.errors.telepon && <p className="text-xs text-rose-600 mt-1">{editForm.errors.telepon}</p>}
                        </div>
                        <div>
                            <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Email</label>
                            <input value={editForm.data.email} onChange={(e) => editForm.setData('email', e.target.value)} placeholder="cth. supplier@contoh.co.id" className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm" />
                            {editForm.errors.email && <p className="text-xs text-rose-600 mt-1">{editForm.errors.email}</p>}
                        </div>
                    </div>
                    <div className="flex justify-end gap-2">
                        <button type="button" onClick={() => setEditTarget(null)} className="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl">
                            Batal
                        </button>
                        <button type="submit" disabled={editForm.processing} className="px-4 py-2 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-xl disabled:opacity-50">
                            Simpan
                        </button>
                    </div>
                </form>
            </Modal>

            <ConfirmDialog
                isOpen={deleteTarget !== null}
                onClose={() => setDeleteTarget(null)}
                onConfirm={confirmDelete}
                title="Hapus Penyedia"
                message={`Hapus penyedia "${deleteTarget?.nama ?? ''}"? Tindakan ini tidak dapat dibatalkan.`}
                confirmText="Ya, Hapus"
                isDestructive
            />
        </AppLayout>
    );
}
