import { Head, useForm, router, usePage } from '@inertiajs/react';
import React, { useState, useRef } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';
import Modal from '@/Components/Modal';
import ConfirmDialog from '@/Components/ConfirmDialog';
import SearchInput from '@/Components/SearchInput';
import EmptyState from '@/Components/EmptyState';
import { BULAN_LIST, getNamaBulan } from '@/Utils/format';
import {
    Plus,
    Trash2,
    Download,
    Upload,
    FileSpreadsheet,
    Building2,
    Coins,
    Calendar,
    AlertTriangle,
} from 'lucide-react';

interface AcuanItem {
    id: string;
    tanggal: string;
    kodering: string | null;
    bku: string | null;
    uraian: string;
    nominal: number;
    bulan: number;
    sekolah?: {
        id: string;
        nama_sekolah: string;
        npsn?: string | null;
    };
}

interface Sekolah {
    id: string;
    nama_sekolah: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    items: {
        data: AcuanItem[];
        links: PaginationLink[];
        total: number;
        from?: number;
        to?: number;
    };
    totalNominal: number;
    totalSekolah: number;
    filterBulan: number | string;
    searchSatuan: string;
    listBulan: number[];
    sekolahs: Sekolah[];
}

export default function Index({
    items,
    totalNominal = 0,
    totalSekolah = 0,
    filterBulan = '',
    searchSatuan = '',
    listBulan = [],
    sekolahs = [],
}: Props) {
    const { auth } = usePage<{ auth?: { user?: { roles?: string[]; sekolah_id?: string | null } } }>().props;
    const user = auth?.user;
    const isAdmin = user?.roles?.includes('admin_kcd') || !user?.sekolah_id;
    const roleName = isAdmin ? 'Admin' : 'User';

    const [showModal, setShowModal] = useState(false);
    const [confirmTarget, setConfirmTarget] = useState<AcuanItem | null>(null);
    const [showKosongkan, setShowKosongkan] = useState(false);
    const [kosongkanMessage, setKosongkanMessage] = useState('');
    const [importError, setImportError] = useState('');
    const [suggestions, setSuggestions] = useState<{ kode_barang: string; nama_barang: string }[]>([]);
    const searchTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const [searchInput, setSearchInput] = useState(searchSatuan);
    const filterTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    // Form Import
    const {
        data: importData,
        setData: setImportData,
        post: postImport,
        processing: importProcessing,
        reset: resetImport,
        errors: importErrors,
    } = useForm({
        file: null as File | null,
        bulan: filterBulan || (new Date().getMonth() === 0 ? 12 : new Date().getMonth()),
    });

    // Form Manual Tambah
    const {
        data,
        setData,
        post,
        processing,
        reset,
        errors,
    } = useForm({
        sekolah_id: '',
        tanggal: new Date().toISOString().split('T')[0],
        kodering: '',
        bku: '',
        uraian: '',
        nominal: 0,
        bulan: filterBulan || (new Date().getMonth() === 0 ? 12 : new Date().getMonth()),
    });

    // Filter handlers
    const handleSearchChange = (val: string) => {
        setSearchInput(val);
        if (filterTimeoutRef.current) clearTimeout(filterTimeoutRef.current);
        filterTimeoutRef.current = setTimeout(() => {
            router.get(
                '/pelaporan-bm/acuan',
                {
                    search_satuan: val,
                    bulan: filterBulan,
                },
                { preserveState: true, replace: true }
            );
        }, 300);
    };

    const handleBulanChange = (bln: string) => {
        router.get(
            '/pelaporan-bm/acuan',
            {
                search_satuan: searchInput,
                bulan: bln,
            },
            { preserveState: true }
        );
    };

    const handleSearchKodering = (q: string) => {
        setData('kodering', q);
        if (searchTimeoutRef.current) clearTimeout(searchTimeoutRef.current);
        if (q.length > 1) {
            searchTimeoutRef.current = setTimeout(() => {
                fetch(`/pelaporan-bm/cari-barang?q=${encodeURIComponent(q)}`)
                    .then((r) => r.json())
                    .then((d) => setSuggestions(d));
            }, 250);
        } else {
            setSuggestions([]);
        }
    };

    const handleSelectBarang = (item: { kode_barang: string; nama_barang: string }) => {
        setData((prev) => ({
            ...prev,
            kodering: item.kode_barang,
            uraian: prev.uraian ? prev.uraian : item.nama_barang,
        }));
        setSuggestions([]);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/pelaporan-bm/acuan', {
            onSuccess: () => {
                setShowModal(false);
                reset();
            },
        });
    };

    const handleImportSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!importData.file) {
            setImportError('Silakan pilih berkas template terlebih dahulu.');
            return;
        }
        setImportError('');
        postImport('/pelaporan-bm/acuan/import', {
            forceFormData: true,
            onSuccess: () => {
                resetImport();
            },
        });
    };

    const handleDelete = () => {
        if (!confirmTarget) return;
        router.delete(`/pelaporan-bm/acuan/${confirmTarget.id}`, { preserveScroll: true });
        setConfirmTarget(null);
    };

    const handleKosongkanSemua = () => {
        const teksBulan = filterBulan ? `bulan "${getNamaBulan(Number(filterBulan))}"` : 'SEMUA BULAN';
        setKosongkanMessage(`Menghapus SELURUH data acuan untuk ${teksBulan}. Data yang dihapus tidak bisa dikembalikan.`);
        setShowKosongkan(true);
    };

    const handleKosongkanConfirm = () => {
        router.post('/pelaporan-bm/acuan/destroy-all', {
            bulan: filterBulan,
        });
        setShowKosongkan(false);
        setKosongkanMessage('');
    };

    return (
        <AppLayout title="Input Acuan">
            <Head title="Master Barang Acuan" />

            <div className="space-y-6">
                {/* 1. KARTU IMPORT MASTER BARANG ACUAN (Sama dengan Legacy) */}
                <div className="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
                    <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-5">
                        <div>
                            <h2 className="text-lg font-bold text-slate-900 flex items-center gap-2">
                                <FileSpreadsheet className="w-6 h-6 text-emerald-600" />
                                Import Master Barang Acuan
                            </h2>
                            <p className="text-xs text-slate-500 mt-0.5">
                                Unggah data acuan belanja modal menggunakan format template resmi.
                            </p>
                        </div>
                        <a
                            href="/templates/template_import_vendor_acuan.xlsx"
                            download
                            className="inline-flex items-center gap-2 px-4 py-2 bg-blue-50 text-[#2563eb] border border-blue-200 rounded-xl text-xs font-bold hover:bg-[#2563eb] hover:text-white transition shadow-sm"
                        >
                            <Download className="w-4 h-4" /> Download Template XLSX
                        </a>
                    </div>

                    <form onSubmit={handleImportSubmit}>
                        <div className="border-2 border-dashed border-blue-200 bg-slate-50 hover:bg-blue-50/40 rounded-xl p-6 text-center transition mb-4">
                            <Upload className="w-10 h-10 text-[#2563eb] mx-auto mb-2" />
                            <h3 className="text-sm font-semibold text-slate-800 mb-0.5">
                                Pilih File Template Anda
                            </h3>
                            <p className="text-[11px] text-slate-500 mb-3">
                                Mendukung berkas .xlsx atau .xls dari template resmi
                            </p>
                            <div className="flex justify-center">
                                <input
                                    type="file"
                                    accept=".xlsx,.xls"
                                    onChange={(e) => setImportData('file', e.target.files?.[0] || null)}
                                    className="block w-full max-w-sm text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-[#2563eb] hover:file:bg-blue-100 cursor-pointer"
                                />
                            </div>
                            {importErrors.file && (
                                <p className="text-red-600 text-xs mt-2">{importErrors.file}</p>
                            )}
                            {importError && (
                                <p className="text-red-600 text-xs mt-2" role="alert">{importError}</p>
                            )}
                        </div>

                        <div className="flex justify-end">
                            <button
                                type="submit"
                                disabled={importProcessing}
                                className="inline-flex items-center gap-2 px-5 py-2.5 bg-[#2563eb] text-white rounded-xl text-xs font-bold hover:bg-blue-700 transition shadow-sm disabled:opacity-50 cursor-pointer"
                            >
                                <Upload className="w-4 h-4" />
                                {importProcessing ? 'Memproses...' : 'Proses Import Data'}
                            </button>
                        </div>
                    </form>
                </div>

                {/* 2. KARTU DATA ACUAN AKTIF (Sama dengan Legacy) */}
                <div className="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
                    <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-4">
                        <div className="flex items-center gap-3">
                            <h2 className="text-base font-bold text-slate-900 flex items-center gap-2">
                                <FileSpreadsheet className="w-5 h-5 text-[#2563eb]" />
                                Data Acuan Aktif (Role: {roleName})
                            </h2>
                        </div>
                        <div className="flex items-center gap-2">
                            <button
                                type="button"
                                onClick={() => setShowModal(true)}
                                className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-50 text-[#2563eb] border border-blue-200 rounded-lg text-xs font-semibold hover:bg-[#2563eb] hover:text-white transition cursor-pointer"
                            >
                                <Plus className="w-3.5 h-3.5" /> Tambah Manual
                            </button>
                            <button
                                type="button"
                                onClick={handleKosongkanSemua}
                                className="inline-flex items-center gap-1.5 px-3 py-1.5 text-red-600 border border-red-200 rounded-lg text-xs font-semibold hover:bg-red-50 transition cursor-pointer"
                            >
                                <Trash2 className="w-3.5 h-3.5" /> Kosongkan Semua Data Acuan
                            </button>
                        </div>
                    </div>

                    {/* Widgets Summary */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div className="bg-gradient-to-br from-blue-50 to-blue-100/60 border border-blue-200 rounded-xl p-4 flex items-center justify-between">
                            <div>
                                <span className="text-[11px] font-bold text-blue-700 uppercase tracking-wider flex items-center gap-1.5">
                                    <Coins className="w-3.5 h-3.5 text-[#2563eb]" />
                                    Total Akumulasi Nominal Acuan
                                </span>
                                <h3 className="text-2xl font-bold text-[#2563eb] mt-1">
                                    Rp {Number(totalNominal).toLocaleString('id-ID')}
                                </h3>
                            </div>
                        </div>
                        <div className="bg-gradient-to-br from-emerald-50 to-emerald-100/60 border border-emerald-200 rounded-xl p-4 flex items-center justify-between">
                            <div>
                                <span className="text-[11px] font-bold text-emerald-700 uppercase tracking-wider flex items-center gap-1.5">
                                    <Building2 className="w-3.5 h-3.5 text-emerald-600" />
                                    Total Sekolah Terealisasi
                                </span>
                                <h3 className="text-2xl font-bold text-emerald-700 mt-1">
                                    {Number(totalSekolah).toLocaleString('id-ID')} Sekolah
                                </h3>
                            </div>
                            <span className="px-2.5 py-1 bg-emerald-100 text-emerald-800 text-[10px] font-bold rounded-full">
                                Real-time
                            </span>
                        </div>
                    </div>

                    {/* Filter & Search Box */}
                    <div className="bg-slate-50 border border-slate-200 rounded-xl p-4 mb-4">
                        <div className="grid grid-cols-1 md:grid-cols-12 gap-3">
                            <div className="md:col-span-7">
                                <label className="block text-[11px] font-bold text-slate-500 uppercase mb-1">
                                    Cari Satuan Pendidikan
                                </label>
                                <SearchInput
                                    value={searchInput}
                                    onChange={handleSearchChange}
                                    placeholder="Ketik nama sekolah vendor... (Contoh: SMKN 1 KUNINGAN)"
                                />
                            </div>
                            <div className="md:col-span-5">
                                <label className="block text-[11px] font-bold text-slate-500 uppercase mb-1">
                                    Filter Berdasarkan Bulan
                                </label>
                                <div className="relative">
                                    <Calendar className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                                    <select
                                        value={filterBulan}
                                        onChange={(e) => handleBulanChange(e.target.value)}
                                        className="w-full pl-9 pr-3 py-2 bg-white border border-slate-200 rounded-lg text-xs font-semibold focus:ring-2 focus:ring-blue-500 focus:outline-none cursor-pointer"
                                    >
                                        <option value="">-- Semua Bulan --</option>
                                        {BULAN_LIST.map((name, i) => (
                                            <option key={i + 1} value={i + 1}>
                                                {name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Table View */}
                    <div className="overflow-x-auto border border-slate-200 rounded-xl">
                        <table className="w-full min-w-[1024px] text-left text-xs border-collapse">
                            <thead>
                                <tr className="bg-blue-50 text-[#1e3a8a] border-b-2 border-blue-100 font-bold uppercase text-[11px]">
                                    <th className="py-3 px-3 text-center w-12">No</th>
                                    <th className="py-3 px-3">Satuan Pendidikan</th>
                                    <th className="py-3 px-3 text-center">NPSN</th>
                                    <th className="py-3 px-3 text-center">Tanggal</th>
                                    <th className="py-3 px-3 text-center">Kodering</th>
                                    <th className="py-3 px-3">BKU</th>
                                    <th className="py-3 px-3">Uraian Barang</th>
                                    <th className="py-3 px-3 text-end">Nominal</th>
                                    <th className="py-3 px-3 text-center">Bulan</th>
                                    <th className="py-3 px-3 text-center w-16">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 bg-white">
                                {items.data && items.data.length > 0 ? (
                                    items.data.map((item, index) => (
                                        <tr key={item.id} className="hover:bg-slate-50 transition">
                                            <td className="py-3 px-3 text-center text-slate-500 font-medium">
                                                {(items.from || 1) + index}
                                            </td>
                                            <td className="py-3 px-3 font-semibold text-slate-800">
                                                {item.sekolah?.nama_sekolah || '-'}
                                            </td>
                                            <td className="py-3 px-3 text-center text-slate-600 font-mono">
                                                {item.sekolah?.npsn || '-'}
                                            </td>
                                            <td className="py-3 px-3 text-center text-slate-600 font-mono whitespace-nowrap">
                                                {item.tanggal}
                                            </td>
                                            <td className="py-3 px-3 text-center text-blue-600 font-mono font-bold">
                                                {item.kodering || '-'}
                                            </td>
                                            <td className="py-3 px-3 text-slate-600 font-mono">
                                                {item.bku || '-'}
                                            </td>
                                            <td className="py-3 px-3 text-slate-700 max-w-xs truncate" title={item.uraian}>
                                                {item.uraian}
                                            </td>
                                            <td className="py-3 px-3 text-end font-bold text-[#2563eb] whitespace-nowrap">
                                                Rp {Number(item.nominal).toLocaleString('id-ID')}
                                            </td>
                                            <td className="py-3 px-3 text-center text-slate-600 font-semibold">
                                                {getNamaBulan(item.bulan)}
                                            </td>
                                            <td className="py-3 px-3 text-center">
                                                <button
                                                    onClick={() => setConfirmTarget(item)}
                                                    aria-label={`Hapus acuan ${item.uraian}`}
                                                    className="p-1.5 text-red-500 hover:text-red-700 hover:bg-red-50 rounded-lg transition cursor-pointer"
                                                    title="Hapus Baris Ini"
                                                >
                                                    <Trash2 className="w-4 h-4" />
                                                </button>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={10}>
                                            <EmptyState
                                                icon={<AlertTriangle className="w-10 h-10 text-slate-300" />}
                                                title="Tidak ada data acuan"
                                                description={`Belum ada barang acuan untuk ${filterBulan ? `bulan ${getNamaBulan(Number(filterBulan))}` : 'filter ini'}. Import template atau tambah manual.`}
                                            />
                                        </td>
                                    </tr>
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
            </div>

            {/* Modal Tambah Manual */}
            <Modal
                isOpen={showModal}
                onClose={() => setShowModal(false)}
                title="Tambah Manual Data Acuan"
                maxWidth="lg"
            >
                        <form onSubmit={handleSubmit} className="space-y-3 text-xs">
                            {isAdmin && sekolahs.length > 0 && (
                                <div>
                                    <label className="block font-bold text-slate-700 mb-1">
                                        Pilih Sekolah
                                    </label>
                                    <select
                                        value={data.sekolah_id}
                                        onChange={(e) => {
                                            const s = sekolahs.find((x) => x.id === e.target.value);
                                            setData((prev) => ({
                                                ...prev,
                                                sekolah_id: e.target.value,
                                                satuan_pendidikan: s?.nama_sekolah || '',
                                            }));
                                        }}
                                        className="w-full p-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                    >
                                        <option value="">-- Pilih Satuan Pendidikan --</option>
                                        {sekolahs.map((s) => (
                                            <option key={s.id} value={s.id}>
                                                {s.nama_sekolah}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            )}

                            <div>
                                <label className="block font-bold text-slate-700 mb-1">Tanggal</label>
                                <input
                                    type="date"
                                    value={data.tanggal}
                                    onChange={(e) => setData('tanggal', e.target.value)}
                                    className="w-full p-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                    required
                                />
                            </div>

                            <div className="relative">
                                <label className="block font-bold text-slate-700 mb-1">
                                    Kodering (Live Search Master Barang)
                                </label>
                                <input
                                    type="text"
                                    value={data.kodering}
                                    onChange={(e) => handleSearchKodering(e.target.value)}
                                    placeholder="Ketik kode / nama barang..."
                                    className="w-full p-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none font-mono"
                                />
                                {suggestions.length > 0 && (
                                    <div className="absolute top-full left-0 right-0 z-10 bg-white border border-slate-200 rounded-lg shadow-lg max-h-48 overflow-y-auto mt-1 divide-y divide-slate-100">
                                        {suggestions.map((s) => (
                                            <div
                                                key={s.kode_barang}
                                                onClick={() => handleSelectBarang(s)}
                                                className="p-2 hover:bg-blue-50 cursor-pointer text-left"
                                            >
                                                <div className="font-mono font-bold text-[#2563eb]">
                                                    {s.kode_barang}
                                                </div>
                                                <div className="text-slate-600">{s.nama_barang}</div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>

                            <div className="grid grid-cols-2 gap-2">
                                <div>
                                    <label className="block font-bold text-slate-700 mb-1">BKU</label>
                                    <input
                                        type="text"
                                        value={data.bku}
                                        onChange={(e) => setData('bku', e.target.value)}
                                        className="w-full p-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                    />
                                </div>
                                <div>
                                    <label className="block font-bold text-slate-700 mb-1">Bulan</label>
                                    <select
                                        value={data.bulan}
                                        onChange={(e) => setData('bulan', Number(e.target.value))}
                                        className="w-full p-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                    >
                                        {BULAN_LIST.map((name, i) => (
                                            <option key={i + 1} value={i + 1}>
                                                {name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label className="block font-bold text-slate-700 mb-1">Uraian Barang</label>
                                <textarea
                                    value={data.uraian}
                                    onChange={(e) => setData('uraian', e.target.value)}
                                    rows={2}
                                    className="w-full p-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                    required
                                />
                            </div>

                            <div>
                                <label className="block font-bold text-slate-700 mb-1">Nominal (Rp)</label>
                                <input
                                    type="number"
                                    value={data.nominal}
                                    onChange={(e) => setData('nominal', Number(e.target.value))}
                                    className="w-full p-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none font-semibold text-right"
                                    required
                                    min="0"
                                />
                            </div>

                            <div className="flex justify-end gap-2 pt-3">
                                <button
                                    type="button"
                                    onClick={() => setShowModal(false)}
                                    className="px-4 py-2 border border-slate-200 text-slate-600 rounded-lg hover:bg-slate-50 font-semibold"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="px-4 py-2 bg-[#2563eb] text-white rounded-lg hover:bg-blue-700 font-semibold disabled:opacity-50"
                                >
                                    {processing ? 'Menyimpan...' : 'Simpan Data'}
                                </button>
                            </div>
                        </form>
            </Modal>

            {/* Konfirmasi Hapus Baris */}
            <ConfirmDialog
                isOpen={confirmTarget !== null}
                onClose={() => setConfirmTarget(null)}
                onConfirm={handleDelete}
                title="Hapus Baris Acuan"
                message={`Hapus acuan "${confirmTarget?.uraian ?? ''}"${confirmTarget?.sekolah ? ` atas nama ${confirmTarget.sekolah.nama_sekolah}` : ''}? Tindakan ini tidak dapat dibatalkan.`}
                confirmText="Ya, Hapus"
                isDestructive={true}
            />

            {/* Konfirmasi Kosongkan Semua */}
            <ConfirmDialog
                isOpen={showKosongkan}
                onClose={() => setShowKosongkan(false)}
                onConfirm={handleKosongkanConfirm}
                title="Kosongkan Data Acuan"
                message={kosongkanMessage}
                confirmText="Ya, Kosongkan"
                isDestructive={true}
            />
        </AppLayout>
    );
}
