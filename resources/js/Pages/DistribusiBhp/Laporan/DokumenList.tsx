import { Head } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { FileSpreadsheet } from 'lucide-react';
import Pagination from '@/Components/Pagination';

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

interface PaginatedDocs {
    data: DocItem[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    total: number;
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
    items: PaginatedDocs;
}) {
    return (
        <AppLayout title={`Laporan ${jenisLabel}`}>
            <Head title={`Laporan ${jenisLabel} | BHP`} />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-lg font-bold text-slate-900">
                        Laporan Distribusi BHP — {jenisLabel} ({sumberDana})
                    </h1>
                    
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
                            {items.data.length === 0 ? (
                                <tr>
                                    <td colSpan={3} className="py-10 text-center text-slate-400 text-xs">
                                        Belum ada dokumen {jenisLabel.toLowerCase()}.
                                    </td>
                                </tr>
                            ) : (
                                items.data.map((doc) => (
                                    <tr key={doc.id}>
                                        <td className="py-3 px-4 font-semibold">{doc.nomor ?? '-'}</td>
                                        <td className="py-3 px-4">{doc.tanggal ?? '-'}</td>
                                        <td className="py-3 px-4">
                                            {doc.pengajuan ? (
                                                <a
                                                    href={`/distribusi-bhp/${sumberDana.toLowerCase()}/pengajuan/${doc.pengajuan.id}/dokumen/${doc.id}/pdf`}
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
                    {items.total > 0 && (
                        <div className="border-t border-slate-100 p-4">
                            <Pagination links={items.links} total={items.total} />
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
