import { Head, useForm, router } from '@inertiajs/react';
import React, { useState, useMemo } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import ConfirmDialog from '@/Components/ConfirmDialog';
import Modal from '@/Components/Modal';
import SearchInput from '@/Components/SearchInput';
import EmptyState from '@/Components/EmptyState';
import {
    Users,
    UserPlus,
    Pencil,
    Trash2,
    Shield,
    Building2,
    AlertTriangle,
    Eye,
    EyeOff,
    UserCircle2,
    Power,
} from 'lucide-react';

interface Sekolah {
    id: string;
    nama_sekolah: string;
    kota_kab: string;
    npsn?: string;
}

interface UserItem {
    id: string;
    name: string;
    username: string;
    is_active: boolean;
    sekolah_id: string | null;
    sekolah?: Sekolah | null;
    roles: { name: string }[];
    created_at?: string;
}

interface Props {
    users: UserItem[];
    sekolahs: Sekolah[];
    auth: {
        user: {
            id: string;
            name: string;
            username: string;
        };
    };
}

export default function Index({ users, sekolahs, auth }: Props) {
    const [searchQuery, setSearchQuery] = useState('');
    const [modalEditOpen, setModalEditOpen] = useState(false);
    const [modalCreateOpen, setModalCreateOpen] = useState(false);
    const [modalDeleteUser, setModalDeleteUser] = useState<UserItem | null>(null);
    const [toggleTarget, setToggleTarget] = useState<UserItem | null>(null);
    const [showPassword, setShowPassword] = useState(false);

    // Form Edit User & Ganti Password
    const {
        data: editData,
        setData: setEditData,
        put,
        processing: editProcessing,
        errors: editErrors,
        reset: resetEdit,
    } = useForm({
        id: '',
        name: '',
        username: '',
        password: '',
        role: 'operator_sekolah',
        sekolah_id: '',
    });

    // Form Tambah User Baru
    const {
        data: createData,
        setData: setCreateData,
        post,
        processing: createProcessing,
        errors: createErrors,
        reset: resetCreate,
    } = useForm({
        name: '',
        username: '',
        password: '',
        sekolah_id: '',
        role: 'operator_sekolah',
    });

    const applyNpsnPrefix = (username: string, npsn?: string, defaultSuffix = 'admin') => {
        if (!npsn) return username;
        const parts = username.split('-');
        const suffix = parts.length > 1 ? parts.slice(1).join('-') : (username || defaultSuffix);
        return `${npsn}-${suffix}`;
    };

    const handleCreateSekolahChange = (sekolahId: string) => {
        const selected = sekolahs.find((s) => s.id === sekolahId);
        const defaultSuffix = createData.role === 'bendahara_sekolah' ? 'bendahara' : 'admin';
        setCreateData({
            ...createData,
            sekolah_id: sekolahId,
            username: applyNpsnPrefix(createData.username, selected?.npsn, defaultSuffix),
        });
    };

    const handleCreateRoleChange = (newRole: string) => {
        const selected = sekolahs.find((s) => s.id === createData.sekolah_id);
        let username = createData.username;
        if (newRole !== 'admin_kcd' && selected?.npsn) {
            const defaultSuffix = newRole === 'bendahara_sekolah' ? 'bendahara' : 'admin';
            username = applyNpsnPrefix(username, selected.npsn, defaultSuffix);
        }
        setCreateData({
            ...createData,
            role: newRole,
            sekolah_id: newRole === 'admin_kcd' ? '' : createData.sekolah_id,
            username,
        });
    };

    const handleEditSekolahChange = (sekolahId: string) => {
        const selected = sekolahs.find((s) => s.id === sekolahId);
        const defaultSuffix = editData.role === 'bendahara_sekolah' ? 'bendahara' : 'admin';
        setEditData({
            ...editData,
            sekolah_id: sekolahId,
            username: applyNpsnPrefix(editData.username, selected?.npsn, defaultSuffix),
        });
    };

    const handleEditRoleChange = (newRole: string) => {
        const selected = sekolahs.find((s) => s.id === editData.sekolah_id);
        let username = editData.username;
        if (newRole !== 'admin_kcd' && selected?.npsn) {
            const defaultSuffix = newRole === 'bendahara_sekolah' ? 'bendahara' : 'admin';
            username = applyNpsnPrefix(username, selected.npsn, defaultSuffix);
        }
        setEditData({
            ...editData,
            role: newRole,
            sekolah_id: newRole === 'admin_kcd' ? '' : editData.sekolah_id,
            username,
        });
    };

    const filteredUsers = useMemo(() => {
        const q = searchQuery.toLowerCase().trim();
        if (!q) return users;
        return users.filter((u) => {
            const username = u.username.toLowerCase();
            const namaSekolah = (u.sekolah?.nama_sekolah || 'induk kcd').toLowerCase();
            const idSekolah = (u.sekolah_id || '').toLowerCase();
            return username.includes(q) || namaSekolah.includes(q) || idSekolah.includes(q);
        });
    }, [users, searchQuery]);

    const handleOpenEdit = (user: UserItem) => {
        const role = user.roles[0]?.name === 'admin' ? 'admin_kcd' : (user.roles[0]?.name || 'operator_sekolah');
        setEditData({
            id: user.id,
            name: user.name,
            username: user.username,
            password: '',
            role: role,
            sekolah_id: user.sekolah_id || '',
        });
        setShowPassword(false);
        setModalEditOpen(true);
    };

    const handleEditSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/admin/user/${editData.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setModalEditOpen(false);
                resetEdit();
            },
        });
    };

    const handleCreateSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/user', {
            preserveScroll: true,
            onSuccess: () => {
                setModalCreateOpen(false);
                resetCreate();
            },
        });
    };

    const handleDeleteConfirm = () => {
        if (!modalDeleteUser) return;

        router.delete(`/admin/user/${modalDeleteUser.id}`, {
            preserveScroll: true,
            onSuccess: () => setModalDeleteUser(null),
        });
    };

    const handleToggleConfirm = () => {
        if (!toggleTarget) return;
        router.put(
            `/admin/user/${toggleTarget.id}`,
            {
                username: toggleTarget.username,
                is_active: toggleTarget.is_active ? 0 : 1,
            },
            { preserveScroll: true },
        );
    };

    return (
        <AppLayout title="Kelola Data User">
            <Head title="Kelola Data User | SINVENTARIS" />

            <div className="max-w-7xl mx-auto space-y-6">
                {/* Header Card Persis Legacy kelola_user.php */}
                <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div>
                        <div className="flex items-center gap-2.5 mb-1">
                            <Users className="w-6 h-6 text-blue-600" />
                            <h1 className="text-xl font-bold text-slate-900">
                                Kelola Data User
                            </h1>
                            <span className="px-3 py-1 bg-blue-50 text-blue-700 border border-blue-200 rounded-full text-xs font-bold">
                                Total: {users.length} User
                            </span>
                        </div>
                        <p className="text-xs text-slate-500 font-medium">
                            Manajemen akun pengguna sistem inventaris dan hak akses.
                        </p>
                    </div>

                    <div className="flex items-center gap-3 w-full md:w-auto">
                        {/* Search Input Realtime */}
                        <SearchInput
                            value={searchQuery}
                            onChange={setSearchQuery}
                            placeholder="Cari sekolah / username..."
                            ariaLabel="Cari sekolah atau username"
                            className="flex-1 md:w-72"
                        />

                        {/* Button Tambah User */}
                        <button
                            onClick={() => {
                                resetCreate();
                                setModalCreateOpen(true);
                            }}
                            className="inline-flex items-center gap-1.5 px-3.5 py-2 bg-blue-600 text-white rounded-xl text-xs font-bold hover:bg-blue-700 transition shadow-xs shrink-0 cursor-pointer"
                        >
                            <UserPlus className="w-4 h-4" /> Tambah User
                        </button>
                    </div>
                </div>

                {/* Tabel User Persis Legacy */}
                <div className="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[900px] text-left text-xs">
                            <thead className="bg-slate-50 text-slate-600 uppercase font-bold tracking-wider border-b border-slate-200 text-[11px]">
                                <tr>
                                    <th className="p-3.5 text-center w-16">ID</th>
                                    <th className="p-3.5">Username</th>
                                    <th className="p-3.5">Role</th>
                                    <th className="p-3.5">Status</th>
                                    <th className="p-3.5">NPSN / ID</th>
                                    <th className="p-3.5">Nama Sekolah</th>
                                    <th className="p-3.5">Dibuat Pada</th>
                                    <th className="p-3.5 text-center w-28">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 font-semibold">
                                {filteredUsers.length === 0 ? (
                                    <tr>
                                        <td colSpan={8}>
                                            <EmptyState
                                                icon={<AlertTriangle className="w-10 h-10 text-slate-300" />}
                                                title="User tidak ditemukan"
                                                description={
                                                    users.length === 0
                                                        ? 'Belum ada user terdaftar. Tambah user baru untuk memulai.'
                                                        : 'Data sekolah atau user tidak cocok dengan pencarian.'
                                                }
                                            />
                                        </td>
                                    </tr>
                                ) : (
                                    filteredUsers.map((u, idx) => {
                                        const isAdmin = u.roles.some((r) =>
                                            ['admin', 'admin_kcd'].includes(r.name)
                                        );

                                        return (
                                            <tr
                                                key={u.id}
                                                className={`transition ${u.is_active ? 'hover:bg-slate-50/80' : 'bg-slate-50/60 hover:bg-slate-50/80'}`}
                                            >
                                                <td className="p-3.5 text-center text-slate-500 font-mono">
                                                    #{idx + 1}
                                                </td>
                                                <td className="p-3.5 text-slate-900 font-bold">
                                                    <div className="inline-flex items-center gap-2">
                                                        <UserCircle2 className="w-4 h-4 text-slate-400" />
                                                        <span>{u.username}</span>
                                                    </div>
                                                </td>
                                                <td className="p-3.5">
                                                    {isAdmin ? (
                                                        <span className="inline-flex items-center gap-1 px-2.5 py-1 bg-rose-50 text-rose-700 border border-rose-200 rounded-md text-[10px] font-bold">
                                                            <Shield className="w-3 h-3" /> Admin
                                                        </span>
                                                    ) : u.roles.some((r) => r.name === 'bendahara_sekolah') ? (
                                                        <span className="inline-flex items-center gap-1 px-2.5 py-1 bg-amber-50 text-amber-700 border border-amber-200 rounded-md text-[10px] font-bold">
                                                            <Building2 className="w-3 h-3" /> Bendahara Sekolah
                                                        </span>
                                                    ) : (
                                                        <span className="inline-flex items-center gap-1 px-2.5 py-1 bg-blue-50 text-blue-700 border border-blue-200 rounded-md text-[10px] font-bold">
                                                            <Building2 className="w-3 h-3" /> Operator Sekolah
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="p-3.5">
                                                    {u.is_active ? (
                                                        <span className="inline-flex items-center gap-1 px-2.5 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-md text-[10px] font-bold">
                                                            Aktif
                                                        </span>
                                                    ) : (
                                                        <span className="inline-flex items-center gap-1 px-2.5 py-1 bg-rose-50 text-rose-700 border border-rose-200 rounded-md text-[10px] font-bold">
                                                            Nonaktif
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="p-3.5 font-mono text-slate-600">
                                                    {u.sekolah?.npsn || (u.sekolah_id ? u.sekolah_id.substring(0, 8) : '-')}
                                                </td>
                                                <td className="p-3.5 text-slate-900 font-semibold uppercase">
                                                    {u.sekolah?.nama_sekolah || 'Induk Dinas Pendidikan KCD X'}
                                                </td>
                                                <td className="p-3.5 text-slate-500 font-mono text-[11px]">
                                                    {u.created_at
                                                        ? new Date(u.created_at).toLocaleDateString('id-ID', {
                                                              day: '2-digit',
                                                              month: 'short',
                                                              year: 'numeric',
                                                          })
                                                        : '-'}
                                                </td>
                                                <td className="p-3.5 text-center">
                                                    <div className="flex items-center justify-center gap-1.5">
                                                        {/* Tombol Edit */}
                                                        <button
                                                            onClick={() => handleOpenEdit(u)}
                                                            aria-label={`Edit user ${u.username}`}
                                                            className="p-1.5 text-amber-600 hover:bg-amber-50 rounded-lg transition cursor-pointer"
                                                            title="Edit / Ganti Password"
                                                        >
                                                            <Pencil className="w-4 h-4" />
                                                        </button>

                                                        {/* Tombol Nonaktifkan / Aktifkan (tersembunyi utk akun sendiri) */}
                                                        {u.id !== auth.user.id && (
                                                            <button
                                                                onClick={() => setToggleTarget(u)}
                                                                aria-label={u.is_active ? `Nonaktifkan user ${u.username}` : `Aktifkan user ${u.username}`}
                                                                className={
                                                                    u.is_active
                                                                        ? 'p-1.5 text-slate-500 hover:bg-slate-100 rounded-lg transition cursor-pointer'
                                                                        : 'p-1.5 text-emerald-600 hover:bg-emerald-50 rounded-lg transition cursor-pointer'
                                                                }
                                                                title={u.is_active ? 'Nonaktifkan User' : 'Aktifkan User'}
                                                            >
                                                                <Power className="w-4 h-4" />
                                                            </button>
                                                        )}

                                                        {/* Tombol Hapus (tersembunyi utk akun sendiri) */}
                                                        {u.id !== auth.user.id && (
                                                            <button
                                                                onClick={() => setModalDeleteUser(u)}
                                                                aria-label={`Hapus user ${u.username}`}
                                                                className="p-1.5 text-rose-600 hover:bg-rose-50 rounded-lg transition cursor-pointer"
                                                                title="Hapus User"
                                                            >
                                                                <Trash2 className="w-4 h-4" />
                                                            </button>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {/* MODAL EDIT USER & GANTI PASSWORD Persis Legacy modalEditUser */}
            <Modal
                isOpen={modalEditOpen}
                onClose={() => setModalEditOpen(false)}
                title="Edit User & Ganti Password"
                maxWidth="md"
            >
                <form onSubmit={handleEditSubmit} className="space-y-3.5">
                            <div>
                                <label className="block text-xs font-bold text-slate-600 uppercase mb-1">
                                    Username
                                </label>
                                <input
                                    type="text"
                                    value={editData.username}
                                    onChange={(e) => setEditData('username', e.target.value)}
                                    className="w-full border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold outline-none focus:ring-2 focus:ring-blue-500"
                                    required
                                />
                                {editErrors.username && (
                                    <p className="text-rose-500 text-xs mt-1">{editErrors.username}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-slate-600 uppercase mb-1">
                                    Password Baru
                                </label>
                                <div className="relative">
                                    <input
                                        type={showPassword ? 'text' : 'password'}
                                        value={editData.password}
                                        onChange={(e) => setEditData('password', e.target.value)}
                                        placeholder="Kosongkan jika tidak ingin mengubah password"
                                        className="w-full border border-slate-200 rounded-xl pl-3 pr-9 py-2 text-xs font-semibold outline-none focus:ring-2 focus:ring-blue-500"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowPassword(!showPassword)}
                                        aria-label={showPassword ? 'Sembunyikan password' : 'Tampilkan password'}
                                        aria-pressed={showPassword}
                                        className="absolute right-3 top-2.5 text-slate-400 hover:text-slate-600"
                                    >
                                        {showPassword ? (
                                            <EyeOff className="w-4 h-4" />
                                        ) : (
                                            <Eye className="w-4 h-4" />
                                        )}
                                    </button>
                                </div>
                                <p className="text-[11px] text-slate-400 mt-1">
                                    *Kosongkan kolom ini jika tidak ada perubahan password.
                                </p>
                                {editErrors.password && (
                                    <p className="text-rose-500 text-xs mt-1">{editErrors.password}</p>
                                )}
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-xs font-bold text-slate-600 uppercase mb-1">
                                        Role / Hak Akses
                                    </label>
                                    <select
                                        value={editData.role}
                                        onChange={(e) => handleEditRoleChange(e.target.value)}
                                        className="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold outline-none focus:ring-2 focus:ring-blue-500"
                                        required
                                    >
                                        <option value="operator_sekolah">Operator Sekolah</option>
                                        <option value="bendahara_sekolah">Bendahara Sekolah</option>
                                        <option value="admin_kcd">Admin (Dinas Pusat)</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-slate-600 uppercase mb-1">
                                        Satuan Pendidikan
                                    </label>
                                    {editData.role === 'admin_kcd' ? (
                                        <input
                                            type="text"
                                            value="Dinas Pendidikan KCD X"
                                            readOnly
                                            className="w-full bg-slate-100 text-slate-500 border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold cursor-not-allowed"
                                        />
                                    ) : (
                                        <select
                                            value={editData.sekolah_id}
                                            onChange={(e) => handleEditSekolahChange(e.target.value)}
                                            className="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold outline-none focus:ring-2 focus:ring-blue-500"
                                            required
                                        >
                                            <option value="" disabled>-- Pilih Sekolah --</option>
                                            {sekolahs.map((s) => (
                                                <option key={s.id} value={s.id}>
                                                    {s.nama_sekolah} {s.npsn ? `(${s.npsn})` : `(${s.kota_kab})`}
                                                </option>
                                            ))}
                                        </select>
                                    )}
                                </div>
                            </div>

                            <div className="flex justify-end gap-2 pt-3">
                                <button
                                    type="button"
                                    onClick={() => setModalEditOpen(false)}
                                    className="px-4 py-2 border border-slate-200 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-50 cursor-pointer"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    disabled={editProcessing}
                                    className="px-4 py-2 bg-amber-500 text-slate-950 font-bold rounded-xl text-xs hover:bg-amber-600 transition shadow-xs cursor-pointer disabled:opacity-50"
                                >
                                    Simpan Perubahan
                                </button>
                            </div>
                        </form>
            </Modal>

            {/* MODAL HAPUS USER Persis Legacy modalHapusUser */}
            <ConfirmDialog
                isOpen={modalDeleteUser !== null}
                onClose={() => setModalDeleteUser(null)}
                onConfirm={handleDeleteConfirm}
                title="Konfirmasi Hapus User"
                message={`Hapus user @${modalDeleteUser?.username ?? ''}? Tindakan ini tidak dapat dibatalkan.`}
                confirmText="Ya, Hapus"
                isDestructive={true}
            />

            {/* MODAL TAMBAH USER BARU */}
            <Modal
                isOpen={modalCreateOpen}
                onClose={() => setModalCreateOpen(false)}
                title="Tambah User Baru"
                maxWidth="md"
            >
                <form onSubmit={handleCreateSubmit} className="space-y-3.5">
                            <div>
                                <label className="block text-xs font-bold text-slate-600 uppercase mb-1">
                                    Nama Lengkap / Petugas
                                </label>
                                <input
                                    type="text"
                                    value={createData.name}
                                    onChange={(e) => setCreateData('name', e.target.value)}
                                    placeholder="Contoh: Operator SMKN 1 Bandung"
                                    className="w-full border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold outline-none focus:ring-2 focus:ring-blue-500"
                                    required
                                />
                                {createErrors.name && (
                                    <p className="text-rose-500 text-xs mt-1">{createErrors.name}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-slate-600 uppercase mb-1">
                                    Username
                                </label>
                                <input
                                    type="text"
                                    value={createData.username}
                                    onChange={(e) => setCreateData('username', e.target.value)}
                                    placeholder="Contoh: smkn1bdg"
                                    className="w-full border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold outline-none focus:ring-2 focus:ring-blue-500"
                                    required
                                />
                                {createErrors.username && (
                                    <p className="text-rose-500 text-xs mt-1">{createErrors.username}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-slate-600 uppercase mb-1">
                                    Password
                                </label>
                                <input
                                    type="password"
                                    value={createData.password}
                                    onChange={(e) => setCreateData('password', e.target.value)}
                                    placeholder="Minimal 6 karakter"
                                    className="w-full border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold outline-none focus:ring-2 focus:ring-blue-500"
                                    required
                                />
                                {createErrors.password && (
                                    <p className="text-rose-500 text-xs mt-1">{createErrors.password}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-slate-600 uppercase mb-1">
                                    Role / Hak Akses
                                </label>
                                <select
                                    value={createData.role}
                                    onChange={(e) => handleCreateRoleChange(e.target.value)}
                                    className="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold outline-none focus:ring-2 focus:ring-blue-500"
                                    required
                                >
                                    <option value="operator_sekolah">Operator Sekolah</option>
                                    <option value="bendahara_sekolah">Bendahara Sekolah</option>
                                    <option value="admin_kcd">Admin (Dinas Pusat)</option>
                                </select>
                            </div>

                            {createData.role !== 'admin_kcd' && (
                                <div>
                                    <label className="block text-xs font-bold text-slate-600 uppercase mb-1">
                                        Pilih Satuan Pendidikan
                                    </label>
                                    <select
                                        value={createData.sekolah_id}
                                        onChange={(e) => handleCreateSekolahChange(e.target.value)}
                                        className="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold outline-none focus:ring-2 focus:ring-blue-500"
                                        required
                                    >
                                        <option value="" disabled>
                                            -- Pilih Sekolah --
                                        </option>
                                        {sekolahs.map((s) => (
                                            <option key={s.id} value={s.id}>
                                                {s.nama_sekolah} {s.npsn ? `(${s.npsn})` : `(${s.kota_kab})`}
                                            </option>
                                        ))}
                                    </select>
                                    {createErrors.sekolah_id && (
                                        <p className="text-rose-500 text-xs mt-1">
                                            {createErrors.sekolah_id}
                                        </p>
                                    )}
                                </div>
                            )}

                            <div className="flex justify-end gap-2 pt-3">
                                <button
                                    type="button"
                                    onClick={() => setModalCreateOpen(false)}
                                    className="px-4 py-2 border border-slate-200 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-50 cursor-pointer"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    disabled={createProcessing}
                                    className="px-4 py-2 bg-blue-600 text-white font-bold rounded-xl text-xs hover:bg-blue-700 transition shadow-xs cursor-pointer disabled:opacity-50"
                                >
                                    Simpan User
                                </button>
                            </div>
                        </form>
            </Modal>
            {/* MODAL KONFIRMASI NONAKTIF/AKTIFKAN USER */}
            <ConfirmDialog
                isOpen={toggleTarget !== null}
                onClose={() => setToggleTarget(null)}
                onConfirm={handleToggleConfirm}
                title={toggleTarget?.is_active ? 'Nonaktifkan User' : 'Aktifkan User'}
                message={
                    toggleTarget?.is_active
                        ? `Nonaktifkan @${toggleTarget?.username}? User tidak dapat login dan semua sesi aktifnya langsung diputus.`
                        : `Aktifkan kembali @${toggleTarget?.username}? User dapat login seperti semula.`
                }
                confirmText={toggleTarget?.is_active ? 'Ya, Nonaktifkan' : 'Ya, Aktifkan'}
                isDestructive={!!toggleTarget?.is_active}
            />
        </AppLayout>
    );
}
