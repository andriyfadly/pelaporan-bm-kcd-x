import { Head, Link, router } from '@inertiajs/react';
import React, { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';
import {
    Table as TableIcon,
    Filter,
    Download,
    FileSpreadsheet,
    RotateCcw,
    Coins,
    FolderX,
} from 'lucide-react';

interface RealisasiItem {
    id: string;
    no_sp2d?: string;
    sumber_perolehan?: string;
    no_spk?: string;
    ba_no?: string;
    ba_tgl?: string;
    bulan_realisasi: number;
    kode_barang: string;
    nama_barang: string;
    jenis_aset?: string;
    merk_tipe?: string;
    no_sertifikat?: string;
    ukuran_bangunan?: string;
    satuan?: string;
    volume: number;
    harga_satuan: number;
    nilai_perolehan: number;
    sekolah?: {
        nama_sekolah: string;
    };
    acuan?: {
        kodering: string;
    };
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedData {
    data: RealisasiItem[];
    links: PaginationLink[];
    total: number;
    from: number;
    to: number;
}

interface Props {
    items: PaginatedData;
    filters: {
        filter_barang: string;
        filter_bulan: number | null;
        filter_tahun: number | null;
    };
    totalNilaiPerolehan: number;
    availableYears: number[];
}

export default function Index({ items, filters, totalNilaiPerolehan, availableYears }: Props) {
    const [filterBarang, setFilterBarang] = useState(filters.filter_barang || '');
    const [filterBulan, setFilterBulan] = useState(filters.filter_bulan ? String(filters.filter_bulan) : '');
    const [filterTahun, setFilterTahun] = useState(filters.filter_tahun ? String(filters.filter_tahun) : '');

    const bulanNames = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];

    const handleFilter = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/pelaporan-bm/realisasi', {
            filter_barang: filterBarang,
            filter_bulan: filterBulan,
            filter_tahun: filterTahun,
        }, {
            preserveState: true,
            replace: true,
        });
    };

    const handleReset = () => {
        setFilterBarang('');
        setFilterBulan('');
        setFilterTahun('');
        router.get('/pelaporan-bm/realisasi');
    };

    const unduhUrl = `/pelaporan-bm/realisasi/unduh?${new URLSearchParams({
        filter_barang: filterBarang,
        filter_bulan: filterBulan,
        filter_tahun: filterTahun,
    }).toString()}`;

    return (
        <AppLayout title="Data Realisasi Belanja Modal">
            <Head title="Data Realisasi Belanja Modal" />

            {/* Total Nilai Perolehan Card */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div className="bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-[20px] p-6 shadow-md">
                    <div className="flex items-center justify-between">
                        <div>
                            <span className="text-xs text-blue-100 font-bold uppercase tracking-wider block mb-1">
                                Total Nilai Perolehan (Tersaring)
                            </span>
                            <h2 className="text-2xl lg:text-3xl font-black">
                                Rp {Number(totalNilaiPerolehan).toLocaleString('id-ID')}
                            </h2>
                            <p className="text-xs text-blue-200 mt-1">
                                {items.total} baris aset terdata
                            </p>
                        </div>
                        <div className="p-3 bg-white/20 rounded-2xl">
                            <Coins className="w-8 h-8 text-white" />
                        </div>
                    </div>
                </div>
            </div>

            {/* Filter Box */}
            <div className="bg-white border border-slate-200 rounded-[20px] p-5 mb-6 shadow-sm">
                <h3 className="text-sm font-bold text-slate-800 mb-4 flex items-center gap-2">
                    <Filter className="w-4 h-4 text-blue-600" /> Filter Pencarian Realisasi
                </h3>
                <form onSubmit={handleFilter} className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-end">
                    <div className="lg:col-span-4">
                        <label className="text-xs font-semibold text-slate-500 mb-1 block">
                            Nama / Kode Barang
                        </label>
                        <input
                            type="text"
                            value={filterBarang}
                            onChange={(e) => setFilterBarang(e.target.value)}
                            placeholder="Ketik nama atau kode barang..."
                            className="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                    <div className="lg:col-span-2">
                        <label className="text-xs font-semibold text-slate-500 mb-1 block">
                            Bulan Realisasi
                        </label>
                        <select
                            value={filterBulan}
                            onChange={(e) => setFilterBulan(e.target.value)}
                            className="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">-- Semua Bulan --</option>
                            {bulanNames.map((nama, idx) => (
                                <option key={idx + 1} value={idx + 1}>
                                    {nama}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="lg:col-span-2">
                        <label className="text-xs font-semibold text-slate-500 mb-1 block">
                            Tahun
                        </label>
                        <select
                            value={filterTahun}
                            onChange={(e) => setFilterTahun(e.target.value)}
                            className="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">-- Semua Tahun --</option>
                            {availableYears.map((yr) => (
                                <option key={yr} value={yr}>
                                    {yr}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="lg:col-span-4 flex flex-wrap items-center gap-2">
                        <button
                            type="submit"
                            className="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-bold shadow-sm transition"
                        >
                            <Filter className="w-4 h-4" /> Cari
                        </button>
                        <a
                            href={unduhUrl}
                            className="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-sm font-bold shadow-sm transition"
                            title="Download CSV/Excel Sesuai Filter"
                        >
                            <Download className="w-4 h-4" /> Laporan
                        </a>
                        <a
                            href="/templates/template_import_inventaris.xlsx"
                            className="inline-flex items-center gap-1.5 px-3 py-2 bg-sky-500 hover:bg-sky-600 text-white rounded-xl text-sm font-bold shadow-sm transition"
                            title="Download Template Isian Excel"
                        >
                            <FileSpreadsheet className="w-4 h-4" /> Template
                        </a>
                        <button
                            type="button"
                            onClick={handleReset}
                            className="p-2 border border-slate-200 hover:bg-slate-50 text-slate-600 rounded-xl transition"
                            title="Reset Filter"
                        >
                            <RotateCcw className="w-4 h-4" />
                        </button>
                    </div>
                </form>
            </div>

            {/* Table */}
            <div className="bg-white border border-slate-200 rounded-[20px] p-5 shadow-sm">
                <div className="flex items-center justify-between mb-4">
                    <h4 className="font-bold text-slate-800 text-base flex items-center gap-2">
                        <TableIcon className="w-5 h-5 text-blue-600" /> Data Realisasi Belanja Modal
                    </h4>
                    <span className="text-xs bg-slate-100 text-slate-600 px-3 py-1 rounded-full font-bold">
                        Total: {items.total} Baris
                    </span>
                </div>

                <div className="overflow-x-auto border border-slate-200 rounded-xl">
                    <table className="w-full text-xs text-left text-slate-700 border-collapse">
                        <thead className="bg-[#f1f5f9] text-slate-700 font-bold uppercase tracking-wider text-[11px] border-b border-slate-200">
                            <tr>
                                <th rowSpan={2} className="p-3 border-r border-slate-200 text-center w-10">No</th>
                                <th rowSpan={2} className="p-3 border-r border-slate-200">No. SP2D</th>
                                <th rowSpan={2} className="p-3 border-r border-slate-200">Sumber Perolehan</th>
                                <th rowSpan={2} className="p-3 border-r border-slate-200">Kodering Belanja</th>
                                <th rowSpan={2} className="p-3 border-r border-slate-200">No. SPK / Faktur</th>
                                <th colSpan={4} className="p-2 border-r border-b border-slate-200 text-center">BA Penerimaan</th>
                                <th rowSpan={2} className="p-3 border-r border-slate-200 text-center">Bln Realisasi</th>
                                <th rowSpan={2} className="p-3 border-r border-slate-200">Kode Barang</th>
                                <th colSpan={5} className="p-2 border-r border-b border-slate-200 text-center">Rincian Barang</th>
                                <th rowSpan={2} className="p-3 text-right">Nilai Perolehan</th>
                            </tr>
                            <tr className="bg-[#e2e8f0]/60">
                                <th className="p-2 border-r border-slate-200">No</th>
                                <th className="p-2 border-r border-slate-200 text-center">Tgl</th>
                                <th className="p-2 border-r border-slate-200 text-center">Bln</th>
                                <th className="p-2 border-r border-slate-200 text-center">Thn</th>
                                <th className="p-2 border-r border-slate-200">Nama Barang</th>
                                <th className="p-2 border-r border-slate-200">Merk / Tipe</th>
                                <th className="p-2 border-r border-slate-200 text-center">Satuan</th>
                                <th className="p-2 border-r border-slate-200 text-center">Volume</th>
                                <th className="p-2 border-r border-slate-200 text-right">Harga Satuan</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {items.data.length === 0 ? (
                                <tr>
                                    <td colSpan={17} className="text-center py-12 text-slate-400">
                                        <FolderX className="w-10 h-10 mx-auto mb-2 text-slate-300" />
                                        <p className="font-semibold text-slate-500">
                                            Tidak ditemukan data realisasi belanja modal.
                                        </p>
                                    </td>
                                </tr>
                            ) : (
                                items.data.map((row, idx) => {
                                    const tgl = row.ba_tgl ? new Date(row.ba_tgl).getDate() : '-';
                                    const bln = row.ba_tgl ? new Date(row.ba_tgl).getMonth() + 1 : '-';
                                    const thn = row.ba_tgl ? new Date(row.ba_tgl).getFullYear() : '-';
                                    const rowNum = (items.from || 1) + idx;

                                    return (
                                        <tr key={row.id} className="hover:bg-slate-50 transition">
                                            <td className="p-3 text-center border-r border-slate-100 font-bold text-slate-500">
                                                {rowNum}
                                            </td>
                                            <td className="p-3 border-r border-slate-100 font-medium">
                                                {row.no_sp2d || '-'}
                                            </td>
                                            <td className="p-3 border-r border-slate-100">
                                                {row.sumber_perolehan || '-'}
                                            </td>
                                            <td className="p-3 border-r border-slate-100 font-mono text-[11px]">
                                                {row.acuan?.kodering || '-'}
                                            </td>
                                            <td className="p-3 border-r border-slate-100 font-mono text-[11px] text-blue-600 font-medium">
                                                {row.no_spk || '-'}
                                            </td>
                                            <td className="p-3 border-r border-slate-100">
                                                {row.ba_no || '-'}
                                            </td>
                                            <td className="p-3 border-r border-slate-100 text-center">
                                                {tgl}
                                            </td>
                                            <td className="p-3 border-r border-slate-100 text-center">
                                                {bln}
                                            </td>
                                            <td className="p-3 border-r border-slate-100 text-center">
                                                {thn}
                                            </td>
                                            <td className="p-3 border-r border-slate-100 text-center font-bold text-sky-600">
                                                {bulanNames[row.bulan_realisasi - 1] || row.bulan_realisasi}
                                            </td>
                                            <td className="p-3 border-r border-slate-100 font-mono text-[11px]">
                                                {row.kode_barang}
                                            </td>
                                            <td className="p-3 border-r border-slate-100 font-bold text-slate-800">
                                                {row.nama_barang}
                                            </td>
                                            <td className="p-3 border-r border-slate-100 text-slate-600">
                                                {row.merk_tipe || '-'}
                                            </td>
                                            <td className="p-3 border-r border-slate-100 text-center">
                                                {row.satuan || '-'}
                                            </td>
                                            <td className="p-3 border-r border-slate-100 text-center font-bold">
                                                {Number(row.volume).toLocaleString('id-ID')}
                                            </td>
                                            <td className="p-3 border-r border-slate-100 text-right font-mono">
                                                Rp {Number(row.harga_satuan).toLocaleString('id-ID')}
                                            </td>
                                            <td className="p-3 text-right font-mono font-bold text-[#0284c7] bg-slate-50/50">
                                                Rp {Number(row.nilai_perolehan).toLocaleString('id-ID')}
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                {items.links && (
                    <div className="mt-4 pt-4 border-t border-slate-100">
                        <Pagination links={items.links} total={items.total} />
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
