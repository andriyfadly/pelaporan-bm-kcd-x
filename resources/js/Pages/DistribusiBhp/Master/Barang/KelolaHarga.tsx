import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import ConfirmDialog from '@/Components/ConfirmDialog';
import EmptyState from '@/Components/EmptyState';
import Modal from '@/Components/Modal';
import { formatRupiah } from '@/Utils/format';
import { DollarSign, ArrowLeft, Plus, Pencil, Trash2, Save, Package } from 'lucide-react';

interface BarangInfo {
    id: string;
    kode_barang: string | null;
    nama_barang: string;
    spesifikasi_barang_nusp: string | null;
    satuan: string | null;
}

interface HargaItem {
    id: string;
    harga_satuan: number;
    created_at: string;
}

export default function KelolaHarga({
    barang,
    sumberDana,
    hargaList,
}: {
    barang: BarangInfo;
    sumberDana: string;
    hargaList: HargaItem[];
}) {
    const prefix = `/distribusi-bhp/${sumberDana.toLowerCase()}`;
    const [showCreate, setShowCreate] = useState(false);
    const [editTarget, setEditTarget] = useState<HargaItem | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<HargaItem | null>(null);

    const createForm = useForm({ sumber_dana: sumberDana, harga_satuan: 0 });
    const editForm = useForm({ harga_satuan: 0 });

    const submitCreate = (e: React.FormEvent) => {
        e.preventDefault();
        createForm.post(`${prefix}/master/barang/${barang.id}/harga`, {
            preserveScroll: true,
            onSuccess: () => {
                createForm.reset('harga_satuan');
                setShowCreate(false);
            },
        });
    };

    const openEdit = (item: HargaItem) => {
        setEditTarget(item);
        editForm.setData('harga_satuan', item.harga_satuan);
    };

    const submitEdit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editTarget) return;
        editForm.put(`${prefix}/master/harga-barang/${editTarget.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setEditTarget(null);
                editForm.reset();
            },
        });
    };

    const confirmDelete = () => {
        if (!deleteTarget) return;
        router.delete(`${prefix}/master/harga-barang/${deleteTarget.id}`, { preserveScroll: true });
    };

    const formatDate = (dateString: string) =>
        new Intl.DateTimeFormat('id-ID', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(dateString));

    return (
        <AppLayout title="Kelola Harga Barang">
            <Head title="Kelola Harga Barang" />

            <div className="space-y-6">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-lg font-bold text-slate-900 flex items-center gap-2">
                            <DollarSign className="w-5 h-5 text-emerald-600" /> Kelola Harga Barang
                        </h1>
                        <p className="text-sm text-slate-500 mt-1">Kelola semua harga untuk {barang.nama_barang}.</p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <a
                            href={`${prefix}/master/barang`}
                            className="inline-flex min-h-10 flex-1 items-center justify-center gap-1.5 px-4 border border-slate-300 text-slate-700 rounded-xl text-sm font-bold hover:bg-slate-50 sm:flex-none"
                        >
                            <ArrowLeft className="w-4 h-4" /> Kembali
                        </a>
                        <button
                            type="button"
                            onClick={() => setShowCreate(true)}
                            className="inline-flex min-h-10 flex-1 items-center justify-center gap-1.5 px-4 bg-blue-600 text-white rounded-xl text-sm font-bold hover:bg-blue-700 transition sm:flex-none"
                        >
                            <Plus className="w-4 h-4" /> Tambah Harga
                        </button>
                    </div>
                </div>

                <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                    <h2 className="text-sm font-bold text-slate-800 flex items-center gap-2 mb-4">
                        <Package className="w-4 h-4 text-slate-500" /> Informasi Barang
                    </h2>
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <p className="text-xs font-bold uppercase text-slate-500">Kode Barang</p>
                            <p className="text-sm font-semibold text-slate-800 mt-1">{barang.kode_barang ?? '-'}</p>
                        </div>
                        <div>
                            <p className="text-xs font-bold uppercase text-slate-500">Nama Barang</p>
                            <p className="text-sm font-semibold text-slate-800 mt-1">{barang.nama_barang}</p>
                        </div>
                        <div>
                            <p className="text-xs font-bold uppercase text-slate-500">Spesifikasi / NUSP</p>
                            <p className="text-sm font-semibold text-slate-800 mt-1">{barang.spesifikasi_barang_nusp ?? '-'}</p>
                        </div>
                    </div>
                </div>

                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div className="px-4 py-3 border-b border-slate-100">
                        <h2 className="text-sm font-bold text-slate-800">Daftar Harga ({hargaList.length})</h2>
                    </div>
                    {hargaList.length === 0 ? (
                        <EmptyState
                            title="Belum Ada Harga"
                            description="Tambahkan harga satuan agar barang ini bisa dipakai pada pengajuan dan penerimaan."
                            action={
                                <button
                                    type="button"
                                    onClick={() => setShowCreate(true)}
                                    className="inline-flex min-h-10 items-center gap-1.5 px-4 text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-xl"
                                >
                                    <Plus className="w-4 h-4" /> Tambah Harga Pertama
                                </button>
                            }
                        />
                    ) : (
                        <div className="divide-y divide-slate-100">
                            {hargaList.map((harga) => (
                                <div
                                    key={harga.id}
                                    className="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:gap-4"
                                >
                                    <div className="min-w-0">
                                        <div className="text-lg font-bold text-emerald-700">{formatRupiah(harga.harga_satuan)}</div>
                                        <div className="text-xs text-slate-500">Dibuat: {formatDate(harga.created_at)}</div>
                                    </div>
                                    <div className="grid grid-cols-2 gap-2 sm:flex sm:shrink-0 sm:items-center">
                                        <button
                                            type="button"
                                            onClick={() => openEdit(harga)}
                                            aria-label={`Edit harga ${formatRupiah(harga.harga_satuan)}`}
                                            className="inline-flex min-h-10 items-center justify-center gap-1 px-3 rounded-xl text-sm font-bold text-white bg-amber-500 hover:bg-amber-600"
                                        >
                                            <Pencil className="w-4 h-4" /> Edit
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => setDeleteTarget(harga)}
                                            aria-label={`Hapus harga ${formatRupiah(harga.harga_satuan)}`}
                                            className="inline-flex min-h-10 items-center justify-center gap-1 px-3 rounded-xl text-sm font-bold text-white bg-rose-600 hover:bg-rose-700"
                                        >
                                            <Trash2 className="w-4 h-4" /> Hapus
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>

            <Modal isOpen={showCreate} onClose={() => setShowCreate(false)} title="Tambah Harga Baru">
                <form onSubmit={submitCreate} className="space-y-4">
                    <div>
                        <label htmlFor="create-harga-satuan" className="block text-xs font-bold uppercase text-slate-500 mb-1">Harga Satuan</label>
                        <input
                            id="create-harga-satuan"
                            type="number"
                            value={createForm.data.harga_satuan}
                            onChange={(e) => createForm.setData('harga_satuan', Number(e.target.value))}
                            placeholder="Masukkan harga satuan"
                            className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm"
                        />
                        {createForm.errors.harga_satuan && <p className="text-xs text-rose-600 mt-1">{createForm.errors.harga_satuan}</p>}
                    </div>
                    <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" onClick={() => setShowCreate(false)} className="min-h-10 px-4 text-sm font-bold text-slate-600 hover:bg-slate-100 rounded-xl">
                            Batal
                        </button>
                        <button type="submit" disabled={createForm.processing} className="inline-flex min-h-10 items-center justify-center gap-1.5 px-4 text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-xl disabled:opacity-50">
                            <Save className="w-4 h-4" /> Tambah Harga
                        </button>
                    </div>
                </form>
            </Modal>

            <Modal isOpen={editTarget !== null} onClose={() => setEditTarget(null)} title="Edit Harga">
                <form onSubmit={submitEdit} className="space-y-4">
                    <div>
                        <label htmlFor="edit-harga-satuan" className="block text-xs font-bold uppercase text-slate-500 mb-1">Harga Satuan</label>
                        <input
                            id="edit-harga-satuan"
                            type="number"
                            value={editForm.data.harga_satuan}
                            onChange={(e) => editForm.setData('harga_satuan', Number(e.target.value))}
                            placeholder="Masukkan harga satuan"
                            className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm"
                        />
                        {editForm.errors.harga_satuan && <p className="text-xs text-rose-600 mt-1">{editForm.errors.harga_satuan}</p>}
                    </div>
                    <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" onClick={() => setEditTarget(null)} className="min-h-10 px-4 text-sm font-bold text-slate-600 hover:bg-slate-100 rounded-xl">
                            Batal
                        </button>
                        <button type="submit" disabled={editForm.processing} className="inline-flex min-h-10 items-center justify-center gap-1.5 px-4 text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-xl disabled:opacity-50">
                            <Save className="w-4 h-4" /> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </Modal>

            <ConfirmDialog
                isOpen={deleteTarget !== null}
                onClose={() => setDeleteTarget(null)}
                onConfirm={confirmDelete}
                title="Hapus Harga"
                message={`Hapus harga ${deleteTarget ? formatRupiah(deleteTarget.harga_satuan) : ''}? Tindakan ini tidak dapat dibatalkan.`}
                confirmText="Ya, Hapus"
                isDestructive
            />
        </AppLayout>
    );
}
