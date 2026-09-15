import { Head, router } from '@inertiajs/react';
import React, { useState, useMemo } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import {
    Plus,
    Printer,
    Trash2,
    Lock,
    Search,
    X,
    Pencil,
    Download,
    CheckCircle2,
    Clock,
    Calendar,
    Box,
    BookOpen,
    Wrench,
    AlertCircle,
} from 'lucide-react';
import ConfirmDialog from '@/Components/ConfirmDialog';

interface SpjItem {
    id: string;
    no_spk: string;
    no_sp2d: string | null;
    sumber_perolehan?: string | null;
    kategori?: string | null;
    ba_no?: string | null;
    ba_tgl?: string | null;
    kode_barang: string;
    nama_barang: string;
    jenis_aset: string;
    merk_tipe?: string | null;
    no_sertifikat?: string | null;
    ukuran_bangunan?: string | null;
    satuan: string | null;
    volume: number;
    harga_satuan: number;
    nilai_perolehan: number;
    is_realisasi: boolean;
}

interface Props {
    items: SpjItem[];
    bulan: number;
    isLocked: boolean;
    statusKirim: string;
}

interface SpkGroup {
    no_spk: string;
    ba_no?: string | null;
    ba_tgl?: string | null;
    sumber_perolehan?: string | null;
    items: SpjItem[];
    total_nilai_spk: number;
}

export default function Index({ items, bulan, isLocked, statusKirim }: Props) {
    const [searchQuery, setSearchQuery] = useState('');
    const [showKategoriModal, setShowKategoriModal] = useState(false);
    const [confirmDeleteSpk, setConfirmDeleteSpk] = useState<string | null>(null);
    const [confirmDeleteItem, setConfirmDeleteItem] = useState<{ id: string; nama: string } | null>(null);

    const bulanNames = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];

    const isReadOnly = isLocked || statusKirim === 'menunggu_approval' || statusKirim === 'disetujui';

    // Grouping by SPK
    const groupedSpk = useMemo(() => {
        const groups: Record<string, SpkGroup> = {};
        for (const item of items) {
            const spkKey = item.no_spk || 'TANPA_SPK';
            if (!groups[spkKey]) {
                groups[spkKey] = {
                    no_spk: item.no_spk,
                    ba_no: item.ba_no,
                    ba_tgl: item.ba_tgl,
                    sumber_perolehan: item.sumber_perolehan,
                    items: [],
                    total_nilai_spk: 0,
                };
            }
            groups[spkKey].items.push(item);
            groups[spkKey].total_nilai_spk += Number(item.nilai_perolehan);
        }
        return Object.values(groups);
    }, [items]);

    // Live search filter across SPK and Items
    const filteredGroups = useMemo(() => {
        if (!searchQuery.trim()) return groupedSpk;
        const q = searchQuery.toLowerCase();
        return groupedSpk
            .map((g) => {
                const matchSpk = g.no_spk.toLowerCase().includes(q) || (g.sumber_perolehan || '').toLowerCase().includes(q);
                const matchingItems = g.items.filter(
                    (it) =>
                        it.kode_barang.toLowerCase().includes(q) ||
                        it.nama_barang.toLowerCase().includes(q) ||
                        (it.merk_tipe || '').toLowerCase().includes(q)
                );
                if (matchSpk) return g;
                if (matchingItems.length > 0) return { ...g, items: matchingItems };
                return null;
            })
            .filter((g): g is SpkGroup => g !== null);
    }, [groupedSpk, searchQuery]);

    const grandTotal = useMemo(() => {
        return items.reduce((acc, it) => acc + Number(it.nilai_perolehan), 0);
    }, [items]);

    const openCreateWithKategori = (kategori: string) => {
        setShowKategoriModal(false);
        router.visit(`/pelaporan-bm/spj/create?kategori=${encodeURIComponent(kategori)}&bulan=${bulan}`);
    };

    const handleHapusSpk = () => {
        if (!confirmDeleteSpk) return;
        router.delete(`/pelaporan-bm/spj/spk/${encodeURIComponent(confirmDeleteSpk)}?bulan=${bulan}`, {
            onSuccess: () => setConfirmDeleteSpk(null),
        });
    };

    const handleHapusItem = () => {
        if (!confirmDeleteItem) return;
        router.delete(`/pelaporan-bm/spj/${confirmDeleteItem.id}?bulan=${bulan}`, {
            onSuccess: () => setConfirmDeleteItem(null),
        });
    };

    return (
        <AppLayout title="Data Barang">
            <Head title={`Data Barang | Bulan ${bulanNames[bulan - 1]}`} />

            <div className="space-y-6">
                {/* Header Floating Banner Bar */}
                <div className="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div>
                        <div className="flex flex-wrap items-center gap-2 mb-2">
                            <span className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#1e3a8a] text-white rounded-lg text-xs font-black uppercase tracking-wider font-mono">
                                <Box className="w-4 h-4" /> Manajemen Data Barang
                            </span>
                            <span className="px-3 py-1.5 bg-slate-100 text-slate-700 border border-slate-200 rounded-lg text-xs font-bold font-mono">
                                Total SPJ Bulan {bulanNames[bulan - 1]}: {groupedSpk.length} Berkas
                            </span>
                            <span className="px-3 py-1.5 bg-slate-100 text-slate-700 border border-slate-200 rounded-lg text-xs font-bold font-mono">
                                Total Barang: {items.length} Item
                            </span>
                            {isReadOnly && (
                                <span className="inline-flex items-center gap-1 px-3 py-1.5 bg-red-100 text-red-700 rounded-lg text-xs font-bold border border-red-200">
                                    <Lock className="w-3.5 h-3.5" /> Terkunci
                                </span>
                            )}
                        </div>
                        <h1 className="text-xl font-black text-slate-800 tracking-tight">
                            Katalog Dokumen SPJ Belanja Modal
                        </h1>
                        <p className="text-slate-500 text-xs">
                            Pengelolaan dokumen SPK pengadaan belanja modal dan rincian fisik barang sekolah periode <strong>Bulan {bulanNames[bulan - 1]}</strong>.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            onClick={() => router.visit('/pelaporan-bm/spj/pilih-bulan')}
                            className="inline-flex items-center gap-1.5 px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition cursor-pointer"
                        >
                            <Calendar className="w-4 h-4 text-slate-500" />
                            <span>Pilih Bulan Lain</span>
                        </button>
                        <a
                            href={`/pelaporan-bm/unduh?bulan=${bulan}`}
                            className="inline-flex items-center gap-1.5 px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition shadow-sm"
                        >
                            <Download className="w-4 h-4" />
                            <span>Unduh CSV</span>
                        </a>
                        <a
                            href={`/pelaporan-bm/cetak?bulan=${bulan}`}
                            target="_blank"
                            rel="noreferrer"
                            className="inline-flex items-center gap-1.5 px-3 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-bold transition shadow-sm"
                        >
                            <Printer className="w-4 h-4" />
                            <span>Cetak BA</span>
                        </a>
                        {!isReadOnly && (
                            <button
                                type="button"
                                onClick={() => setShowKategoriModal(true)}
                                className="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition shadow-sm cursor-pointer"
                            >
                                <Plus className="w-4 h-4" />
                                <span>Tambah SPJ</span>
                            </button>
                        )}
                    </div>
                </div>

                {/* Live Filter Input Bar */}
                <div className="bg-white border border-slate-200 rounded-2xl p-3 shadow-sm flex items-center gap-3">
                    <Search className="w-5 h-5 text-slate-400 ml-2 shrink-0" />
                    <input
                        type="text"
                        placeholder="Cari Data SPJ / Barang (Nomor SPK, Nama Barang, Kode, atau Merk)..."
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        className="w-full bg-transparent border-none text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-0"
                    />
                    {searchQuery && (
                        <button
                            type="button"
                            onClick={() => setSearchQuery('')}
                            className="text-slate-400 hover:text-slate-600 p-1 mr-1 cursor-pointer"
                        >
                            <X className="w-4 h-4" />
                        </button>
                    )}
                </div>

                {/* Tabel Grouped SPJ */}
                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-xs text-left">
                            <thead className="bg-[#1e3a8a] text-white font-extrabold uppercase tracking-wider text-[11px] sticky top-0 z-10">
                                <tr>
                                    <th className="py-3.5 px-4 w-[240px]">Dokumen SPK</th>
                                    <th className="py-3.5 px-4">Rincian Barang</th>
                                    <th className="py-3.5 px-3 w-28 text-center">Fisik & Satuan</th>
                                    <th className="py-3.5 px-4 w-32 text-right">Nilai Satuan</th>
                                    <th className="py-3.5 px-4 w-36 text-right">Total Perolehan</th>
                                    <th className="py-3.5 px-3 w-32 text-center">Status</th>
                                    <th className="py-3.5 px-4 w-36 text-right">Total SPK</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200">
                                {filteredGroups.length > 0 ? (
                                    filteredGroups.map((group) => {
                                        const itemCount = group.items.length;
                                        return group.items.map((item, idx) => {
                                            const isFirst = idx === 0;
                                            return (
                                                <tr
                                                    key={item.id}
                                                    className={`hover:bg-blue-50/30 transition ${
                                                        idx === itemCount - 1 ? 'border-b-2 border-slate-300' : ''
                                                    }`}
                                                >
                                                    {/* Rowspan Kolom Dokumen SPK */}
                                                    {isFirst && (
                                                        <td
                                                            rowSpan={itemCount}
                                                            className="py-3 px-4 align-top border-r border-slate-200 bg-slate-50/50"
                                                        >
                                                            <div className="font-mono font-black text-slate-800 text-xs break-all">
                                                                SPK:<br />{group.no_spk}
                                                            </div>
                                                            {group.ba_tgl && (
                                                                <div className="text-[11px] text-slate-500 mt-0.5">
                                                                    Tgl: {new Date(group.ba_tgl).toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' })}
                                                                </div>
                                                            )}
                                                            <div className="text-[11px] text-slate-500 font-semibold mt-1">
                                                                Sumber: <span className="text-blue-700 font-bold">{group.sumber_perolehan || '-'}</span>
                                                            </div>
                                                            {group.ba_no && (
                                                                <div className="text-[10px] text-slate-400 mt-0.5">
                                                                    BA: {group.ba_no}
                                                                </div>
                                                            )}
                                                            {!isReadOnly && (
                                                                <div className="flex items-center gap-1.5 mt-3 pt-2 border-t border-slate-200">
                                                                    <button
                                                                        type="button"
                                                                        onClick={() =>
                                                                            router.visit(
                                                                                `/pelaporan-bm/spj/edit-spk/${encodeURIComponent(
                                                                                    group.no_spk
                                                                                )}?bulan=${bulan}`
                                                                            )
                                                                        }
                                                                        className="inline-flex items-center gap-1 px-2 py-1 bg-amber-50 text-amber-700 hover:bg-amber-100 border border-amber-200 rounded-md text-[11px] font-bold transition cursor-pointer"
                                                                        title="Edit Seluruh SPK"
                                                                    >
                                                                        <Pencil className="w-3 h-3" /> Edit SPK
                                                                    </button>
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => setConfirmDeleteSpk(group.no_spk)}
                                                                        className="inline-flex items-center gap-1 px-2 py-1 bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200 rounded-md text-[11px] font-bold transition cursor-pointer"
                                                                        title="Hapus Seluruh SPK"
                                                                    >
                                                                        <Trash2 className="w-3 h-3" /> Hapus SPK
                                                                    </button>
                                                                </div>
                                                            )}
                                                        </td>
                                                    )}

                                                    {/* Kolom Rincian Barang */}
                                                    <td className="py-2.5 px-4 align-top">
                                                        <div className="flex items-center gap-2">
                                                            <span className="font-mono text-[11px] font-bold text-blue-700 bg-blue-50 px-1.5 py-0.5 rounded border border-blue-200">
                                                                {item.kode_barang}
                                                            </span>
                                                            <span className="font-bold text-slate-800 text-xs">
                                                                {item.nama_barang}
                                                            </span>
                                                        </div>
                                                        <div className="text-[11px] text-slate-500 mt-1 flex flex-wrap gap-x-3">
                                                            <span>Merk: <strong>{item.merk_tipe || '-'}</strong></span>
                                                            <span>Jenis: <strong>{item.jenis_aset}</strong></span>
                                                            {item.no_sertifikat && item.no_sertifikat !== '-' && (
                                                                <span>Sertifikat/Pabrik: <strong>{item.no_sertifikat}</strong></span>
                                                            )}
                                                            {item.ukuran_bangunan && item.ukuran_bangunan !== '-' && (
                                                                <span>Ukuran: <strong>{item.ukuran_bangunan}</strong></span>
                                                            )}
                                                        </div>
                                                        {!isReadOnly && (
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    setConfirmDeleteItem({
                                                                        id: item.id,
                                                                        nama: item.nama_barang,
                                                                    })
                                                                }
                                                                className="inline-flex items-center gap-1 text-[10.5px] text-rose-600 hover:text-rose-800 font-semibold mt-1 cursor-pointer"
                                                            >
                                                                <Trash2 className="w-3 h-3" /> Hapus Item
                                                            </button>
                                                        )}
                                                    </td>

                                                    {/* Kolom Fisik & Satuan */}
                                                    <td className="py-2.5 px-3 text-center align-top font-bold text-slate-700">
                                                        {Number(item.volume).toLocaleString('id-ID')} {item.satuan || 'Unit'}
                                                    </td>

                                                    {/* Kolom Harga Satuan */}
                                                    <td className="py-2.5 px-4 text-right align-top font-mono text-slate-600">
                                                        Rp {Number(item.harga_satuan).toLocaleString('id-ID')}
                                                    </td>

                                                    {/* Kolom Total Nilai Perolehan */}
                                                    <td className="py-2.5 px-4 text-right align-top font-mono font-bold text-blue-700">
                                                        Rp {Number(item.nilai_perolehan).toLocaleString('id-ID')}
                                                    </td>

                                                    {/* Kolom Status Realisasi */}
                                                    <td className="py-2.5 px-3 text-center align-top">
                                                        {item.is_realisasi ? (
                                                            <span className="inline-flex items-center gap-1 px-2.5 py-0.5 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-full text-[10.5px] font-bold">
                                                                <CheckCircle2 className="w-3 h-3" /> Sudah Realisasi
                                                            </span>
                                                        ) : (
                                                            <span className="inline-flex items-center gap-1 px-2.5 py-0.5 bg-amber-50 text-amber-700 border border-amber-200 rounded-full text-[10.5px] font-bold">
                                                                <Clock className="w-3 h-3" /> Belum Realisasi
                                                            </span>
                                                        )}
                                                    </td>

                                                    {/* Rowspan Total SPK */}
                                                    {isFirst && (
                                                        <td
                                                            rowSpan={itemCount}
                                                            className="py-3 px-4 text-right align-top border-l border-slate-200 bg-blue-50/40 font-mono font-black text-slate-900 text-xs"
                                                        >
                                                            Rp {Number(group.total_nilai_spk).toLocaleString('id-ID')}
                                                        </td>
                                                    )}
                                                </tr>
                                            );
                                        });
                                    })
                                ) : (
                                    <tr>
                                        <td colSpan={7} className="py-12 text-center text-slate-400">
                                            <Box className="w-10 h-10 mx-auto text-slate-300 mb-2" />
                                            Belum ada berkas data barang / SPJ pada periode bulan ini.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                            <tfoot className="bg-slate-100 border-t-2 border-slate-300 font-extrabold text-xs">
                                <tr>
                                    <td colSpan={4} className="py-3 px-4 text-right uppercase tracking-wider text-slate-700">
                                        Total Keseluruhan SPJ Bulan {bulanNames[bulan - 1]} :
                                    </td>
                                    <td colSpan={3} className="py-3 px-4 text-right font-mono font-black text-blue-700 text-sm bg-blue-100/50">
                                        Rp {grandTotal.toLocaleString('id-ID')}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            {/* Modal Kategori Belanja (Sesuai legacy data_barang.php) */}
            {showKategoriModal && (
                <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200">
                        <div className="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                            <h3 className="font-bold text-slate-800 text-base flex items-center gap-2">
                                <Box className="w-5 h-5 text-blue-600" />
                                Pilih Kategori Belanja
                            </h3>
                            <button
                                type="button"
                                onClick={() => setShowKategoriModal(false)}
                                className="text-slate-400 hover:text-slate-600 p-1 cursor-pointer"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>
                        <p className="text-slate-500 text-xs mb-5 leading-relaxed">
                            Silakan pilih jenis kategori belanja terlebih dahulu untuk menyesuaikan aturan dokumen SPK pengadaan.
                        </p>
                        <div className="space-y-3">
                            <button
                                type="button"
                                onClick={() => openCreateWithKategori('Peralatan & Mesin')}
                                className="w-full p-4 rounded-xl border-2 border-slate-200 hover:border-blue-600 hover:bg-blue-50/50 flex items-center gap-4 transition text-left cursor-pointer"
                            >
                                <div className="w-11 h-11 rounded-xl bg-blue-600 text-white flex items-center justify-center shrink-0">
                                    <Wrench className="w-5 h-5" />
                                </div>
                                <div>
                                    <div className="font-bold text-slate-800 text-sm">Peralatan & Mesin</div>
                                    <div className="text-slate-400 text-xs">Komputer, Mebel, Alat Lab, dll.</div>
                                </div>
                            </button>

                            <button
                                type="button"
                                onClick={() => openCreateWithKategori('Buku')}
                                className="w-full p-4 rounded-xl border-2 border-slate-200 hover:border-teal-600 hover:bg-teal-50/50 flex items-center gap-4 transition text-left cursor-pointer"
                            >
                                <div className="w-11 h-11 rounded-xl bg-teal-600 text-white flex items-center justify-center shrink-0">
                                    <BookOpen className="w-5 h-5" />
                                </div>
                                <div>
                                    <div className="font-bold text-slate-800 text-sm">Buku Perpustakaan / Umum</div>
                                    <div className="text-slate-400 text-xs">Wajib isi No. Sertifikat / Pabrik saat input.</div>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Dialog Konfirmasi Hapus SPK */}
            <ConfirmDialog
                isOpen={!!confirmDeleteSpk}
                title="Konfirmasi Hapus Seluruh Dokumen SPK"
                message={`Peringatan Keras! Apakah Anda yakin ingin menghapus SELURUH ITEM BARANG di dalam Dokumen SPK [${confirmDeleteSpk}] ini?`}
                onConfirm={handleHapusSpk}
                onClose={() => setConfirmDeleteSpk(null)}
            />

            {/* Dialog Konfirmasi Hapus Item */}
            <ConfirmDialog
                isOpen={!!confirmDeleteItem}
                title="Konfirmasi Hapus Item Barang"
                message={`Apakah Anda yakin ingin menghapus barang [${confirmDeleteItem?.nama}] ini dari SPK?`}
                onConfirm={handleHapusItem}
                onClose={() => setConfirmDeleteItem(null)}
            />
        </AppLayout>
    );
}
