import { Head, router, Link } from '@inertiajs/react';
import React, { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import {
    FileSpreadsheet,
    Plus,
    Pencil,
    CheckCircle2,
    Lock,
    ChevronDown,
    ChevronRight,
    Calendar,
    ArrowLeftRight,
} from 'lucide-react';
import { formatRupiah, BULAN_LIST, getNamaBulan } from '@/Utils/format';

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
    const isReadOnly = isLocked || statusKirim === 'menunggu_approval' || statusKirim === 'disetujui';

    const toggleExpand = (kodering: string) => {
        setExpandedKodering((prev) =>
            prev.includes(kodering) ? prev.filter((k) => k !== kodering) : [...prev, kodering]
        );
    };

    const handleMonthChange = (newMonth: number) => {
        router.visit(`/pelaporan-bm/input-realisasi?bulan_realisasi=${newMonth}`, {
            preserveState: false,
        });
    };

    return (
        <AppLayout title="Input Realisasi - Target Acuan Kerja">
            <Head title="Input Realisasi - Target Acuan Kerja" />

            <div className="space-y-6">
                {/* Header Card */}
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
                            Klik Kode Rekening untuk melihat rincian uraian pekerjaannya.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        <div className="flex items-center gap-2 bg-slate-50 border border-slate-200 px-3 py-1.5 rounded-xl text-xs font-bold text-slate-700">
                            <Calendar className="w-4 h-4 text-slate-500" />
                            <span>Bulan:</span>
                            <select
                                value={bulan}
                                onChange={(e) => handleMonthChange(Number(e.target.value))}
                                className="bg-transparent border-0 font-extrabold text-blue-600 cursor-pointer focus:outline-none text-xs"
                            >
                                {BULAN_LIST.map((nama, idx) => (
                                    <option key={idx + 1} value={idx + 1}>
                                        {nama}
                                    </option>
                                ))}
                            </select>
                        </div>

                        {isLocked && (
                            <span className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold rounded-xl">
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
                                            Tidak ada data target acuan kerja untuk bulan ini.
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
            </div>
        </AppLayout>
    );
}
