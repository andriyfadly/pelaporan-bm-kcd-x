import { Head, useForm } from '@inertiajs/react';
import React from 'react';
import AppLayout from '@/Layouts/AppLayout';
import EmptyState from '@/Components/EmptyState';
import { RefreshCw } from 'lucide-react';

interface SyncLog {
    id: string;
    status: string;
    dibuat: number;
    tetap: number;
    surplus: number;
    perlu_review: number;
    error: string | null;
    created_at: string;
}

interface Props {
    logs: SyncLog[];
    sekolahList: { id: string; nama_sekolah: string }[];
    filter: { sekolah_id: string | null };
}

const BADGE: Record<string, string> = {
    sukses: 'bg-emerald-50 text-emerald-700 border-emerald-100',
    berjalan: 'bg-blue-50 text-blue-700 border-blue-100',
    gagal: 'bg-rose-50 text-rose-700 border-rose-100',
};

export default function Index({ logs, sekolahList, filter }: Props) {
    const form = useForm({
        sekolah_id: filter.sekolah_id ?? '',
        bulan: String(new Date().getMonth() + 1),
        tahun: String(new Date().getFullYear()),
    });

    const jalankan = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/pencatatan-bm/sinkronisasi', { preserveScroll: true });
    };

    return (
        <AppLayout>
            <Head title="Sinkronisasi Inventaris" />

            <div className="space-y-5">
                <header>
                    <h1 className="text-xl font-bold text-slate-800">Sinkronisasi Inventaris</h1>
                    <p className="text-sm text-slate-500">
                        Hanya periode berstatus <strong>disetujui</strong> yang menghasilkan unit. Sinkronisasi aman diulang.
                    </p>
                </header>

                <form onSubmit={jalankan} className="flex flex-wrap items-end gap-2 rounded-2xl border border-slate-200 bg-white p-4">
                    {sekolahList.length > 0 && (
                        <label className="text-xs font-semibold text-slate-600">
                            Sekolah
                            <select
                                value={form.data.sekolah_id}
                                onChange={(e) => form.setData('sekolah_id', e.target.value)}
                                className="mt-1 block rounded-xl border border-slate-200 px-3 py-2 text-sm"
                            >
                                <option value="">Pilih sekolah</option>
                                {sekolahList.map((sekolah) => (
                                    <option key={sekolah.id} value={sekolah.id}>
                                        {sekolah.nama_sekolah}
                                    </option>
                                ))}
                            </select>
                            {form.errors.sekolah_id && <p className="mt-1 text-xs text-rose-600">{form.errors.sekolah_id}</p>}
                        </label>
                    )}
                    <label className="text-xs font-semibold text-slate-600">
                        Bulan
                        <input
                            type="number"
                            min={1}
                            max={12}
                            value={form.data.bulan}
                            onChange={(e) => form.setData('bulan', e.target.value)}
                            className="mt-1 block w-24 rounded-xl border border-slate-200 px-3 py-2 text-sm"
                        />
                    </label>
                    <label className="text-xs font-semibold text-slate-600">
                        Tahun
                        <input
                            type="number"
                            min={2026}
                            value={form.data.tahun}
                            onChange={(e) => form.setData('tahun', e.target.value)}
                            className="mt-1 block w-28 rounded-xl border border-slate-200 px-3 py-2 text-sm"
                        />
                    </label>
                    <button
                        type="submit"
                        disabled={form.processing}
                        className="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700 disabled:opacity-60"
                    >
                        <RefreshCw className="w-4 h-4" /> Jalankan
                    </button>
                </form>

                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    {logs.length === 0 ? (
                        <EmptyState title="Belum ada log sinkronisasi" description="Log muncul setelah sinkronisasi pertama dijalankan." />
                    ) : (
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-400">
                                    <th className="px-4 py-3">Waktu</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3 text-right">Dibuat</th>
                                    <th className="px-4 py-3 text-right">Tetap</th>
                                    <th className="px-4 py-3 text-right">Surplus</th>
                                    <th className="px-4 py-3 text-right">Review</th>
                                </tr>
                            </thead>
                            <tbody>
                                {logs.map((log) => (
                                    <tr key={log.id} className="border-b border-slate-50 last:border-0">
                                        <td className="px-4 py-3 text-slate-600">{log.created_at}</td>
                                        <td className="px-4 py-3">
                                            <span className={`rounded-full border px-2 py-0.5 text-xs font-bold ${BADGE[log.status] ?? ''}`}>
                                                {log.status}
                                            </span>
                                            {log.error && <p className="mt-1 text-xs text-rose-600">{log.error}</p>}
                                        </td>
                                        <td className="px-4 py-3 text-right text-slate-600">{log.dibuat}</td>
                                        <td className="px-4 py-3 text-right text-slate-600">{log.tetap}</td>
                                        <td className="px-4 py-3 text-right text-slate-600">{log.surplus}</td>
                                        <td className="px-4 py-3 text-right text-slate-600">{log.perlu_review}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
