import { Head, useForm, router, Link } from '@inertiajs/react';
import React, { useState, useEffect, useRef } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';
import { Database, Plus, Trash2, Search, Upload, Download, ArrowLeft, X, Info, AlertCircle } from 'lucide-react';

interface KodeBarangItem {
    id: string;
    kode_barang: string;
    uraian: string;
    kodering_aset: string | null;
    jenis_aset: string | null;
    umur_ekonomis: number;
    satuan: string | null;
    harga_standar: number;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    items: {
        data: KodeBarangItem[];
        links: PaginationLink[];
        total: number;
    };
    search: string;
}

export default function Index({ items, search }: Props) {
    const [showModal, setShowModal] = useState(false);
    const [searchVal, setSearchVal] = useState(search || '');
    const searchTimeout = useRef<ReturnType<typeof setTimeout> | null>(null);

    // Form Tambah Manual
    const { data, setData, post, processing, reset, errors } = useForm({
        kode_barang: '',
        uraian: '',
        kodering_aset: '',
        jenis_aset: 'Peralatan dan Mesin',
        umur_ekonomis: 5,
        satuan: 'Unit',
        harga_standar: 0,
    });

    // Form Import Excel / CSV
    const {
        data: importData,
        setData: setImportData,
        post: postImport,
        processing: importProcessing,
        reset: resetImport,
        errors: importErrors,
    } = useForm({
        file: null as File | null,
    });

    // Live search dengan debounce 250ms
    const handleSearchChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const val = e.target.value;
        setSearchVal(val);

        if (searchTimeout.current) {
            clearTimeout(searchTimeout.current);
        }

        searchTimeout.current = setTimeout(() => {
            router.get(
                '/admin/kode-barang',
                { search: val },
                { preserveState: true, replace: true }
            );
        }, 250);
    };

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (searchTimeout.current) {
            clearTimeout(searchTimeout.current);
        }
        router.get('/admin/kode-barang', { search: searchVal }, { preserveState: true });
    };

    const handleManualSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/kode-barang', {
            onSuccess: () => {
                setShowModal(false);
                reset();
            },
        });
    };

    const handleImportSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!importData.file) return;

        postImport('/admin/kode-barang/import', {
            forceFormData: true,
            onSuccess: () => {
                resetImport();
            },
        });
    };

    const handleDelete = (id: string) => {
        if (confirm('Yakin ingin menghapus master kode barang ini?')) {
            router.delete(`/admin/kode-barang/${id}`);
        }
    };

    return (
        <AppLayout title="Master Kode Barang">
            <Head title="Master Kode Barang | SINVENTARIS" />

            <div className="max-w-7xl mx-auto space-y-6">
                {/* Header disesuaikan dengan legacy kode_barang.php */}
                <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                    <div>
                        <div className="flex items-center gap-3">
                            <Database className="w-7 h-7 text-blue-600" />
                            <div>
                                <h1 className="text-2xl font-bold text-slate-800">
                                    Master Kode Barang
                                </h1>
                                <p className="text-xs text-slate-500 font-medium mt-0.5">
                                    Kelola dan import data referensi barang inventaris
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <button
                            onClick={() => setShowModal(true)}
                            className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-xl text-xs font-bold hover:bg-blue-700 transition shadow-sm cursor-pointer"
                        >
                            <Plus className="w-4 h-4" /> Tambah Kode
                        </button>
                        <Link
                            href="/dashboard"
                            className="inline-flex items-center gap-2 px-3.5 py-2 border border-slate-300 text-slate-700 rounded-xl text-xs font-semibold hover:bg-slate-50 transition"
                        >
                            <ArrowLeft className="w-4 h-4" /> Dashboard
                        </Link>
                    </div>
                </div>

                {/* Grid layout 2 kolom persis legacy: col-lg-4 & col-lg-8 */}
                <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    {/* Kolom Kiri: Upload Form & Pencarian Cepat */}
                    <div className="lg:col-span-4 space-y-6">
                        {/* Card Upload File */}
                        <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                            <h2 className="text-sm font-bold text-slate-800 flex items-center gap-2 mb-4">
                                <Upload className="w-4 h-4 text-blue-600" />
                                Upload File Excel / CSV
                            </h2>

                            <form onSubmit={handleImportSubmit} className="space-y-4">
                                <div>
                                    <input
                                        type="file"
                                        accept=".xlsx,.xls,.csv,.txt"
                                        onChange={(e) => setImportData('file', e.target.files ? e.target.files[0] : null)}
                                        className="w-full text-xs text-slate-600 file:mr-3 file:py-2 file:px-3.5 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 border border-slate-200 rounded-xl p-1.5 focus:outline-none"
                                        required
                                    />
                                    {importErrors.file && (
                                        <p className="text-red-500 text-xs mt-1">{importErrors.file}</p>
                                    )}
                                </div>

                                <button
                                    type="submit"
                                    disabled={importProcessing || !importData.file}
                                    className="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 text-white rounded-xl text-xs font-bold hover:bg-blue-700 transition shadow-sm cursor-pointer disabled:opacity-50"
                                >
                                    <Upload className="w-4 h-4" />
                                    {importProcessing ? 'Memproses Berkas...' : 'Import Sekarang'}
                                </button>
                            </form>

                            {/* Panduan Import Box */}
                            <div className="mt-4 p-3.5 bg-blue-50/70 border-l-4 border-blue-600 rounded-xl text-xs text-slate-700 space-y-2">
                                <div className="font-bold flex items-center gap-1.5 text-blue-900">
                                    <Info className="w-3.5 h-3.5 text-blue-600 shrink-0" />
                                    Panduan Import:
                                </div>
                                <ul className="list-disc pl-4 space-y-1 text-[11px] text-slate-600">
                                    <li>Gunakan file format <strong>.xlsx</strong> atau <strong>.csv</strong></li>
                                    <li>Baris pertama adalah <strong>Header</strong></li>
                                    <li>Sistem menggunakan <strong>Upsert</strong> (Jika kode sama, data akan diupdate)</li>
                                </ul>
                                <div className="pt-1">
                                    <a
                                        href="/templates/template_import_inventaris.xlsx"
                                        download
                                        className="inline-flex items-center gap-1 text-[11px] font-bold text-blue-700 hover:text-blue-900 underline"
                                    >
                                        <Download className="w-3 h-3" /> Unduh Template Master
                                    </a>
                                </div>
                            </div>
                        </div>

                        {/* Card Pencarian Cepat */}
                        <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                            <h2 className="text-sm font-bold text-slate-800 flex items-center gap-2 mb-3">
                                <Search className="w-4 h-4 text-emerald-600" />
                                Pencarian Cepat
                            </h2>
                            <form onSubmit={handleSearchSubmit}>
                                <div className="relative">
                                    <input
                                        type="text"
                                        value={searchVal}
                                        onChange={handleSearchChange}
                                        placeholder="Cari Kode atau Nama Barang..."
                                        className="w-full border border-slate-200 rounded-xl px-3.5 py-2.5 pl-9 text-xs font-medium outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    />
                                    <Search className="w-4 h-4 text-slate-400 absolute left-3 top-3 pointer-events-none" />
                                </div>
                            </form>
                            <p className="text-[11px] text-slate-400 mt-2">
                                Filter real-time berdasarkan kode, nama barang, kodering, atau jenis aset.
                            </p>
                        </div>
                    </div>

                    {/* Kolom Kanan: Daftar Referensi Kode */}
                    <div className="lg:col-span-8">
                        <div className="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                            {/* Card Header */}
                            <div className="p-4 border-b border-slate-100 flex justify-between items-center bg-white">
                                <h3 className="font-bold text-sm text-slate-800">
                                    Daftar Referensi Kode
                                </h3>
                                <div className="flex items-center gap-2">
                                    <span className="text-xs text-slate-500">
                                        Total: <strong className="text-slate-800">{items.total}</strong>
                                    </span>
                                    <span className="px-2.5 py-1 bg-blue-50 text-blue-700 rounded-full text-[10px] font-bold tracking-wide uppercase">
                                        Real-time Table
                                    </span>
                                </div>
                            </div>

                            {/* Table */}
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-xs">
                                    <thead className="bg-slate-50 text-slate-600 uppercase font-bold tracking-wider border-b border-slate-200 text-[11px]">
                                        <tr>
                                            <th className="p-3.5">Kode Barang</th>
                                            <th className="p-3.5">Nama Barang (Uraian)</th>
                                            <th className="p-3.5">Kodering Aset</th>
                                            <th className="p-3.5">Jenis Aset</th>
                                            <th className="p-3.5 text-center">Umur</th>
                                            <th className="p-3.5 text-center w-16">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {items.data.length === 0 ? (
                                            <tr>
                                                <td colSpan={6} className="p-8 text-center text-slate-500">
                                                    {searchVal ? (
                                                        <div className="flex flex-col items-center justify-center gap-1 text-red-600">
                                                            <AlertCircle className="w-5 h-5" />
                                                            <p className="font-medium">
                                                                Data &apos;{searchVal}&apos; tidak ditemukan
                                                            </p>
                                                        </div>
                                                    ) : (
                                                        'Belum ada data tersedia'
                                                    )}
                                                </td>
                                            </tr>
                                        ) : (
                                            items.data.map((item) => (
                                                <tr key={item.id} className="hover:bg-slate-50/80 transition">
                                                    <td className="p-3.5 font-mono font-bold text-blue-600">
                                                        {item.kode_barang}
                                                    </td>
                                                    <td className="p-3.5 text-slate-900 font-medium">
                                                        {item.uraian}
                                                    </td>
                                                    <td className="p-3.5">
                                                        {item.kodering_aset ? (
                                                            <span className="px-2 py-0.5 bg-slate-100 text-slate-700 border border-slate-200 rounded text-[11px] font-mono">
                                                                {item.kodering_aset}
                                                            </span>
                                                        ) : (
                                                            <span className="text-slate-400">-</span>
                                                        )}
                                                    </td>
                                                    <td className="p-3.5 text-slate-600">
                                                        {item.jenis_aset || '-'}
                                                    </td>
                                                    <td className="p-3.5 text-center font-mono text-slate-700">
                                                        {item.umur_ekonomis ?? 0}
                                                    </td>
                                                    <td className="p-3.5 text-center">
                                                        <button
                                                            onClick={() => handleDelete(item.id)}
                                                            className="p-1 text-slate-400 hover:text-red-600 rounded hover:bg-red-50 transition cursor-pointer"
                                                            title="Hapus"
                                                        >
                                                            <Trash2 className="w-3.5 h-3.5" />
                                                        </button>
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>

                            {/* Pagination Controls */}
                            {items.links && (
                                <div className="p-3 border-t border-slate-100 bg-white">
                                    <Pagination links={items.links} total={items.total} />
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {/* Modal Tambah Kode Barang Manual */}
            {showModal && (
                <div className="fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-50">
                    <div className="bg-white rounded-2xl shadow-xl max-w-md w-full p-6">
                        <div className="flex justify-between items-center mb-4 pb-2 border-b border-slate-100">
                            <h2 className="text-base font-bold text-slate-800 flex items-center gap-2">
                                <Plus className="w-4 h-4 text-blue-600" /> Tambah Kode Barang
                            </h2>
                            <button
                                onClick={() => setShowModal(false)}
                                className="text-slate-400 hover:text-slate-600 cursor-pointer"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>
                        <form onSubmit={handleManualSubmit} className="space-y-3">
                            <div>
                                <label className="block text-xs font-bold text-slate-600 uppercase mb-1">
                                    Kode Barang
                                </label>
                                <input
                                    type="text"
                                    value={data.kode_barang}
                                    onChange={(e) => setData('kode_barang', e.target.value)}
                                    placeholder="Contoh: 1.3.2.05.01.01.001"
                                    className="w-full border border-slate-200 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-blue-500 font-mono"
                                    required
                                />
                                {errors.kode_barang && <p className="text-red-500 text-xs mt-1">{errors.kode_barang}</p>}
                            </div>
                            <div>
                                <label className="block text-xs font-bold text-slate-600 uppercase mb-1">
                                    Nama Barang / Uraian
                                </label>
                                <input
                                    type="text"
                                    value={data.uraian}
                                    onChange={(e) => setData('uraian', e.target.value)}
                                    className="w-full border border-slate-200 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-blue-500"
                                    required
                                />
                                {errors.uraian && <p className="text-red-500 text-xs mt-1">{errors.uraian}</p>}
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-xs font-bold text-slate-600 uppercase mb-1">
                                        Kodering Aset
                                    </label>
                                    <input
                                        type="text"
                                        value={data.kodering_aset}
                                        onChange={(e) => setData('kodering_aset', e.target.value)}
                                        placeholder="Contoh: 5.2.02.01"
                                        className="w-full border border-slate-200 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-blue-500 font-mono"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-slate-600 uppercase mb-1">
                                        Umur Ekonomis (Thn)
                                    </label>
                                    <input
                                        type="number"
                                        min="0"
                                        value={data.umur_ekonomis}
                                        onChange={(e) => setData('umur_ekonomis', Number(e.target.value))}
                                        className="w-full border border-slate-200 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-blue-500 font-mono"
                                    />
                                </div>
                            </div>
                            <div>
                                <label className="block text-xs font-bold text-slate-600 uppercase mb-1">
                                    Jenis Aset
                                </label>
                                <select
                                    value={data.jenis_aset}
                                    onChange={(e) => setData('jenis_aset', e.target.value)}
                                    className="w-full border border-slate-200 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-blue-500 bg-white"
                                >
                                    <option value="Peralatan dan Mesin">Peralatan dan Mesin</option>
                                    <option value="Gedung dan Bangunan">Gedung dan Bangunan</option>
                                    <option value="Tanah">Tanah</option>
                                    <option value="Jalan, Irigasi dan Jaringan">Jalan, Irigasi dan Jaringan</option>
                                    <option value="Aset Tetap Lainnya">Aset Tetap Lainnya</option>
                                </select>
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-xs font-bold text-slate-600 uppercase mb-1">
                                        Satuan
                                    </label>
                                    <input
                                        type="text"
                                        value={data.satuan}
                                        onChange={(e) => setData('satuan', e.target.value)}
                                        placeholder="Unit/Pcs"
                                        className="w-full border border-slate-200 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-blue-500"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-slate-600 uppercase mb-1">
                                        Harga Standar (Rp)
                                    </label>
                                    <input
                                        type="number"
                                        min="0"
                                        value={data.harga_standar}
                                        onChange={(e) => setData('harga_standar', Number(e.target.value))}
                                        className="w-full border border-slate-200 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-blue-500 font-mono"
                                    />
                                </div>
                            </div>
                            <div className="flex justify-end gap-2 pt-3">
                                <button
                                    type="button"
                                    onClick={() => setShowModal(false)}
                                    className="px-4 py-2 border border-slate-200 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-50 cursor-pointer"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="px-4 py-2 bg-blue-600 text-white rounded-xl text-xs font-bold hover:bg-blue-700 cursor-pointer disabled:opacity-50"
                                >
                                    Simpan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
