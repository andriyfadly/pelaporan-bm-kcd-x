import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

interface DokumenItem {
    id: string;
    jenis: string;
    nomor: string;
    tanggal_titimangsa: string | null;
}

interface Detail {
    id: string;
    jumlah_diminta: number;
    jumlah_disetujui: number | null;
    barang?: { nama_barang: string; satuan: string | null } | null;
}

interface Pengajuan {
    id: string;
    sumber_dana: string;
    status: string;
    tanggal: string | null;
    details: Detail[];
    dokumen: DokumenItem[];
}

const labelJenis: Record<string, string> = {
    nota_permintaan: 'Nota Permintaan Barang',
    surat_permintaan: 'Surat Permintaan Barang',
    surat_penyaluran: 'Surat Perintah Penyaluran Barang',
};

export default function Show({ pengajuan }: { pengajuan: Pengajuan }) {
    return (
        <AppLayout title="Dokumen BHP">
            <Head title="Dokumen Distribusi BHP" />

            <div className="space-y-6">
                <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-lg font-bold text-slate-900">Dokumen Distribusi BHP</h1>
                            <p className="text-sm text-slate-500">
                                Sumber Dana {pengajuan.sumber_dana} · Status {pengajuan.status} · Tanggal {pengajuan.tanggal ?? '-'}
                            </p>
                        </div>
                        <Link href="/distribusi-bhp/review-pengajuan" className="text-sm font-semibold text-blue-600">
                            ← Kembali
                        </Link>
                    </div>
                </div>

                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-slate-200 font-bold text-sm text-slate-700">Dokumen</div>
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 text-slate-500 uppercase text-xs">
                            <tr>
                                <th className="text-left py-3 px-4">Jenis</th>
                                <th className="text-left py-3 px-4">Nomor</th>
                                <th className="text-left py-3 px-4">Tanggal Titimangsa</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {pengajuan.dokumen.length === 0 ? (
                                <tr><td colSpan={3} className="py-10 text-center text-slate-400 text-xs">Belum ada dokumen.</td></tr>
                            ) : (
                                pengajuan.dokumen.map((doc) => (
                                    <tr key={doc.id}>
                                        <td className="py-3 px-4">{labelJenis[doc.jenis] ?? doc.jenis}</td>
                                        <td className="py-3 px-4 font-mono text-slate-700">{doc.nomor}</td>
                                        <td className="py-3 px-4">{doc.tanggal_titimangsa ?? '-'}</td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-slate-200 font-bold text-sm text-slate-700">Item Disetujui</div>
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 text-slate-500 uppercase text-xs">
                            <tr>
                                <th className="text-left py-3 px-4">Barang</th>
                                <th className="text-right py-3 px-4">Diminta</th>
                                <th className="text-right py-3 px-4">Disetujui</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {pengajuan.details.map((d) => (
                                <tr key={d.id}>
                                    <td className="py-3 px-4">{d.barang?.nama_barang}</td>
                                    <td className="py-3 px-4 text-right">{d.jumlah_diminta}</td>
                                    <td className="py-3 px-4 text-right">{d.jumlah_disetujui ?? '-'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
