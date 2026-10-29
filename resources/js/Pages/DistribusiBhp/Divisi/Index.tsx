import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Modal from '@/Components/Modal';
import ConfirmDialog from '@/Components/ConfirmDialog';
import { Building2, Eye, EyeOff, KeyRound, Pencil, Power, Trash2, UserPlus } from 'lucide-react';

interface OperatorDivisi {
    id: string;
    name: string;
    username: string;
    is_active: boolean;
}

interface DivisiItem {
    id: string;
    nama: string;
    users: OperatorDivisi[];
    pengajuan_count: number;
}

export default function Index({ divisis, npsn }: { divisis: DivisiItem[]; npsn: string | null }) {
    const [resetTarget, setResetTarget] = useState<OperatorDivisi | null>(null);
    const [showPassword, setShowPassword] = useState(false);
    const [showCreate, setShowCreate] = useState(false);

    const {
        data,
        setData,
        post,
        processing,
        errors,
        reset,
    } = useForm({
        nama_divisi: '',
        nama_operator: '',
        username_suffix: '',
        password: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/distribusi-bhp/divisi', {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setShowCreate(false);
                setShowPassword(false);
            },
        });
    };

    const resetPasswordForm = useForm({ password: '' });

    const submitReset = (e: React.FormEvent) => {
        e.preventDefault();
        if (!resetTarget) return;
        resetPasswordForm.put(`/distribusi-bhp/divisi/operator/${resetTarget.id}/password`, {
            preserveScroll: true,
            onSuccess: () => {
                setResetTarget(null);
                resetPasswordForm.reset();
            },
        });
    };

    const toggleActiveForm = useForm({});

    const toggleActive = (operator: OperatorDivisi) => {
        toggleActiveForm.put(`/distribusi-bhp/divisi/operator/${operator.id}/toggle`, { preserveScroll: true });
    };

    const [editTarget, setEditTarget] = useState<DivisiItem | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<DivisiItem | null>(null);

    const editForm = useForm({ nama_divisi: '' });

    const openEdit = (divisi: DivisiItem) => {
        setEditTarget(divisi);
        editForm.setData('nama_divisi', divisi.nama);
    };

    const submitEdit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editTarget) return;
        editForm.put(`/distribusi-bhp/divisi/${editTarget.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setEditTarget(null);
                editForm.reset();
            },
        });
    };

    const confirmDelete = () => {
        if (!deleteTarget) return;
        router.delete(`/distribusi-bhp/divisi/${deleteTarget.id}`, { preserveScroll: true });
    };

    return (
        <AppLayout title="Divisi BHP">
            <Head title="Divisi BHP" />

            <div className="space-y-6">
                <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-lg font-bold text-slate-900 flex items-center gap-2">
                            <Building2 className="w-5 h-5 text-blue-600" /> Divisi Sekolah
                        </h1>
                        <p className="text-sm text-slate-500 mt-1">
                            Kelola divisi beserta akun operatornya. Username login otomatis berformat{' '}
                            <code className="font-mono text-xs">{npsn ?? 'NPSN-belum-ada'}-{'{username divisi}'}</code>.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={() => setShowCreate(true)}
                        className="inline-flex shrink-0 items-center gap-1.5 px-4 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-bold hover:bg-blue-700 transition"
                    >
                        <UserPlus className="w-4 h-4" /> Tambah Divisi
                    </button>
                </div>

                <div className="space-y-4">
                    {divisis.length === 0 && (
                        <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm text-sm text-slate-500">
                            Belum ada divisi. Klik "Tambah Divisi" untuk membuat divisi pertama.
                        </div>
                    )}
                    {divisis.map((divisi) => (
                        <div key={divisi.id} className="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm sm:p-6">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <div className="flex items-baseline gap-2">
                                    <h3 className="text-base font-bold text-slate-900">{divisi.nama}</h3>
                                    <span className="text-xs text-slate-500">{divisi.pengajuan_count} pengajuan</span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <button
                                        type="button"
                                        onClick={() => openEdit(divisi)}
                                        className="inline-flex min-h-9 items-center gap-1 px-3 border border-slate-300 rounded-lg text-xs font-bold text-slate-600 hover:bg-slate-50"
                                    >
                                        <Pencil className="w-3.5 h-3.5" /> Edit
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setDeleteTarget(divisi)}
                                        className="inline-flex min-h-9 items-center gap-1 px-3 border border-rose-200 rounded-lg text-xs font-bold text-rose-600 hover:bg-rose-50"
                                    >
                                        <Trash2 className="w-3.5 h-3.5" /> Hapus
                                    </button>
                                </div>
                            </div>
                            <div className="mt-4 space-y-2">
                                {divisi.users.length === 0 && (
                                    <p className="text-xs text-slate-400">Belum ada akun operator pada divisi ini.</p>
                                )}
                                {divisi.users.map((operator) => (
                                    <div
                                        key={operator.id}
                                        className="rounded-xl bg-slate-50 px-4 py-3 sm:flex sm:items-center sm:justify-between sm:gap-3"
                                    >
                                        <div className="flex items-center justify-between gap-2 sm:block">
                                            <div>
                                                <div className="text-sm font-semibold text-slate-800">{operator.name}</div>
                                                <div className="text-xs font-mono text-slate-500">{operator.username}</div>
                                            </div>
                                            <span className={`shrink-0 px-2 py-1 rounded-full text-xs font-bold sm:hidden ${operator.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-200 text-slate-500'}`}>
                                                {operator.is_active ? 'Aktif' : 'Nonaktif'}
                                            </span>
                                        </div>
                                        <div className="mt-2 flex items-center gap-2 sm:mt-0 sm:shrink-0">
                                            <span className={`hidden px-2 py-1 rounded-full text-xs font-bold sm:inline ${operator.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-200 text-slate-500'}`}>
                                                {operator.is_active ? 'Aktif' : 'Nonaktif'}
                                            </span>
                                            <button
                                                onClick={() => setResetTarget(operator)}
                                                aria-label={`Reset password ${operator.username}`}
                                                className="inline-flex min-h-9 flex-1 items-center justify-center gap-1 px-2.5 border border-slate-300 rounded-lg text-xs font-bold text-slate-600 hover:bg-white sm:flex-none"
                                            >
                                                <KeyRound className="w-3.5 h-3.5" /> Reset
                                            </button>
                                            <button
                                                onClick={() => toggleActive(operator)}
                                                aria-label={`${operator.is_active ? 'Nonaktifkan' : 'Aktifkan'} ${operator.username}`}
                                                className="inline-flex min-h-9 flex-1 items-center justify-center gap-1 px-2.5 border border-slate-300 rounded-lg text-xs font-bold text-slate-600 hover:bg-white sm:flex-none"
                                            >
                                                <Power className="w-3.5 h-3.5" /> {operator.is_active ? 'Nonaktifkan' : 'Aktifkan'}
                                            </button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            <Modal isOpen={resetTarget !== null} onClose={() => setResetTarget(null)} title="Reset Password Operator" maxWidth="sm">
                <form onSubmit={submitReset} className="space-y-4">
                    <p className="text-sm text-slate-600">
                        Buat password baru untuk akun <span className="font-mono font-bold">{resetTarget?.username ?? ''}</span>. Operator akan
                        diminta mengganti password saat login berikutnya.
                    </p>
                    <div>
                        <label htmlFor="reset-password" className="block text-xs font-bold uppercase text-slate-500 mb-1">Password Baru</label>
                        <input
                            id="reset-password"
                            type="password"
                            autoComplete="new-password"
                            value={resetPasswordForm.data.password}
                            onChange={(e) => resetPasswordForm.setData('password', e.target.value)}
                            className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm"
                        />
                        {resetPasswordForm.errors.password && <p className="text-xs text-rose-600 mt-1">{resetPasswordForm.errors.password}</p>}
                    </div>
                    <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" onClick={() => setResetTarget(null)} className="min-h-10 px-4 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl">
                            Batal
                        </button>
                        <button type="submit" disabled={resetPasswordForm.processing} className="min-h-10 px-4 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-xl disabled:opacity-50">
                            Reset
                        </button>
                    </div>
                </form>
            </Modal>

            <Modal isOpen={showCreate} onClose={() => setShowCreate(false)} title="Tambah Divisi + Operator" maxWidth="2xl">
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label htmlFor="nama-divisi" className="block text-xs font-bold uppercase text-slate-500 mb-1">Nama Divisi</label>
                            <input
                                id="nama-divisi"
                                value={data.nama_divisi}
                                onChange={(e) => setData('nama_divisi', e.target.value)}
                                placeholder="Sarpras"
                                className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm"
                            />
                            {errors.nama_divisi && <p className="text-xs text-rose-600 mt-1">{errors.nama_divisi}</p>}
                        </div>
                        <div>
                            <label htmlFor="nama-operator" className="block text-xs font-bold uppercase text-slate-500 mb-1">Nama Operator</label>
                            <input
                                id="nama-operator"
                                value={data.nama_operator}
                                onChange={(e) => setData('nama_operator', e.target.value)}
                                className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm"
                            />
                            {errors.nama_operator && <p className="text-xs text-rose-600 mt-1">{errors.nama_operator}</p>}
                        </div>
                        <div>
                            <label htmlFor="username-suffix" className="block text-xs font-bold uppercase text-slate-500 mb-1">Username</label>
                            <div className="flex items-center">
                                <span className="px-3 py-2 bg-slate-100 border border-r-0 border-slate-300 rounded-l-xl text-sm font-mono text-slate-500 whitespace-nowrap">
                                    {npsn ?? '???'}-
                                </span>
                                <input
                                    id="username-suffix"
                                    value={data.username_suffix}
                                    onChange={(e) => setData('username_suffix', e.target.value.toLowerCase())}
                                    placeholder="sarpras"
                                    className="w-full px-3 py-2 border border-slate-300 rounded-r-xl text-sm font-mono"
                                />
                            </div>
                            {errors.username_suffix && <p className="text-xs text-rose-600 mt-1">{errors.username_suffix}</p>}
                        </div>
                        <div>
                            <label htmlFor="password-operator" className="block text-xs font-bold uppercase text-slate-500 mb-1">Password Awal</label>
                            <div className="relative">
                                <input
                                    id="password-operator"
                                    type={showPassword ? 'text' : 'password'}
                                    autoComplete="new-password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    className="w-full px-3 py-2 pr-11 border border-slate-300 rounded-xl text-sm"
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowPassword((v) => !v)}
                                    className="absolute right-0 top-0 inline-flex h-10 w-10 items-center justify-center text-slate-400 hover:text-slate-600"
                                    aria-label={showPassword ? 'Sembunyikan password' : 'Tampilkan password'}
                                >
                                    {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                                </button>
                            </div>
                            {errors.password && <p className="text-xs text-rose-600 mt-1">{errors.password}</p>}
                        </div>
                    </div>
                    <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" onClick={() => setShowCreate(false)} className="min-h-10 px-4 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl">
                            Batal
                        </button>
                        <button type="submit" disabled={processing} className="inline-flex min-h-10 items-center justify-center gap-1.5 px-4 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-xl disabled:opacity-50">
                            <UserPlus className="w-4 h-4" /> Buat Divisi & Operator
                        </button>
                    </div>
                </form>
            </Modal>

            <Modal isOpen={editTarget !== null} onClose={() => setEditTarget(null)} title="Edit Divisi" maxWidth="sm">
                <form onSubmit={submitEdit} className="space-y-4">
                    <div>
                        <label htmlFor="edit-nama-divisi" className="block text-xs font-bold uppercase text-slate-500 mb-1">Nama Divisi</label>
                        <input
                            id="edit-nama-divisi"
                            value={editForm.data.nama_divisi}
                            onChange={(e) => editForm.setData('nama_divisi', e.target.value)}
                            className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm"
                        />
                        {editForm.errors.nama_divisi && <p className="text-xs text-rose-600 mt-1">{editForm.errors.nama_divisi}</p>}
                    </div>
                    <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" onClick={() => setEditTarget(null)} className="min-h-10 px-4 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl">
                            Batal
                        </button>
                        <button type="submit" disabled={editForm.processing} className="min-h-10 px-4 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-xl disabled:opacity-50">
                            Simpan
                        </button>
                    </div>
                </form>
            </Modal>

            <ConfirmDialog
                isOpen={deleteTarget !== null}
                onClose={() => setDeleteTarget(null)}
                onConfirm={confirmDelete}
                title="Hapus Divisi"
                message={`Hapus divisi "${deleteTarget?.nama ?? ''}" beserta seluruh akun operatornya? Tindakan ini tidak dapat dibatalkan.`}
                confirmText="Ya, Hapus"
                isDestructive
            />
        </AppLayout>
    );
}
