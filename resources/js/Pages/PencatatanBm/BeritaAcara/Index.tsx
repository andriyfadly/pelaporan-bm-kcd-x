import { Head, router } from '@inertiajs/react';
import React, { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import EmptyState from '@/Components/EmptyState';
import Modal from '@/Components/Modal';
import { Ban, FileText, Pencil } from 'lucide-react';

interface DokumenBa {
    id: string;
    nomor: string | null;
    tanggal: string | null;
    nomor_dibatalkan_pada: string | null;
    payload: {
        pembatalan?: { alasan?: string; oleh?: string };
        unit?: { nama_barang?: string | null; nomor_register?: string | null };
    };
    created_at: string;
}

interface Props {
    daftar: DokumenBa[];
}

export default function Index({ daftar }: Props) {
    const [editTarget, setEditTarget] = useState<DokumenBa | null>(null);
    const [nomor, setNomor] = useState('');
    const [batalTarget, setBatalTarget] = useState<DokumenBa | null>(null);
    const [alasan, setAlasan] = useState('');
    const [proses, setProses] = useState(false);

    const bukaEdit = (item: DokumenBa) => {
        setEditTarget(item);
        setNomor(item.nomor ?? '');
    };

    const simpanNomor = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editTarget) {
            return;
        }

        setProses(true);
        router.put(`/pencatatan-bm/berita-acara/${editTarget.id}`, { nomor }, {
            preserveScroll: true,
            onFinish: () => {
                setProses(false);
                setEditTarget(null);
            },
        });
    };

    const batalkan = () => {
        if (!batalTarget) {
            return;
        }

        router.delete(`/pencatatan-bm/berita-acara/${batalTarget.id}`, {
            data: { alasan },
            preserveScroll: true,
            onFinish: () => {
                setBatalTarget(null);
                setAlasan('');
            },
        });
    };

    return (
        <AppLayout>
            <Head title="Rekap Berita Acara" />

            <div className="space-y-5">
                <header>
                    <h1 className="text-xl font-bold text-slate-800">Rekap Berita Acara</h1>
                    <p className="text-sm text-slate-500">
                        Koreksi atau batalkan nomor BA. Dokumen yang dibatalkan tetap tersimpan beserta alasannya.
                    </p>
                </header>

                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    {daftar.length === 0 ? (
                        <EmptyState
                            title="Belum ada berita acara"
                            description="BAST dibuat dari halaman detail unit inventaris."
                            icon={<FileText className="w-12 h-12 text-slate-300" />}
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[760px] text-sm">
                                <thead>
                                    <tr className="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-500">
                                        <th className="px-4 py-3">Nomor</th>
                                        <th className="px-4 py-3">Unit</th>
                                        <th className="px-4 py-3">Tanggal</th>
                                        <th className="px-4 py-3 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {daftar.map((item) => (
                                        <tr key={item.id} className="border-b border-slate-50 last:border-0">
                                            <td className="px-4 py-3">
                                                <p className="font-semibold text-slate-700">{item.nomor ?? '-'}</p>
                                                {item.nomor_dibatalkan_pada && (
                                                    <p className="text-xs text-rose-600">
                                                        Dibatalkan: {item.payload.pembatalan?.alasan ?? '-'}
                                                    </p>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-slate-600">
                                                {item.payload.unit?.nama_barang ?? '-'} · {item.payload.unit?.nomor_register ?? '-'}
                                            </td>
                                            <td className="whitespace-nowrap px-4 py-3 text-slate-600">{item.tanggal?.slice(0, 10) ?? item.created_at}</td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center justify-end gap-1">
                                                    <button
                                                        type="button"
                                                        onClick={() => bukaEdit(item)}
                                                        aria-label={`Koreksi nomor ${item.nomor ?? ''}`}
                                                        className="rounded-lg p-2 text-slate-500 hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                                                    >
                                                        <Pencil className="w-4 h-4" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => setBatalTarget(item)}
                                                        aria-label={`Batalkan nomor ${item.nomor ?? ''}`}
                                                        className="rounded-lg p-2 text-rose-600 hover:bg-rose-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                                                    >
                                                        <Ban className="w-4 h-4" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>

            <Modal isOpen={editTarget !== null} onClose={() => setEditTarget(null)} title="Koreksi Nomor BA" maxWidth="sm">
                <form onSubmit={simpanNomor} className="space-y-3">
                    <label className="block text-xs font-semibold text-slate-600">
                        Nomor
                        <input
                            type="text"
                            value={nomor}
                            onChange={(e) => setNomor(e.target.value)}
                            required
                            className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-none"
                        />
                    </label>
                    <div className="flex justify-end gap-2 pt-1">
                        <button
                            type="button"
                            onClick={() => setEditTarget(null)}
                            className="rounded-xl px-4 min-h-10 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-none"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            disabled={proses}
                            className="rounded-xl bg-blue-600 px-4 min-h-10 py-2 text-xs font-bold text-white hover:bg-blue-700 focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-none disabled:opacity-60"
                        >
                            Simpan
                        </button>
                    </div>
                </form>
            </Modal>

            <Modal isOpen={batalTarget !== null} onClose={() => setBatalTarget(null)} title="Batalkan Nomor BA" maxWidth="sm">
                <label className="block text-xs font-semibold text-slate-600">
                    Alasan pembatalan
                    <textarea
                        value={alasan}
                        onChange={(e) => setAlasan(e.target.value)}
                        rows={3}
                        required
                        className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-none"
                    />
                </label>
                <div className="mt-4 flex justify-end gap-2">
                    <button
                        type="button"
                        onClick={() => setBatalTarget(null)}
                        className="rounded-xl px-4 min-h-10 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-none"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        onClick={batalkan}
                        disabled={!alasan}
                        className="rounded-xl bg-rose-600 px-4 min-h-10 py-2 text-xs font-bold text-white hover:bg-rose-700 focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-none disabled:opacity-60"
                    >
                        Batalkan Nomor
                    </button>
                </div>
            </Modal>
        </AppLayout>
    );
}
