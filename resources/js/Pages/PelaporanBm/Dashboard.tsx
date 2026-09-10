import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import {
    Building2,
    CalendarCheck,
    CalendarDays,
    CheckCircle2,
    Coins,
    FileCheck,
    FileText,
    Filter,
    Package,
    Scale,
    XCircle,
} from 'lucide-react';
import React, { useState } from 'react';

interface SekolahItem {
    id: string;
    nama: string;
}

interface RekapBulan {
    bulan: number;
    bulan_nama: string;
    nilai_acuan: number;
    total_realisasi: number;
    total_aset: number;
    berkas_spk: number;
    status: string;
}

interface Props {
    isAdmin?: boolean;
    // Admin Props
    filterBulan?: number;
    filterTahun?: number;
    namaBulan?: string;
    years?: number[];
    totalTarget?: number;
    totalSelesai?: number;
    totalBelum?: number;
    listSelesai?: SekolahItem[];
    listBelum?: SekolahItem[];
    // Sekolah Props
    sekolah?: { id: string; nama_sekolah: string; kota_kab?: string | null };
    bulanSekarang?: number;
    bulanLapor?: number;
    namaBulanSekarang?: string;
    namaBulanLapor?: string;
    statusBulanLapor?: string;
    totalAcuan?: number;
    totalRealisasi?: number;
    totalAset?: number;
    totalSpk?: number;
    rekapBulanan?: RekapBulan[];
}

export default function Dashboard({
    isAdmin = false,
    filterBulan = new Date().getMonth() + 1,
    filterTahun = new Date().getFullYear(),
    namaBulan = 'Januari',
    years = [new Date().getFullYear()],
    totalTarget = 0,
    totalSelesai = 0,
    totalBelum = 0,
    listSelesai = [],
    listBelum = [],
    sekolah,
    bulanSekarang = new Date().getMonth() + 1,
    bulanLapor = new Date().getMonth() === 0 ? 12 : new Date().getMonth(),
    namaBulanSekarang = 'Januari',
    namaBulanLapor = 'Desember',
    statusBulanLapor = 'BELUM SELESAI',
    totalAcuan = 0,
    totalRealisasi = 0,
    totalAset = 0,
    totalSpk = 0,
    rekapBulanan = [],
}: Props) {
    const [selectedBulan, setSelectedBulan] = useState(filterBulan);
    const [selectedTahun, setSelectedTahun] = useState(filterTahun);

    const bulanOptions = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];

    const handleFilterSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/dashboard', {
            bulan: selectedBulan,
            tahun: selectedTahun,
        }, { preserveState: true });
    };

    const persenSelesai = totalTarget > 0 ? ((totalSelesai / totalTarget) * 100).toFixed(1) : '0';
    const persenBelum = totalTarget > 0 ? ((totalBelum / totalTarget) * 100).toFixed(1) : '0';

    return (
        <AppLayout title="Dashboard">
            <Head title={isAdmin ? 'Inventaris Barang | Dashboard' : 'SI DIPTA | Dashboard User'} />

            <div className="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto space-y-6">
                {isAdmin ? (
                    /* ========================================================
                       DASHBOARD ADMIN (Diselaraskan dengan legacy index_admin.php)
                       ======================================================== */
                    <>
                        {/* Filter Periode Bulan dan Tahun */}
                        <div className="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
                            <form onSubmit={handleFilterSubmit} className="grid grid-cols-1 sm:grid-cols-12 gap-4 items-end">
                                <div className="sm:col-span-5">
                                    <label className="block text-xs font-bold uppercase text-slate-500 mb-1.5 flex items-center gap-1.5">
                                        <CalendarDays className="w-3.5 h-3.5 text-blue-600" />
                                        Pilih Bulan Monitoring
                                    </label>
                                    <select
                                        value={selectedBulan}
                                        onChange={(e) => setSelectedBulan(Number(e.target.value))}
                                        className="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white"
                                    >
                                        {bulanOptions.map((name, idx) => (
                                            <option key={idx + 1} value={idx + 1}>
                                                {name}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div className="sm:col-span-4">
                                    <label className="block text-xs font-bold uppercase text-slate-500 mb-1.5 flex items-center gap-1.5">
                                        <CalendarCheck className="w-3.5 h-3.5 text-blue-600" />
                                        Tahun
                                    </label>
                                    <select
                                        value={selectedTahun}
                                        onChange={(e) => setSelectedTahun(Number(e.target.value))}
                                        className="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white"
                                    >
                                        {years.map((y) => (
                                            <option key={y} value={y}>
                                                {y}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div className="sm:col-span-3">
                                    <button
                                        type="submit"
                                        className="w-full inline-flex items-center justify-center gap-2 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-bold shadow-sm transition"
                                    >
                                        <Filter className="w-4 h-4" />
                                        <span>Tampilkan Informasi</span>
                                    </button>
                                </div>
                            </form>
                        </div>

                        {/* Cards Ringkasan Monitoring (3 Metrik) */}
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm flex flex-col justify-between">
                                <div className="flex items-center justify-between mb-3">
                                    <span className="text-xs font-bold uppercase text-slate-500 tracking-wider">
                                        Target Sekolah ({namaBulan})
                                    </span>
                                    <div className="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                                        <Building2 className="w-5 h-5" />
                                    </div>
                                </div>
                                <div className="text-3xl font-black text-slate-900 mb-1">
                                    {totalTarget}
                                </div>
                                <span className="text-xs text-slate-400 font-medium">
                                    Memiliki data acuan di bulan ini
                                </span>
                            </div>

                            <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm flex flex-col justify-between">
                                <div className="flex items-center justify-between mb-3">
                                    <span className="text-xs font-bold uppercase text-slate-500 tracking-wider">
                                        Sudah Selesai ({namaBulan})
                                    </span>
                                    <div className="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                                        <CheckCircle2 className="w-5 h-5" />
                                    </div>
                                </div>
                                <div className="text-3xl font-black text-emerald-600 mb-1">
                                    {totalSelesai}
                                </div>
                                <span className="text-xs text-emerald-600 font-bold">
                                    {persenSelesai}% Terlaporkan
                                </span>
                            </div>

                            <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm flex flex-col justify-between">
                                <div className="flex items-center justify-between mb-3">
                                    <span className="text-xs font-bold uppercase text-slate-500 tracking-wider">
                                        Belum Selesai ({namaBulan})
                                    </span>
                                    <div className="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center">
                                        <XCircle className="w-5 h-5" />
                                    </div>
                                </div>
                                <div className="text-3xl font-black text-rose-600 mb-1">
                                    {totalBelum}
                                </div>
                                <span className="text-xs text-rose-600 font-bold">
                                    {persenBelum}% Belum Realisasi
                                </span>
                            </div>
                        </div>

                        {/* Tabel Informasi Detail Sekolah (2 Kolom) */}
                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            {/* Kolom Kiri: Sudah Realisasi */}
                            <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
                                <div className="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-emerald-50/50">
                                    <div className="flex items-center gap-2 text-emerald-800 font-bold text-sm">
                                        <CheckCircle2 className="w-4 h-4 text-emerald-600" />
                                        <span>Sekolah Sudah Realisasi</span>
                                    </div>
                                    <span className="px-2.5 py-0.5 bg-emerald-600 text-white rounded-full text-xs font-extrabold">
                                        {totalSelesai} Sekolah
                                    </span>
                                </div>
                                <div className="max-h-[420px] overflow-y-auto divide-y divide-slate-100 flex-1">
                                    {listSelesai.length > 0 ? (
                                        <table className="w-full text-left text-xs">
                                            <thead className="bg-slate-50 text-slate-400 font-bold uppercase sticky top-0">
                                                <tr>
                                                    <th className="py-2.5 px-4 w-12 text-center">No</th>
                                                    <th className="py-2.5 px-4">Nama Sekolah</th>
                                                    <th className="py-2.5 px-4 text-right">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-slate-100">
                                                {listSelesai.map((sch, idx) => (
                                                    <tr key={sch.id} className="hover:bg-slate-50/80 transition">
                                                        <td className="py-3 px-4 text-center font-bold text-slate-500">{idx + 1}</td>
                                                        <td className="py-3 px-4 font-semibold text-slate-800">{sch.nama}</td>
                                                        <td className="py-3 px-4 text-right">
                                                            <span className="inline-flex items-center gap-1 px-2.5 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-lg text-[11px] font-bold">
                                                                <CheckCircle2 className="w-3 h-3" /> Selesai Realisasi
                                                            </span>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    ) : (
                                        <div className="py-12 text-center text-slate-400 text-xs">
                                            Belum ada sekolah target yang menyelesaikan realisasi di bulan {namaBulan} {filterTahun}.
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* Kolom Kanan: Belum Realisasi */}
                            <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
                                <div className="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-rose-50/50">
                                    <div className="flex items-center gap-2 text-rose-800 font-bold text-sm">
                                        <XCircle className="w-4 h-4 text-rose-600" />
                                        <span>Sekolah Belum Realisasi</span>
                                    </div>
                                    <span className="px-2.5 py-0.5 bg-rose-600 text-white rounded-full text-xs font-extrabold">
                                        {totalBelum} Sekolah
                                    </span>
                                </div>
                                <div className="max-h-[420px] overflow-y-auto divide-y divide-slate-100 flex-1">
                                    {listBelum.length > 0 ? (
                                        <table className="w-full text-left text-xs">
                                            <thead className="bg-slate-50 text-slate-400 font-bold uppercase sticky top-0">
                                                <tr>
                                                    <th className="py-2.5 px-4 w-12 text-center">No</th>
                                                    <th className="py-2.5 px-4">Nama Sekolah</th>
                                                    <th className="py-2.5 px-4 text-right">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-slate-100">
                                                {listBelum.map((sch, idx) => (
                                                    <tr key={sch.id} className="hover:bg-slate-50/80 transition">
                                                        <td className="py-3 px-4 text-center font-bold text-slate-500">{idx + 1}</td>
                                                        <td className="py-3 px-4 font-semibold text-slate-800">{sch.nama}</td>
                                                        <td className="py-3 px-4 text-right">
                                                            <span className="inline-flex items-center gap-1 px-2.5 py-1 bg-rose-50 text-rose-700 border border-rose-200 rounded-lg text-[11px] font-bold">
                                                                <XCircle className="w-3 h-3" /> Belum Ada Realisasi
                                                            </span>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    ) : (
                                        <div className="py-12 text-center text-slate-400 text-xs">
                                            Semua sekolah target telah menyelesaikan realisasi di bulan {namaBulan} {filterTahun}.
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    </>
                ) : (
                    /* ========================================================
                       DASHBOARD SEKOLAH (Diselaraskan dengan legacy index.php)
                       ======================================================== */
                    <>
                        {/* Banner Utama Dashboard Sekolah */}
                        <div className="rounded-2xl p-6 lg:p-8 bg-gradient-to-r from-[#1e3a8a] to-[#2563eb] text-white shadow-md flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                            <div>
                                <div className="flex flex-wrap items-center gap-2 mb-3">
                                    <span className="inline-flex items-center gap-1 px-3 py-1 bg-amber-400 text-slate-900 font-black text-xs rounded-lg uppercase">
                                        <CalendarCheck className="w-3.5 h-3.5" /> BULAN BERJALAN: {namaBulanSekarang.toUpperCase()}
                                    </span>
                                    <span className="px-3 py-1 bg-white/20 text-white font-bold text-xs rounded-lg uppercase">
                                        ID: {sekolah?.id || '-'}
                                    </span>
                                </div>
                                <h1 className="text-xl lg:text-2xl font-black uppercase tracking-tight text-white mb-1">
                                    DASHBOARD REKAPITULASI ASET SEKOLAH
                                </h1>
                                <p className="text-blue-100 text-sm font-semibold">
                                    {sekolah?.nama_sekolah || 'Satuan Pendidikan'}
                                </p>
                            </div>

                            {/* Panel Status Kanan Atas */}
                            <div className="bg-white/95 text-slate-800 rounded-xl p-4 text-left md:text-right shadow-sm border border-white/40 min-w-[240px]">
                                <div className="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">
                                    STATUS LAPORAN BULAN {namaBulanLapor.toUpperCase()}:
                                </div>
                                <span
                                    className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-black text-white shadow-sm ${
                                        statusBulanLapor === 'SELESAI' ? 'bg-emerald-600' : 'bg-rose-600'
                                    }`}
                                >
                                    {statusBulanLapor === 'SELESAI' ? (
                                        <CheckCircle2 className="w-4 h-4" />
                                    ) : (
                                        <XCircle className="w-4 h-4" />
                                    )}
                                    <span>STATUS {namaBulanLapor.toUpperCase()}: {statusBulanLapor}</span>
                                </span>
                            </div>
                        </div>

                        {/* 4 Metrik Utama Rekapitulasi Setahun */}
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                            <div className="bg-white rounded-2xl border-l-4 border-l-blue-600 border border-slate-200 p-5 shadow-sm">
                                <span className="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">
                                    TOTAL ACUAN ANGGARAN
                                </span>
                                <div className="text-xl lg:text-2xl font-black text-blue-600">
                                    Rp {Number(totalAcuan).toLocaleString('id-ID')}
                                </div>
                                <span className="block text-xs text-slate-500 font-medium mt-1">
                                    Target Anggaran 12 Bulan
                                </span>
                            </div>

                            <div className="bg-white rounded-2xl border-l-4 border-l-emerald-600 border border-slate-200 p-5 shadow-sm">
                                <span className="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">
                                    TOTAL REALISASI BELANJA
                                </span>
                                <div className="text-xl lg:text-2xl font-black text-emerald-600">
                                    Rp {Number(totalRealisasi).toLocaleString('id-ID')}
                                </div>
                                <span className="block text-xs text-slate-500 font-medium mt-1">
                                    Capaian Realisasi Aset
                                </span>
                            </div>

                            <div className="bg-white rounded-2xl border-l-4 border-l-amber-500 border border-slate-200 p-5 shadow-sm">
                                <span className="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">
                                    TOTAL FISIK ASET
                                </span>
                                <div className="text-xl lg:text-2xl font-black text-slate-900">
                                    {Number(totalAset).toLocaleString('id-ID')} <span className="text-sm font-normal text-slate-500">Item</span>
                                </div>
                                <span className="block text-xs text-slate-500 font-medium mt-1">
                                    Jumlah Rincian Barang
                                </span>
                            </div>

                            <div className="bg-white rounded-2xl border-l-4 border-l-cyan-600 border border-slate-200 p-5 shadow-sm">
                                <span className="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">
                                    TOTAL BERKAS SPK
                                </span>
                                <div className="text-xl lg:text-2xl font-black text-cyan-600">
                                    {Number(totalSpk).toLocaleString('id-ID')} <span className="text-sm font-normal text-slate-500">Dokumen</span>
                                </div>
                                <span className="block text-xs text-slate-500 font-medium mt-1">
                                    Dokumen Kontrak Terdata
                                </span>
                            </div>
                        </div>

                        {/* Tabel Detail Rincian Rekapitulasi Per Bulan (1 - 12) */}
                        <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden p-6 space-y-4">
                            <div>
                                <h3 className="text-base font-bold text-slate-900 flex items-center gap-2">
                                    <Coins className="w-5 h-5 text-blue-600" />
                                    RINCIAN PERKEMBANGAN REALISASI TIAP BULAN
                                </h3>
                                <p className="text-xs text-slate-500">
                                    Rangkuman Nilai Acuan, Realisasi Belanja, Jumlah Aset, dan Berkas SPK per Periode Bulan
                                </p>
                            </div>

                            <div className="overflow-x-auto rounded-xl border border-slate-200">
                                <table className="w-full text-xs text-left">
                                    <thead className="bg-slate-100 text-slate-700 font-extrabold text-center uppercase tracking-wider border-b border-slate-200">
                                        <tr>
                                            <th className="py-3 px-3 w-12">NO</th>
                                            <th className="py-3 px-4 text-left">PERIODE BULAN</th>
                                            <th className="py-3 px-4 text-right">NILAI ACUAN</th>
                                            <th className="py-3 px-4 text-right">TOTAL REALISASI</th>
                                            <th className="py-3 px-4 text-center">TOTAL ASET</th>
                                            <th className="py-3 px-4 text-center">BERKAS SPK</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-200">
                                        {rekapBulanan.map((m) => {
                                            const isBulanSekarang = m.bulan === bulanSekarang;
                                            const isBulanLapor = m.bulan === bulanLapor;

                                            let rowBg = 'hover:bg-slate-50/80';
                                            if (isBulanSekarang) rowBg = 'bg-blue-50/70 hover:bg-blue-50';
                                            else if (isBulanLapor) rowBg = 'bg-amber-50/70 hover:bg-amber-50';

                                            return (
                                                <tr key={m.bulan} className={`transition ${rowBg}`}>
                                                    <td className="py-3 px-3 text-center font-bold text-slate-600">
                                                        {m.bulan}
                                                    </td>
                                                    <td className="py-3 px-4 font-semibold text-slate-800">
                                                        <span>{m.bulan_nama}</span>
                                                        {isBulanSekarang && (
                                                            <span className="ml-2 px-2 py-0.5 bg-blue-600 text-white rounded text-[10px] font-bold">
                                                                Bulan Berjalan
                                                            </span>
                                                        )}
                                                        {isBulanLapor && (
                                                            <span className="ml-2 px-2 py-0.5 bg-amber-400 text-slate-900 rounded text-[10px] font-bold">
                                                                Bulan Lapor
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="py-3 px-4 text-right font-medium text-slate-600">
                                                        Rp {Number(m.nilai_acuan).toLocaleString('id-ID')}
                                                    </td>
                                                    <td className="py-3 px-4 text-right font-bold text-blue-600">
                                                        Rp {Number(m.total_realisasi).toLocaleString('id-ID')}
                                                    </td>
                                                    <td className="py-3 px-4 text-center">
                                                        <span className="px-2.5 py-1 bg-white border border-slate-200 rounded-lg font-bold text-slate-700">
                                                            {Number(m.total_aset).toLocaleString('id-ID')} Item
                                                        </span>
                                                    </td>
                                                    <td className="py-3 px-4 text-center">
                                                        <span className="px-2.5 py-1 bg-white border border-blue-200 text-blue-700 rounded-lg font-bold">
                                                            {Number(m.berkas_spk).toLocaleString('id-ID')} Dokumen
                                                        </span>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                    <tfoot className="bg-slate-100 font-black border-t-2 border-slate-300">
                                        <tr>
                                            <td colSpan={2} className="py-3 px-4 text-center uppercase tracking-wide">
                                                TOTAL TAHUNAN
                                            </td>
                                            <td className="py-3 px-4 text-right text-slate-700">
                                                Rp {Number(totalAcuan).toLocaleString('id-ID')}
                                            </td>
                                            <td className="py-3 px-4 text-right text-blue-700">
                                                Rp {Number(totalRealisasi).toLocaleString('id-ID')}
                                            </td>
                                            <td className="py-3 px-4 text-center">
                                                {Number(totalAset).toLocaleString('id-ID')} Item
                                            </td>
                                            <td className="py-3 px-4 text-center">
                                                {Number(totalSpk).toLocaleString('id-ID')} Dokumen
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
