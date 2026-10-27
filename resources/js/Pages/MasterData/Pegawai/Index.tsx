import { Head, router, useForm } from '@inertiajs/react';
import React, { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Modal from '@/Components/Modal';
import ConfirmDialog from '@/Components/ConfirmDialog';
import EmptyState from '@/Components/EmptyState';
import { Pencil, Plus, Trash2, Users } from 'lucide-react';

interface Pegawai {
    id: string;
    nama_lengkap: string;
    nip: string | null;
    pangkat_golongan: string | null;
    jabatan: string | null;
    ruang: string | null;
    unit_aktif_count: number;
}

interface Props {
    pegawai: Pegawai[];
}

const FORM_KOSONG = {
    nama_lengkap: '',
    nip: '',
    pangkat_golongan: '',
    jabatan: '',
    ruang: '',
};

export default function Index({ pegawai }: Props) {
    const [modalOpen, setModalOpen] = useState(false);
    const [editTarget, setEditTarget] = useState<Pegawai | null>(null);
    const [hapusTarget, setHapusTarget] = useState<Pegawai | null>(null);

    const form = useForm({ ...FORM_KOSONG });

    const buka = (item: Pegawai | null) => {
        setEditTarget(item);
        form.setData(
            item
                ? {
                      nama_lengkap: item.nama_lengkap,
                      nip: item.nip ?? '',
                      pangkat_golongan: item.pangkat_golongan ?? '',
                      jabatan: item.jabatan ?? '',
                      ruang: item.ruang ?? '',
                  }
                : { ...FORM_KOSONG },
        );
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
            form.put(`/master-data/pegawai/${editTarget.id}`, options);
            return;
        }

        form.post('/master-data/pegawai', options);
    };

    const hapus = () => {
        if (!hapusTarget) {
            return;
        }

        router.delete(`/master-data/pegawai/${hapusTarget.id}`, {
            preserveScroll: true,
            onFinish: () => setHapusTarget(null),
        });
    };

    return (
        <AppLayout>
            <Head title="Master Pegawai" />

            <div className="space-y-5">
                <header className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-bold text-slate-800">Master Pegawai</h1>
                        <p className="text-sm text-slate-500">Pegawai sekolah yang dapat menjadi penanggung jawab unit inventaris.</p>
                    </div>
                    <button
                        type="button"
                        onClick={() => buka(null)}
                        className="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700"
                    >
                        <Plus className="w-4 h-4" /> Tambah Pegawai
                    </button>
                </header>

                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    {pegawai.length === 0 ? (
                        <EmptyState
                            title="Belum ada pegawai"
                            description="Tambahkan pegawai agar unit inventaris punya penanggung jawab."
                            icon={<Users className="w-12 h-12 text-slate-300" />}
                        />
                    ) : (
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-400">
                                    <th className="px-4 py-3">Nama</th>
                                    <th className="px-4 py-3">NIP</th>
                                    <th className="px-4 py-3">Jabatan</th>
                                    <th className="px-4 py-3 text-right">Unit Aktif</th>
                                    <th className="px-4 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {pegawai.map((item) => (
                                    <tr key={item.id} className="border-b border-slate-50 last:border-0">
                                        <td className="px-4 py-3">
                                            <p className="font-semibold text-slate-700">{item.nama_lengkap}</p>
                                            <p className="text-xs text-slate-400">{item.pangkat_golongan ?? '-'}</p>
                                        </td>
                                        <td className="px-4 py-3 font-mono text-xs text-slate-500">{item.nip ?? '-'}</td>
                                        <td className="px-4 py-3 text-slate-600">{item.jabatan ?? '-'}</td>
                                        <td className="px-4 py-3 text-right text-slate-600">{item.unit_aktif_count}</td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-end gap-1">
                                                <button
                                                    type="button"
                                                    onClick={() => buka(item)}
                                                    aria-label={`Ubah pegawai ${item.nama_lengkap}`}
                                                    className="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100"
                                                >
                                                    <Pencil className="w-4 h-4" />
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => setHapusTarget(item)}
                                                    aria-label={`Hapus pegawai ${item.nama_lengkap}`}
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

            <Modal isOpen={modalOpen} onClose={() => setModalOpen(false)} title={editTarget ? 'Ubah Pegawai' : 'Tambah Pegawai'}>
                <form onSubmit={simpan} className="space-y-3">
                    <Input
                        label="Nama Lengkap"
                        value={form.data.nama_lengkap}
                        onChange={(value) => form.setData('nama_lengkap', value)}
                        error={form.errors.nama_lengkap}
                    />
                    <Input label="NIP" value={form.data.nip} onChange={(value) => form.setData('nip', value)} />
                    <Input
                        label="Pangkat / Golongan"
                        value={form.data.pangkat_golongan}
                        onChange={(value) => form.setData('pangkat_golongan', value)}
                    />
                    <Input label="Jabatan" value={form.data.jabatan} onChange={(value) => form.setData('jabatan', value)} />
                    <Input label="Ruang" value={form.data.ruang} onChange={(value) => form.setData('ruang', value)} />
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
                title="Hapus Pegawai"
                message={`${hapusTarget?.nama_lengkap ?? 'Pegawai ini'} tidak akan muncul lagi sebagai pilihan penanggung jawab; riwayat mutasi tetap tersimpan.`}
                confirmText="Hapus"
            />
        </AppLayout>
    );
}

interface InputProps {
    label: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
}

function Input({ label, value, onChange, error }: InputProps) {
    return (
        <label className="block text-xs font-semibold text-slate-600">
            {label}
            <input
                type="text"
                value={value}
                onChange={(e) => onChange(e.target.value)}
                className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
            />
            {error && <p className="mt-1 text-xs text-rose-600">{error}</p>}
        </label>
    );
}
