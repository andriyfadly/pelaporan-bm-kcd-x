import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import EmptyState from '@/Components/EmptyState';
import { FileDown, Printer } from 'lucide-react';
import { formatTanggal, formatTanggalTabel } from '@/Utils/format';

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
    bast: 'Berita Acara Serah Terima',
    bap: 'Berita Acara Pemeriksaan',
    stok_opname: 'Berita Acara Stok Opname',
    buku_pengeluaran: 'Buku Pengeluaran Barang',
    buku_penyaluran: 'Buku Penyaluran Barang',
};

export default function Show({ pengajuan }: { pengajuan: Pengajuan }) {
    // Tanggal titimangsa dokumen tambahan. Kosong = tanggal pengajuan. Tanggal
    // lampau berarti snapshot isi mengikuti periode tanggal itu.
    const [tanggal, setTanggal] = useState('');
    // `generateTambahan` menolak jenis yang sudah ada, tetapi klik ganda tetap
    // mengirim dua request; kunci per jenis agar tombolnya tidak aktif lagi.
    const [membuat, setMembuat] = useState<string | null>(null);

    // Selalu lewat prefix sumber dana pengajuan: route tanpa prefix jatuh ke
    // sumber dana aktif request, bukan sumber dana dokumen ini.
    const prefix = `/distribusi-bhp/${pengajuan.sumber_dana.toLowerCase()}`;

    const buat = (jenis: string) => {
        if (membuat) return;
        setMembuat(jenis);
        router.post(
            `${prefix}/pengajuan/${pengajuan.id}/dokumen/tambahan`,
            { jenis, tanggal: tanggal || null },
            { onFinish: () => setMembuat(null) },
        );
    };

    // Unduhan batch berisi ZIP, bukan respons Inertia: `router.post` memakai XHR
    // dengan responseType text sehingga berkas tidak pernah sampai ke pengguna.
    // Form POST asli membiarkan browser menangani Content-Disposition.
    const unduhBatch = () => {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `${prefix}/pengajuan/${pengajuan.id}/dokumen/batch`;

        const csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = '_token';
        csrf.value = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';

        form.appendChild(csrf);
        document.body.appendChild(form);
        form.submit();
        form.remove();
    };

    return (
        <AppLayout title="Dokumen BHP">
            <Head title="Dokumen Distribusi BHP" />

            <div className="space-y-6">
                <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h1 className="text-lg font-bold text-slate-900">Dokumen Distribusi BHP</h1>
                            <p className="text-sm text-slate-500">
                                Sumber Dana {pengajuan.sumber_dana} · Status {pengajuan.status} · Tanggal {formatTanggalTabel(pengajuan.tanggal)}
                            </p>
                        </div>
                        <Link
                            href={`${prefix}/pengajuan`}
                            className="inline-flex min-h-10 shrink-0 items-center text-sm font-semibold text-blue-600 hover:text-blue-700"
                        >
                            ← Kembali
                        </Link>
                    </div>
                    <div className="mt-4 space-y-3">
                        <div className="max-w-xs">
                            <label htmlFor="tanggal-dokumen" className="block text-xs font-bold uppercase text-slate-500 mb-1">
                                Tanggal Titimangsa
                            </label>
                            <input
                                id="tanggal-dokumen"
                                type="date"
                                value={tanggal}
                                onChange={(e) => setTanggal(e.target.value)}
                                className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm"
                            />
                            <p className="text-xs text-slate-500 mt-1">Kosong = tanggal pengajuan.</p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {[
                                { jenis: 'bast', label: 'BAST' },
                                { jenis: 'bap', label: 'BAP' },
                                { jenis: 'stok_opname', label: 'Stok Opname' },
                                { jenis: 'buku_pengeluaran', label: 'Buku Pengeluaran' },
                                { jenis: 'buku_penyaluran', label: 'Buku Penyaluran' },
                            ].map((opt) => (
                                <button
                                    key={opt.jenis}
                                    type="button"
                                    onClick={() => buat(opt.jenis)}
                                    disabled={membuat !== null}
                                    className="inline-flex min-h-10 items-center gap-2 px-3 rounded-xl text-sm font-semibold bg-slate-100 text-slate-700 hover:bg-slate-200 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    {membuat === opt.jenis ? 'Membuat…' : `+ ${opt.label}`}
                                </button>
                            ))}
                        </div>
                    </div>
                    {pengajuan.dokumen.length > 0 && (
                        <button
                            type="button"
                            onClick={() => unduhBatch()}
                            className="mt-4 inline-flex min-h-10 w-full items-center justify-center gap-2 px-4 rounded-xl text-sm font-semibold bg-emerald-600 text-white hover:bg-emerald-700 sm:w-auto"
                        >
                            <Printer className="w-4 h-4" /> Unduh Batch PDF
                        </button>
                    )}
                </div>

                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div className="px-4 py-4 border-b border-slate-200 font-bold text-sm text-slate-700 sm:px-6">Dokumen</div>
                    {pengajuan.dokumen.length === 0 ? (
                        <EmptyState
                            title="Belum Ada Dokumen"
                            description="Buat BAST, BAP, atau dokumen lain lewat tombol di atas."
                        />
                    ) : (
                        <>
                            <ul className="md:hidden divide-y divide-slate-100">
                                {pengajuan.dokumen.map((doc) => (
                                    <li key={doc.id} className="p-4 space-y-2">
                                        <div>
                                            <div className="font-semibold text-slate-800">
                                                {labelJenis[doc.jenis] ?? doc.jenis}
                                            </div>
                                            <div className="font-mono text-xs text-slate-500">{doc.nomor}</div>
                                            <div className="text-xs text-slate-500">
                                                {formatTanggalTabel(doc.tanggal_titimangsa)}
                                            </div>
                                        </div>
                                        <a
                                            href={`${prefix}/pengajuan/${pengajuan.id}/dokumen/${doc.id}/pdf`}
                                            className="inline-flex min-h-10 w-full items-center justify-center gap-1 rounded-xl text-sm font-semibold bg-blue-600 text-white"
                                        >
                                            <FileDown className="w-4 h-4" /> Unduh PDF
                                        </a>
                                    </li>
                                ))}
                            </ul>

                            <div className="hidden md:block overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="bg-slate-50 text-slate-500 uppercase text-xs">
                                        <tr>
                                            <th className="text-left py-3 px-4">Jenis</th>
                                            <th className="text-left py-3 px-4">Nomor</th>
                                            <th className="text-left py-3 px-4">Tanggal Titimangsa</th>
                                            <th className="text-center py-3 px-4">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {pengajuan.dokumen.map((doc) => (
                                            <tr key={doc.id}>
                                                <td className="py-3 px-4">{labelJenis[doc.jenis] ?? doc.jenis}</td>
                                                <td className="py-3 px-4 font-mono text-slate-700">{doc.nomor}</td>
                                                <td className="py-3 px-4">{formatTanggalTabel(doc.tanggal_titimangsa)}</td>
                                                <td className="py-3 px-4 text-center">
                                                    <a
                                                        href={`${prefix}/pengajuan/${pengajuan.id}/dokumen/${doc.id}/pdf`}
                                                        className="inline-flex min-h-9 items-center gap-1 px-3 rounded-lg text-xs font-semibold bg-blue-600 text-white hover:bg-blue-700"
                                                    >
                                                        <FileDown className="w-3.5 h-3.5" /> PDF
                                                    </a>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </>
                    )}
                </div>

                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div className="px-4 py-4 border-b border-slate-200 font-bold text-sm text-slate-700 sm:px-6">Item Disetujui</div>
                    <div className="overflow-x-auto">
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
            </div>
        </AppLayout>
    );
}
