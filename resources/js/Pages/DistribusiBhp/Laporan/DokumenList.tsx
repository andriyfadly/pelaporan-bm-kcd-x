import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { FileSpreadsheet } from 'lucide-react';

interface PengajuanRef {
    id: string;
    nomor: string | null;
    tanggal: string | null;
    sekolah_id: string;
}

interface DocItem {
    id: string;
    nomor: string | null;
    tanggal: string | null;
    pengajuan: PengajuanRef | null;
}

export default function DokumenList({
    jenis,
    jenisLabel,
    sumberDana,
    items,
}: {
    jenis: string;
    jenisLabel: string;
    sumberDana: string;
    items: DocItem[];
}) {
    return (
        <AppLayout title={`Laporan ${jenisLabel}`}>
            <Head title={`Laporan ${jenisLabel} | BHP`} />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-lg font-bold text-slate-900">
                        Laporan Distribusi BHP — {jenisLabel} ({sumberDana})
                    </h1>
                    <div className="flex gap-2">
                        {['BOS', 'BOPD'].map((dana) => (
                            <Link
                                key={dana}
                                href={`/distribusi-bhp/laporan/${jenis}?sumber_dana=${dana}`}
                                className={`px-3 py-2 rounded-xl text-sm font-semibold border ${dana === sumberDana ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-700 border-slate-300'}`}
                            >
                                {dana}
                            </Link>
                        ))}
                    </div>
                </div>

                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 text-slate-500 uppercase text-xs">
                            <tr>
                                <th className="text-left py-3 px-4">Nomor Dokumen</th>
                                <th className="text-left py-3 px-4">Tanggal</th>
                                <th className="text-left py-3 px-4">Referensi Pengajuan</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {items.length === 0 ? (
                                <tr>
                                    <td colSpan={3} className="py-10 text-center text-slate-400 text-xs">
                                        Belum ada dokumen {jenisLabel.toLowerCase()}.
                                    </td>
                                </tr>
                            ) : (
                                items.map((doc) => (
                                    <tr key={doc.id}>
                                        <td className="py-3 px-4 font-semibold">{doc.nomor ?? '-'}</td>
                                        <td className="py-3 px-4">{doc.tanggal ?? '-'}</td>
                                        <td className="py-3 px-4">
                                            {doc.pengajuan ? (
                                                <a
                                                    href={`/distribusi-bhp/pengajuan/${doc.pengajuan.id}/dokumen/${doc.id}/pdf`}
                                                    className="text-blue-600 hover:underline font-semibold"
                                                >
                                                    {doc.pengajuan.nomor ?? 'Pengajuan'} ({doc.pengajuan.tanggal ?? '-'})
                                                </a>
                                            ) : (
                                                <span className="text-slate-400">-</span>
                                            )}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}