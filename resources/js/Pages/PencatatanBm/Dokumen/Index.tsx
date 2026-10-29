import { Head, router } from '@inertiajs/react';
import React, { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import EmptyState from '@/Components/EmptyState';
import { Download, FileText, Printer } from 'lucide-react';

interface DokumenItem {
    id: string;
    jenis: string;
    nomor: string | null;
    tanggal: string | null;
    created_at: string;
}

interface RuangRingkas {
    id: string;
    nama_ruang: string;
}

interface Props {
    dokumen: DokumenItem[];
    ruangList: RuangRingkas[];
}

const LABEL_JENIS: Record<string, string> = {
    bast: 'Berita Acara Serah Terima',
    fakta_integritas: 'Fakta Integritas',
    surat_pengembalian: 'Surat Permohonan Pengembalian',
};

export default function Index({ dokumen, ruangList }: Props) {
    const [ruangId, setRuangId] = useState(ruangList[0]?.id ?? '');
    const [tahun, setTahun] = useState(() => new Date().getFullYear());

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

                <section className="rounded-2xl border border-slate-200 bg-white p-5">
                    <h2 className="mb-1 flex items-center gap-1.5 font-bold text-slate-800">
                        <Printer className="w-4 h-4 text-slate-500" /> Cetak Langsung
                    </h2>
                    <p className="mb-3 text-xs text-slate-500">
                        Cetak langsung memakai data dan pejabat yang aktif saat ini, dan tidak tersimpan sebagai dokumen.
                    </p>

                    <div className="flex flex-wrap items-end gap-3">
                        <label className="block w-52 text-xs font-semibold text-slate-600">
                            Ruang (KIR)
                            <select
                                value={ruangId}
                                onChange={(e) => setRuangId(e.target.value)}
                                className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                            >
                                {ruangList.length === 0 && <option value="">Belum ada ruang</option>}
                                {ruangList.map((ruang) => (
                                    <option key={ruang.id} value={ruang.id}>
                                        {ruang.nama_ruang}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <a
                            href={`/pencatatan-bm/dokumen/kir?ruang=${ruangId}`}
                            aria-disabled={!ruangId}
                            className={`inline-flex items-center gap-1.5 rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold ${
                                ruangId ? 'text-slate-700 hover:bg-slate-50' : 'pointer-events-none text-slate-300'
                            }`}
                        >
                            <FileText className="w-3.5 h-3.5" /> Kartu Inventaris Ruangan
                        </a>
                        <label className="block w-28 text-xs font-semibold text-slate-600">
                            Tahun
                            <input
                                type="number"
                                value={tahun}
                                onChange={(e) => setTahun(Number(e.target.value))}
                                className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                            />
                        </label>
                        {[
                            { url: '/pencatatan-bm/dokumen/buku-inventaris', label: 'Buku Inventaris' },
                            { url: '/pencatatan-bm/dokumen/sensus', label: 'Sensus Aset' },
                        ].map((cetak) => (
                            <a
                                key={cetak.url}
                                href={`${cetak.url}?tahun=${tahun}`}
                                className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 px-3 min-h-10 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                            >
                                <FileText className="w-3.5 h-3.5" /> {cetak.label}
                            </a>
                        ))}
                    </div>
                </section>

                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    {dokumen.length === 0 ? (
                        <EmptyState
                            title="Belum ada dokumen"
                            description="Buat BAST atau fakta integritas dari halaman detail unit."
                            icon={<FileText className="w-12 h-12 text-slate-300" />}
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[640px] text-sm">
                                <thead>
                                    <tr className="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-500">
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
                                            <td className="whitespace-nowrap px-4 py-3 text-slate-600">{item.nomor ?? '-'}</td>
                                            <td className="whitespace-nowrap px-4 py-3 text-slate-600">{item.tanggal?.slice(0, 10) ?? item.created_at}</td>
                                            <td className="px-4 py-3 text-right">
                                                <button
                                                    type="button"
                                                    onClick={() => router.get(`/pencatatan-bm/dokumen/${item.id}/pdf`)}
                                                    className="inline-flex items-center gap-1 rounded-lg min-h-9 px-3 py-1.5 text-xs font-bold text-blue-600 hover:bg-blue-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                                                >
                                                    <Download className="w-3.5 h-3.5" /> Unduh
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
