import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { FileDown, Printer } from 'lucide-react';
import { formatTanggal } from '@/Utils/format';

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

    // Selalu lewat prefix sumber dana pengajuan: route tanpa prefix jatuh ke
    // sumber dana aktif request, bukan sumber dana dokumen ini.
    const prefix = `/distribusi-bhp/${pengajuan.sumber_dana.toLowerCase()}`;

    const buat = (jenis: string) =>
        router.post(`${prefix}/pengajuan/${pengajuan.id}/dokumen/tambahan`, {
            jenis,
            tanggal: tanggal || null,
        });

    return (
        <AppLayout title="Dokumen BHP">
            <Head title="Dokumen Distribusi BHP" />

            <div className="space-y-6">
                <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-lg font-bold text-slate-900">Dokumen Distribusi BHP</h1>
                            <p className="text-sm text-slate-500">
                                Sumber Dana {pengajuan.sumber_dana} · Status {pengajuan.status} · Tanggal {formatTanggal(pengajuan.tanggal)}
                            </p>
                        </div>
                        <Link href={`${prefix}/pengajuan`} className="text-sm font-semibold text-blue-600">
                            ← Kembali
                        </Link>
                    </div>
                    <div className="flex flex-wrap items-end gap-3 mt-4">
                        <div>
                            <label htmlFor="tanggal-dokumen" className="block text-xs font-bold uppercase text-slate-500 mb-1">
                                Tanggal Titimangsa
                            </label>
                            <input
                                id="tanggal-dokumen"
                                type="date"
                                value={tanggal}
                                onChange={(e) => setTanggal(e.target.value)}
                                className="px-3 py-2 border border-slate-300 rounded-xl text-sm"
                            />
                            <p className="text-[11px] text-slate-400 mt-1">Kosong = tanggal pengajuan.</p>
                        </div>
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
                                className="inline-flex items-center gap-2 px-3 py-2 rounded-xl text-xs font-semibold bg-slate-100 text-slate-700 hover:bg-slate-200"
                            >
                                + {opt.label}
                            </button>
                        ))}
                    </div>
                    {pengajuan.dokumen.length > 0 && (
                        <button
                            type="button"
                            onClick={() => router.post(`${prefix}/pengajuan/${pengajuan.id}/dokumen/batch`)}
                            className="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold bg-emerald-600 text-white hover:bg-emerald-700"
                        >
                            <Printer className="w-4 h-4" /> Unduh Batch PDF
                        </button>
                    )}
                </div>

                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-slate-200 font-bold text-sm text-slate-700">Dokumen</div>
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
                            {pengajuan.dokumen.length === 0 ? (
                                <tr><td colSpan={4} className="py-10 text-center text-slate-400 text-xs">Belum ada dokumen.</td></tr>
                            ) : (
                                pengajuan.dokumen.map((doc) => (
                                    <tr key={doc.id}>
                                        <td className="py-3 px-4">{labelJenis[doc.jenis] ?? doc.jenis}</td>
                                        <td className="py-3 px-4 font-mono text-slate-700">{doc.nomor}</td>
                                        <td className="py-3 px-4">{formatTanggal(doc.tanggal_titimangsa)}</td>
                                        <td className="py-3 px-4 text-center">
                                            <a
                                                href={`${prefix}/pengajuan/${pengajuan.id}/dokumen/${doc.id}/pdf`}
                                                className="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold bg-blue-600 text-white hover:bg-blue-700"
                                            >
                                                <FileDown className="w-3.5 h-3.5" /> PDF
                                            </a>
                                        </td>
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
