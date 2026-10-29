import { Head, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import EmptyState from '@/Components/EmptyState';
import Pagination from '@/Components/Pagination';
import { formatTanggalTabel } from '@/Utils/format';

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
    jenisLabel,
    sumberDana,
    items,
}: {
    jenis: string;
    jenisLabel: string;
    sumberDana: string;
    items: PaginatedDocs;
}) {
    const { auth } = usePage<any>().props;
    const permissions: string[] = auth?.user?.permissions || [];
    // Halaman ini read-only dan boleh dibuka pemegang `lihat-distribusi-bhp`,
    // tetapi menautkan PDF tanpa izin cetak hanya menghasilkan 403.
    const canCetak = permissions.includes('cetak-dokumen-bhp');
    const prefix = `/distribusi-bhp/${sumberDana.toLowerCase()}`;

    return (
        <AppLayout title={`Laporan ${jenisLabel}`}>
            <Head title={`Laporan ${jenisLabel} | BHP`} />

            <div className="space-y-6">
                <div>
                    <h1 className="text-lg font-bold text-slate-900">
                        Laporan Distribusi BHP — {jenisLabel} ({sumberDana})
                    </h1>
                    <p className="text-sm text-slate-500">
                        Daftar dokumen {jenisLabel.toLowerCase()} yang sudah dibuat, terbaru lebih dulu.
                    </p>
                </div>

                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    {items.data.length === 0 ? (
                        <EmptyState
                            title={`Belum Ada Dokumen ${jenisLabel}`}
                            description={`Belum ada dokumen ${jenisLabel.toLowerCase()} untuk sumber dana ${sumberDana}.`}
                        />
                    ) : (
                        <>
                            <ul className="md:hidden divide-y divide-slate-100">
                                {items.data.map((doc) => (
                                    <li key={doc.id} className="p-4 space-y-1">
                                        <div className="font-semibold text-slate-800 break-words">{doc.nomor ?? '-'}</div>
                                        <div className="text-xs text-slate-500">{formatTanggalTabel(doc.tanggal)}</div>
                                        <div className="text-xs">
                                            {doc.pengajuan ? (
                                                canCetak ? (
                                                    <a
                                                        href={`${prefix}/pengajuan/${doc.pengajuan.id}/dokumen/${doc.id}/pdf`}
                                                        className="inline-flex min-h-9 items-center font-semibold text-blue-600"
                                                    >
                                                        {doc.pengajuan.nomor ?? 'Pengajuan'} ({formatTanggalTabel(doc.pengajuan.tanggal)})
                                                    </a>
                                                ) : (
                                                    <span className="font-semibold text-slate-600">
                                                        {doc.pengajuan.nomor ?? 'Pengajuan'} ({formatTanggalTabel(doc.pengajuan.tanggal)})
                                                    </span>
                                                )
                                            ) : (
                                                <span className="text-slate-500">-</span>
                                            )}
                                        </div>
                                    </li>
                                ))}
                            </ul>

                            <div className="hidden md:block overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="bg-slate-50 text-slate-500 uppercase text-xs">
                                        <tr>
                                            <th className="text-left py-3 px-4">Nomor Dokumen</th>
                                            <th className="text-left py-3 px-4">Tanggal</th>
                                            <th className="text-left py-3 px-4">Referensi Pengajuan</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {items.data.map((doc) => (
                                            <tr key={doc.id}>
                                                <td className="py-3 px-4 font-semibold">{doc.nomor ?? '-'}</td>
                                                <td className="py-3 px-4 whitespace-nowrap">{formatTanggalTabel(doc.tanggal)}</td>
                                                <td className="py-3 px-4">
                                                    {doc.pengajuan ? (
                                                        canCetak ? (
                                                            <a
                                                                href={`${prefix}/pengajuan/${doc.pengajuan.id}/dokumen/${doc.id}/pdf`}
                                                                className="inline-flex min-h-9 items-center text-blue-600 hover:underline font-semibold"
                                                            >
                                                                {doc.pengajuan.nomor ?? 'Pengajuan'} ({formatTanggalTabel(doc.pengajuan.tanggal)})
                                                            </a>
                                                        ) : (
                                                            <span className="text-slate-600 font-semibold">
                                                                {doc.pengajuan.nomor ?? 'Pengajuan'} ({formatTanggalTabel(doc.pengajuan.tanggal)})
                                                            </span>
                                                        )
                                                    ) : (
                                                        <span className="text-slate-500">-</span>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </>
                    )}
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
