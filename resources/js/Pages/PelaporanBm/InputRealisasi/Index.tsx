import { Head, router, Link, useForm } from '@inertiajs/react';
import React, { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import ConfirmDialog from '@/Components/ConfirmDialog';
import {
    FileSpreadsheet,
    Plus,
    Pencil,
    CheckCircle2,
    Lock,
    ChevronDown,
    ChevronRight,
    Calendar,
    Send,
    Hourglass,
    CheckCircle,
} from 'lucide-react';
import { formatRupiah, getNamaBulan } from '@/Utils/format';

interface RekeningRow {
    kodering: string;
    acuan_id: string;
    nominal_acuan: number;
    nominal_realisasi: number;
    kekurangan: number;
    list_uraian: string[];
}

interface Props {
    daftarRekening: RekeningRow[];
    totalAcuan: number;
    totalRealisasi: number;
    totalKekurangan: number;
    bulan: number;
    isLocked: boolean;
    statusKirim: string;
}

export default function Index({
    daftarRekening = [],
    totalAcuan = 0,
    totalRealisasi = 0,
    totalKekurangan = 0,
    bulan,
    isLocked,
    statusKirim,
}: Props) {
    const [expandedKodering, setExpandedKodering] = useState<string[]>([]);
    const [showKirimConfirm, setShowKirimConfirm] = useState(false);
    const isReadOnly = isLocked || statusKirim === 'menunggu_approval' || statusKirim === 'disetujui';

    const { post, processing } = useForm({
        bulan_realisasi: bulan,
    });

    const toggleExpand = (kodering: string) => {
        setExpandedKodering((prev) =>
            prev.includes(kodering) ? prev.filter((k) => k !== kodering) : [...prev, kodering]
        );
    };

    const isAllCompleted = daftarRekening.length > 0 && totalKekurangan <= 0;

    const handleKirimLaporan = (e: React.FormEvent) => {
        e.preventDefault();
        if (!isAllCompleted) return;
        setShowKirimConfirm(true);
    };

    return (
        <AppLayout title="Input Realisasi - Target Acuan Kerja">
            <Head title={`Input Realisasi | Bulan ${getNamaBulan(bulan)}`} />

            <div className="space-y-6">
                {/* Header Card (Matching legacy input_realisasi.php) */}
                <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold">
                                <FileSpreadsheet className="w-5 h-5" />
                            </span>
                            <h1 className="text-xl font-black text-slate-800 tracking-tight">
                                Target Acuan Kerja Realisasi
                            </h1>
                        </div>
                        <p className="text-xs text-slate-500 mt-1">
                            Menampilkan daftar kodering belanja modal untuk periode <strong>Bulan {getNamaBulan(bulan)} ({bulan})</strong>.
                            Klik Kode Rekening untuk melihat rincian uraiannya.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Link
                            href="/pelaporan-bm/input-realisasi/pilih-bulan"
                            className="inline-flex items-center gap-1.5 px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition shadow-sm"
                        >
                            <Calendar className="w-4 h-4 text-slate-500" />
                            <span>Ganti Bulan</span>
                        </Link>
                        {isReadOnly && (
                            <span className="inline-flex items-center gap-1 px-3 py-1.5 bg-red-100 text-red-700 rounded-xl text-xs font-bold border border-red-200">
                                <Lock className="w-3.5 h-3.5" /> Terkunci
                            </span>
                        )}
                    </div>
                </div>

                {/* Table Card */}
                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div className="p-4 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                        <h2 className="text-sm font-bold text-slate-800">
                            Daftar Rekening Belanja Modal ({daftarRekening.length} Kodering)
                        </h2>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm border-collapse">
                            <thead className="bg-[#1e3a8a] text-white text-xs uppercase font-extrabold tracking-wider">
                                <tr>
                                    <th className="p-3.5 w-[35%]">Kode Rekening</th>
                                    <th className="p-3.5 text-right w-[20%]">Nilai Acuan</th>
                                    <th className="p-3.5 text-right w-[20%]">Realisasi</th>
                                    <th className="p-3.5 text-right w-[15%]">Kekurangan</th>
                                    <th className="p-3.5 text-center w-[10%]">Aksi Kerja</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 font-medium">
                                {daftarRekening.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="p-8 text-center text-slate-400">
                                            Tidak ada target acuan kodering anggaran pada bulan ini.
                                        </td>
                                    </tr>
                                ) : (
                                    daftarRekening.map((row) => {
                                        const isExpanded = expandedKodering.includes(row.kodering);
                                        const isDone = row.kekurangan <= 0;
                                        return (
                                            <tr key={row.kodering} className="hover:bg-slate-50/80 transition">
                                                <td className="p-3.5 align-top">
                                                    <button
                                                        type="button"
                                                        onClick={() => toggleExpand(row.kodering)}
                                                        className="font-mono font-bold text-slate-800 hover:text-blue-600 flex items-center gap-1.5 cursor-pointer text-left"
                                                    >
                                                        {isExpanded ? (
                                                            <ChevronDown className="w-4 h-4 text-blue-600 shrink-0" />
                                                        ) : (
                                                            <ChevronRight className="w-4 h-4 text-slate-400 shrink-0" />
                                                        )}
                                                        <span>{row.kodering}</span>
                                                    </button>

                                                    {isExpanded && (
                                                        <div className="mt-2 pl-4 border-l-2 border-blue-400 space-y-1 text-xs text-slate-600 bg-blue-50/40 p-2.5 rounded-r-lg">
                                                            <div className="font-bold text-slate-700 text-[11px] uppercase tracking-wider mb-1">
                                                                Daftar Uraian Pekerjaan:
                                                            </div>
                                                            {row.list_uraian.length === 0 ? (
                                                                <div className="italic text-slate-400">Uraian tidak ditemukan.</div>
                                                            ) : (
                                                                row.list_uraian.map((u, uIdx) => (
                                                                    <div key={uIdx} className="truncate" title={u}>
                                                                        {uIdx + 1}. {u}
                                                                    </div>
                                                                ))
                                                            )}
                                                        </div>
                                                    )}
                                                </td>

                                                <td className="p-3.5 text-right font-mono text-xs font-semibold text-slate-600 align-top">
                                                    {formatRupiah(row.nominal_acuan)}
                                                </td>

                                                <td className="p-3.5 text-right font-mono text-xs font-bold text-blue-600 align-top">
                                                    {formatRupiah(row.nominal_realisasi)}
                                                </td>

                                                <td
                                                    className={`p-3.5 text-right font-mono text-xs font-bold align-top ${
                                                        isDone
                                                            ? 'text-emerald-600'
                                                            : row.kekurangan < row.nominal_acuan
                                                            ? 'text-amber-600'
                                                            : 'text-rose-600'
                                                    }`}
                                                >
                                                    {formatRupiah(row.kekurangan)}
                                                </td>

                                                <td className="p-3.5 text-center align-top">
                                                    <div className="flex items-center justify-center gap-1.5">
                                                        {isDone ? (
                                                            <>
                                                                <span className="inline-flex items-center gap-1 px-2.5 py-1 bg-emerald-100 text-emerald-800 rounded-lg text-xs font-bold">
                                                                    <CheckCircle2 className="w-3.5 h-3.5" /> Selesai
                                                                </span>
                                                                {!isReadOnly && (
                                                                    <Link
                                                                        href={`/pelaporan-bm/input-realisasi/edit?kodering=${encodeURIComponent(
                                                                            row.kodering
                                                                        )}&bulan_realisasi=${bulan}`}
                                                                        className="inline-flex items-center gap-1 px-2.5 py-1 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-xs font-bold transition shadow-sm"
                                                                        title="Ubah alokasi realisasi kodering ini"
                                                                    >
                                                                        <Pencil className="w-3.5 h-3.5" /> Edit
                                                                    </Link>
                                                                )}
                                                            </>
                                                        ) : (
                                                            <>
                                                                {!isReadOnly ? (
                                                                    <Link
                                                                        href={`/pelaporan-bm/input-realisasi/tambah?kodering=${encodeURIComponent(
                                                                            row.kodering
                                                                        )}&bulan_realisasi=${bulan}`}
                                                                        className="inline-flex items-center justify-center w-8 h-8 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-bold shadow-sm transition"
                                                                        title="Input realisasi baru untuk kodering ini"
                                                                    >
                                                                        <Plus className="w-4 h-4" />
                                                                    </Link>
                                                                ) : (
                                                                    <span className="p-1 text-slate-400">
                                                                        <Lock className="w-4 h-4" />
                                                                    </span>
                                                                )}

                                                                {row.nominal_realisasi > 0 && !isReadOnly && (
                                                                    <Link
                                                                        href={`/pelaporan-bm/input-realisasi/edit?kodering=${encodeURIComponent(
                                                                            row.kodering
                                                                        )}&bulan_realisasi=${bulan}`}
                                                                        className="inline-flex items-center gap-1 px-2.5 py-1 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-xs font-bold transition shadow-sm"
                                                                        title="Ubah alokasi realisasi kodering ini"
                                                                    >
                                                                        <Pencil className="w-3.5 h-3.5" /> Edit
                                                                    </Link>
                                                                )}
                                                            </>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                            {daftarRekening.length > 0 && (
                                <tfoot className="bg-slate-50 border-t-2 border-slate-200 font-extrabold text-xs font-mono">
                                    <tr>
                                        <td className="p-3.5 text-slate-700 uppercase">Total Akumulasi:</td>
                                        <td className="p-3.5 text-right text-slate-700">{formatRupiah(totalAcuan)}</td>
                                        <td className="p-3.5 text-right text-blue-700">{formatRupiah(totalRealisasi)}</td>
                                        <td className={`p-3.5 text-right ${totalKekurangan <= 0 ? 'text-emerald-700' : 'text-rose-700'}`}>
                                            {formatRupiah(totalKekurangan)}
                                        </td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            )}
                        </table>
                    </div>
                </div>

                {/* Panel Status Pengajuan Laporan (Matching legacy input_realisasi.php lines 390-428) */}
                <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h3 className="font-bold text-slate-800 text-sm mb-1">
                            Status Pengajuan Laporan
                        </h3>
                        <p className="text-slate-500 text-xs">
                            Pastikan kekurangan bernilai Rp 0 pada semua kodering sebelum mengirimkan laporan ke Admin KCD.
                        </p>
                    </div>

                    <div>
                        {statusKirim === 'menunggu_approval' ? (
                            <div className="inline-flex items-center gap-2 px-4 py-2 bg-amber-50 border border-amber-200 text-amber-800 rounded-xl text-xs font-bold">
                                <Hourglass className="w-4 h-4 text-amber-600" />
                                <span>Menunggu di Approved Admin</span>
                            </div>
                        ) : statusKirim === 'disetujui' ? (
                            <div className="inline-flex items-center gap-2 px-4 py-2 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs font-bold">
                                <CheckCircle className="w-4 h-4 text-emerald-600" />
                                <span>Laporan Selesai & Disetujui</span>
                            </div>
                        ) : (
                            <form onSubmit={handleKirimLaporan}>
                                {isAllCompleted ? (
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-sm transition cursor-pointer"
                                    >
                                        <Send className="w-4 h-4" />
                                        <span>{processing ? 'Memproses...' : 'Kirim Laporan'}</span>
                                    </button>
                                ) : (
                                    <button
                                        type="button"
                                        disabled
                                        className="inline-flex items-center gap-2 px-5 py-2.5 bg-slate-100 text-slate-400 rounded-xl text-xs font-bold cursor-not-allowed border border-slate-200"
                                        title="Belum bisa kirim! Realisasi belum selesai semua."
                                    >
                                        <Lock className="w-4 h-4" />
                                        <span>Kirim Laporan (Readonly)</span>
                                    </button>
                                )}
                            </form>
                        )}
                    </div>
                </div>

                <ConfirmDialog
                    isOpen={showKirimConfirm}
                    onClose={() => setShowKirimConfirm(false)}
                    onConfirm={() => post('/pelaporan-bm/input-realisasi/kirim-laporan')}
                    title="Kirim Laporan"
                    message="Apakah Anda yakin ingin mengirimkan laporan realisasi bulan ini ke Admin KCD? Setelah dikirim data akan dikunci."
                    confirmText="Ya, Kirim"
                    isDestructive={false}
                />
            </div>
        </AppLayout>
    );
}
