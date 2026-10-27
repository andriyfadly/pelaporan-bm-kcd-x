import { Head, router } from '@inertiajs/react';
import React from 'react';
import AppLayout from '@/Layouts/AppLayout';
import EmptyState from '@/Components/EmptyState';
import { Download, FileText } from 'lucide-react';

interface DokumenItem {
    id: string;
    jenis: string;
    nomor: string | null;
    tanggal: string | null;
    created_at: string;
}

interface Props {
    dokumen: DokumenItem[];
}

const LABEL_JENIS: Record<string, string> = {
    bast: 'Berita Acara Serah Terima',
    fakta_integritas: 'Fakta Integritas',
    surat_pengembalian: 'Surat Permohonan Pengembalian',
};

export default function Index({ dokumen }: Props) {
    return (
        <AppLayout>
            <Head title="Dokumen Inventaris" />

            <div className="space-y-5">
                <header>
                    <h1 className="text-xl font-bold text-slate-800">Dokumen Inventaris</h1>
                    <p className="text-sm text-slate-500">
                        Dokumen tersimpan membawa snapshot saat dibuat, jadi cetak ulang tidak berubah setelah koreksi data.
                    </p>
                </header>

                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    {dokumen.length === 0 ? (
                        <EmptyState
                            title="Belum ada dokumen"
                            description="Buat BAST atau fakta integritas dari halaman detail unit."
                            icon={<FileText className="w-12 h-12 text-slate-300" />}
                        />
                    ) : (
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-400">
                                    <th className="px-4 py-3">Jenis</th>
                                    <th className="px-4 py-3">Nomor</th>
                                    <th className="px-4 py-3">Tanggal</th>
                                    <th className="px-4 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {dokumen.map((item) => (
                                    <tr key={item.id} className="border-b border-slate-50 last:border-0">
                                        <td className="px-4 py-3 font-semibold text-slate-700">
                                            {LABEL_JENIS[item.jenis] ?? item.jenis}
                                        </td>
                                        <td className="px-4 py-3 text-slate-600">{item.nomor ?? '-'}</td>
                                        <td className="px-4 py-3 text-slate-600">{item.tanggal ?? item.created_at}</td>
                                        <td className="px-4 py-3 text-right">
                                            <button
                                                type="button"
                                                onClick={() => router.get(`/pencatatan-bm/dokumen/${item.id}/pdf`)}
                                                className="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-bold text-blue-600 hover:bg-blue-50"
                                            >
                                                <Download className="w-3.5 h-3.5" /> Unduh
                                            </button>
                                        </td>
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
