import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Modal from '@/Components/Modal';
import { Building2, Eye, EyeOff, KeyRound, Power, UserPlus } from 'lucide-react';

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
            onSuccess: () => reset(),
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

    return (
        <AppLayout title="Divisi BHP">
            <Head title="Divisi BHP" />

            <div className="space-y-6">
                <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                    <h1 className="text-lg font-bold text-slate-900 flex items-center gap-2">
                        <Building2 className="w-5 h-5 text-blue-600" /> Divisi Sekolah
                    </h1>
                    <p className="text-sm text-slate-500 mt-1">
                        Kelola divisi beserta akun operatornya. Username login otomatis berformat{' '}
                        <code className="font-mono text-xs">{npsn ?? 'NPSN-belum-ada'}-{'{suffix}'}</code>.
                    </p>
                </div>

                <form onSubmit={submit} className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-4 max-w-4xl">
                    <h2 className="text-sm font-bold text-slate-700 uppercase tracking-wider">Tambah Divisi + Operator</h2>
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Nama Divisi</label>
                            <input
                                value={data.nama_divisi}
                                onChange={(e) => setData('nama_divisi', e.target.value)}
                                placeholder="Sarpras"
                                className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm"
                            />
                            {errors.nama_divisi && <p className="text-xs text-rose-600 mt-1">{errors.nama_divisi}</p>}
                        </div>
                        <div>
                            <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Nama Operator</label>
                            <input
                                value={data.nama_operator}
                                onChange={(e) => setData('nama_operator', e.target.value)}
                                className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm"
                            />
                            {errors.nama_operator && <p className="text-xs text-rose-600 mt-1">{errors.nama_operator}</p>}
                        </div>
                        <div>
                            <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Username</label>
                            <div className="flex items-center">
                                <span className="px-3 py-2 bg-slate-100 border border-r-0 border-slate-300 rounded-l-xl text-sm font-mono text-slate-500 whitespace-nowrap">
                                    {npsn ?? '???'}-
                                </span>
                                <input
                                    value={data.username_suffix}
                                    onChange={(e) => setData('username_suffix', e.target.value.toLowerCase())}
                                    placeholder="sarpras"
                                    className="w-full px-3 py-2 border border-slate-300 rounded-r-xl text-sm font-mono"
                                />
                            </div>
                            {errors.username_suffix && <p className="text-xs text-rose-600 mt-1">{errors.username_suffix}</p>}
                        </div>
                        <div>
                            <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Password Awal</label>
                            <div className="relative">
                                <input
                                    type={showPassword ? 'text' : 'password'}
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    className="w-full px-3 py-2 pr-9 border border-slate-300 rounded-xl text-sm"
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowPassword((v) => !v)}
                                    className="absolute right-2 top-2.5 text-slate-400 hover:text-slate-600"
                                    aria-label={showPassword ? 'Sembunyikan password' : 'Tampilkan password'}
                                >
                                    {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                                </button>
                            </div>
                            {errors.password && <p className="text-xs text-rose-600 mt-1">{errors.password}</p>}
                        </div>
                    </div>
                    <button type="submit" disabled={processing} className="inline-flex items-center gap-1.5 px-4 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-bold disabled:opacity-50">
                        <UserPlus className="w-4 h-4" /> Buat Divisi & Operator
                    </button>
                </form>

                <div className="space-y-4">
                    {divisis.length === 0 && (
                        <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm text-sm text-slate-500">
                            Belum ada divisi. Buat divisi pertama melalui form di atas.
                        </div>
                    )}
                    {divisis.map((divisi) => (
                        <div key={divisi.id} className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                            <div className="flex items-center justify-between">
                                <h3 className="text-base font-bold text-slate-900">{divisi.nama}</h3>
                                <span className="text-xs text-slate-500">{divisi.pengajuan_count} pengajuan</span>
                            </div>
                            <div className="mt-4 space-y-2">
                                {divisi.users.length === 0 && (
                                    <p className="text-xs text-slate-400">Belum ada akun operator pada divisi ini.</p>
                                )}
                                {divisi.users.map((operator) => (
                                    <div key={operator.id} className="flex items-center justify-between bg-slate-50 rounded-xl px-4 py-2.5">
                                        <div>
                                            <div className="text-sm font-semibold text-slate-800">{operator.name}</div>
                                            <div className="text-xs font-mono text-slate-500">{operator.username}</div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <span className={`px-2 py-1 rounded-full text-[11px] font-bold ${operator.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-200 text-slate-500'}`}>
                                                {operator.is_active ? 'Aktif' : 'Nonaktif'}
                                            </span>
                                            <button
                                                onClick={() => setResetTarget(operator)}
                                                className="inline-flex items-center gap-1 px-2.5 py-1.5 border border-slate-300 rounded-lg text-xs font-bold text-slate-600 hover:bg-white"
                                            >
                                                <KeyRound className="w-3.5 h-3.5" /> Reset Password
                                            </button>
                                            <button
                                                onClick={() => toggleActive(operator)}
                                                className="inline-flex items-center gap-1 px-2.5 py-1.5 border border-slate-300 rounded-lg text-xs font-bold text-slate-600 hover:bg-white"
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
                        <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Password Baru</label>
                        <input
                            type="password"
                            value={resetPasswordForm.data.password}
                            onChange={(e) => resetPasswordForm.setData('password', e.target.value)}
                            className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm"
                        />
                        {resetPasswordForm.errors.password && <p className="text-xs text-rose-600 mt-1">{resetPasswordForm.errors.password}</p>}
                    </div>
                    <div className="flex justify-end gap-2">
                        <button type="button" onClick={() => setResetTarget(null)} className="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl">
                            Batal
                        </button>
                        <button type="submit" disabled={resetPasswordForm.processing} className="px-4 py-2 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-xl disabled:opacity-50">
                            Reset
                        </button>
                    </div>
                </form>
            </Modal>
        </AppLayout>
    );
}
