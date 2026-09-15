import { Head, useForm, router } from '@inertiajs/react';
import React, { useState, useRef } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import {
    Plus,
    Printer,
    Trash2,
    Lock,
    Search,
    X,
    Pencil,
    Send,
    Download,
    CheckCircle2,
    Clock,
    Scale,
    Coins,
    FolderKanban,
    Calendar,
} from 'lucide-react';

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
    acuan_id?: string | null;
}

interface AcuanItem {
    id: string;
    kodering: string;
    uraian: string;
    nominal: number;
}

interface Props {
    items: SpjItem[];
    acuanList: AcuanItem[];
    totalAcuan: number;
    bulan: number;
    isLocked: boolean;
    statusKirim: string;
    mode?: string;
}

export default function Index({ items, acuanList, totalAcuan = 0, bulan, isLocked, statusKirim, mode }: Props) {
    const [showModal, setShowModal] = useState(false);
    const [showKategoriModal, setShowKategoriModal] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [editingItem, setEditingItem] = useState<SpjItem | null>(null);
    const [suggestions, setSuggestions] = useState<{ kode_barang: string; nama_barang: string; jenis_aset: string; satuan: string | null }[]>([]);
    const [activeTab, setActiveTab] = useState<'katalog' | 'acuan'>(mode === 'realisasi' ? 'acuan' : 'katalog');
    const [expandedKodering, setExpandedKodering] = useState<string[]>([]);
    const searchTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const bulanNames = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];

    const isReadOnly = isLocked || statusKirim === 'menunggu_approval' || statusKirim === 'disetujui';

    const { data, setData, post, put, processing, reset, errors } = useForm({
        no_spk: '',
        no_sp2d: '',
        sumber_perolehan: 'BOS Reguler',
        bulan_realisasi: bulan,
        kategori: 'Peralatan dan Mesin',
        ba_no: '',
        ba_tgl: '',
        kode_barang: '',
        nama_barang: '',
        jenis_aset: 'Peralatan dan Mesin',
        merk_tipe: '',
        no_sertifikat: '',
        ukuran_bangunan: '',
        volume: 1,
        harga_satuan: 0,
        satuan: 'Unit',
        acuan_id: '',
    });

    const openCreateWithKategori = (kategori: string) => {
        setShowKategoriModal(false);
        router.visit(`/pelaporan-bm/spj/create?kategori=${encodeURIComponent(kategori)}&bulan=${bulan}`);
    };

    const openCreateForAcuan = (acuanId: string) => {
        setShowKategoriModal(false);
        setEditingItem(null);
        reset();
        setData((prev) => ({
            ...prev,
            bulan_realisasi: bulan,
            acuan_id: acuanId,
            kategori: 'Peralatan dan Mesin',
            jenis_aset: 'Peralatan dan Mesin',
            satuan: 'Unit',
            volume: 1,
            harga_satuan: 0,
        }));
        setShowModal(true);
    };

    const openEditModal = (item: SpjItem) => {
        setEditingItem(item);
        setData({
            no_spk: item.no_spk,
            no_sp2d: item.no_sp2d || '',
            sumber_perolehan: item.sumber_perolehan || 'BOS Reguler',
            bulan_realisasi: bulan,
            kategori: item.kategori || 'Peralatan dan Mesin',
            ba_no: item.ba_no || '',
            ba_tgl: item.ba_tgl ? item.ba_tgl.substring(0, 10) : '',
            kode_barang: item.kode_barang,
            nama_barang: item.nama_barang,
            jenis_aset: item.jenis_aset || 'Peralatan dan Mesin',
            merk_tipe: item.merk_tipe || '',
            no_sertifikat: item.no_sertifikat || '',
            ukuran_bangunan: item.ukuran_bangunan || '',
            volume: Number(item.volume),
            harga_satuan: Number(item.harga_satuan),
            satuan: item.satuan || 'Unit',
            acuan_id: item.acuan_id || '',
        });
        setShowModal(true);
    };

    const handleSearch = (q: string) => {
        setData('nama_barang', q);
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

    const handleSearchKodeBarang = (q: string) => {
        setData('kode_barang', q);
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

    const handleSelectBarang = (item: { kode_barang: string; nama_barang: string; jenis_aset: string; satuan: string | null }) => {
        setData((prev) => ({
            ...prev,
            kode_barang: item.kode_barang,
            nama_barang: item.nama_barang,
            jenis_aset: item.jenis_aset || prev.jenis_aset,
            satuan: item.satuan || prev.satuan,
        }));
        setSuggestions([]);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingItem) {
            put(`/pelaporan-bm/spj/${editingItem.id}`, {
                onSuccess: () => {
                    setShowModal(false);
                    setEditingItem(null);
                    reset();
                },
            });
        } else {
            post('/pelaporan-bm/spj', {
                onSuccess: () => {
                    setShowModal(false);
                    reset();
                    setSuggestions([]);
                },
            });
        }
    };

    const handleDelete = (id: string) => {
        if (confirm('Yakin ingin menghapus item SPJ ini?')) {
            router.delete(`/pelaporan-bm/spj/${id}`);
        }
    };

    const handleDeleteSpk = (noSpk: string) => {
        if (confirm(`Yakin ingin menghapus seluruh data dokumen SPK "${noSpk}"?`)) {
            router.delete(`/pelaporan-bm/spj/spk/${encodeURIComponent(noSpk)}?bulan=${bulan}`);
        }
    };

    const handleKirimLaporan = () => {
        if (confirm(`Kirim laporan Belanja Modal bulan ${bulanNames[bulan - 1]} ke KCD Wilayah X?`)) {
            router.post('/pelaporan-bm/kirim-laporan', { bulan });
        }
    };

    const handleFilterBulan = (b: number) => {
        router.get('/pelaporan-bm/spj', { bulan: b }, { preserveState: true });
    };

    const totalRealisasi = items
        .filter((i) => i.is_realisasi)
        .reduce((acc, curr) => acc + Number(curr.nilai_perolehan || 0), 0);

    const sisaAnggaran = totalAcuan - totalRealisasi;

    const filteredItems = items.filter((item) => {
        if (!searchQuery) return true;
        const q = searchQuery.toLowerCase();
        return (
            item.no_spk?.toLowerCase().includes(q) ||
            item.nama_barang?.toLowerCase().includes(q) ||
            item.merk_tipe?.toLowerCase().includes(q) ||
            item.kode_barang?.toLowerCase().includes(q)
        );
    });

    const groupedSpj = filteredItems.reduce<
        Record<string, { no_spk: string; ba_no?: string | null; ba_tgl?: string | null; sumber_perolehan?: string | null; items: SpjItem[]; totalNilaiSpk: number }>
    >((acc, item) => {
        const key = item.no_spk || 'TANPA_SPK';
        if (!acc[key]) {
            acc[key] = {
                no_spk: item.no_spk,
                ba_no: item.ba_no,
                ba_tgl: item.ba_tgl,
                sumber_perolehan: item.sumber_perolehan || 'BOS Reguler',
                items: [],
                totalNilaiSpk: 0,
            };
        }
        acc[key].items.push(item);
        acc[key].totalNilaiSpk += Number(item.nilai_perolehan || 0);
        return acc;
    }, {});

    const groupedAcuan = acuanList.reduce<
        Record<string, { id: string; kodering: string; list_uraian: string[]; nominal_acuan: number; nominal_realisasi: number; kekurangan: number }>
    >((acc, acuan) => {
        const k = acuan.kodering || 'TANPA_KODERING';
        if (!acc[k]) {
            acc[k] = {
                id: acuan.id,
                kodering: k,
                list_uraian: [],
                nominal_acuan: 0,
                nominal_realisasi: 0,
                kekurangan: 0,
            };
        }
        acc[k].list_uraian.push(acuan.uraian);
        acc[k].nominal_acuan += Number(acuan.nominal || 0);
        return acc;
    }, {});

    items.filter((i) => i.is_realisasi).forEach((item) => {
        const matched = acuanList.find((a) => a.id === item.acuan_id);
        const k = matched?.kodering || 'TANPA_KODERING';
        if (groupedAcuan[k]) {
            groupedAcuan[k].nominal_realisasi += Number(item.nilai_perolehan || 0);
        }
    });

    Object.values(groupedAcuan).forEach((g) => {
        g.kekurangan = g.nominal_acuan - g.nominal_realisasi;
    });

    return (
        <AppLayout title={`Buku SPJ - ${bulanNames[bulan - 1]}`}>
            <Head title={`SPJ Bulan ${bulanNames[bulan - 1]}`} />

            <div className="max-w-7xl mx-auto space-y-6">
                {/* Header Section */}
                <div className="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                    <div>
                        <div className="flex flex-wrap items-center gap-2.5">
                            <h1 className="text-2xl font-black text-slate-800 flex items-center gap-2">
                                <FolderKanban className="w-6 h-6 text-blue-600" /> Buku SPJ Belanja Modal
                            </h1>
                            <div className="flex items-center gap-1 bg-slate-50 border border-slate-200 px-2 py-1 rounded-xl">
                                <Calendar className="w-4 h-4 text-slate-400" />
                                <select
                                    value={bulan}
                                    onChange={(e) => handleFilterBulan(Number(e.target.value))}
                                    className="bg-transparent border-none text-xs font-bold text-slate-800 outline-none cursor-pointer"
                                >
                                    {bulanNames.map((nama, idx) => (
                                        <option key={idx + 1} value={idx + 1}>
                                            Bulan {nama}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        <div className="flex flex-wrap items-center gap-2 mt-2">
                            {isLocked ? (
                                <span className="inline-flex items-center gap-1.5 px-3 py-1 bg-red-100 text-red-700 text-xs font-bold rounded-full border border-red-200">
                                    <Lock className="w-3.5 h-3.5" /> Laporan Dikunci Dinas
                                </span>
                            ) : (
                                <span className="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 text-emerald-700 text-xs font-bold rounded-full border border-emerald-200">
                                    <CheckCircle2 className="w-3.5 h-3.5" /> Terbuka untuk Pengisian
                                </span>
                            )}

                            {statusKirim === 'menunggu_approval' ? (
                                <span className="inline-flex items-center gap-1.5 px-3 py-1 bg-amber-50 text-amber-700 text-xs font-bold rounded-full border border-amber-200">
                                    <Clock className="w-3.5 h-3.5" /> Menunggu Verifikasi KCD
                                </span>
                            ) : statusKirim === 'disetujui' ? (
                                <span className="inline-flex items-center gap-1.5 px-3 py-1 bg-blue-50 text-blue-700 text-xs font-bold rounded-full border border-blue-200">
                                    <CheckCircle2 className="w-3.5 h-3.5" /> Laporan Disetujui
                                </span>
                            ) : (
                                <span className="inline-flex items-center gap-1.5 px-3 py-1 bg-slate-100 text-slate-600 text-xs font-bold rounded-full">
                                    Status: Draft
                                </span>
                            )}
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2.5">
                        <a
                            href={`/pelaporan-bm/unduh?bulan=${bulan}`}
                            className="inline-flex items-center gap-2 px-3.5 py-2 bg-emerald-600 text-white rounded-xl text-xs font-bold hover:bg-emerald-700 transition shadow-sm"
                        >
                            <Download className="w-4 h-4" /> Unduh CSV
                        </a>
                        <a
                            href={`/pelaporan-bm/cetak?bulan=${bulan}`}
                            target="_blank"
                            rel="noreferrer"
                            className="inline-flex items-center gap-2 px-3.5 py-2 bg-slate-800 text-white rounded-xl text-xs font-bold hover:bg-slate-900 transition shadow-sm"
                        >
                            <Printer className="w-4 h-4" /> Cetak Berita Acara
                        </a>
                        {!isReadOnly && (
                            <>
                                <button
                                    onClick={() => setShowKategoriModal(true)}
                                    className="inline-flex items-center gap-2 px-4 py-2 bg-[#2563eb] text-white rounded-xl text-xs font-bold hover:bg-blue-700 transition shadow-sm cursor-pointer"
                                >
                                    <Plus className="w-4 h-4" /> Tambah SPJ
                                </button>
                                <button
                                    onClick={handleKirimLaporan}
                                    className="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-xl text-xs font-bold hover:bg-indigo-700 transition shadow-sm cursor-pointer"
                                >
                                    <Send className="w-4 h-4" /> Kirim Laporan
                                </button>
                            </>
                        )}
                    </div>
                </div>

                {/* Sisa Anggaran Box */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div className="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
                        <div className="flex items-center justify-between text-xs text-slate-500 font-bold uppercase mb-1">
                            <span>Target Acuan Bulan Ini</span>
                            <Scale className="w-4 h-4 text-slate-400" />
                        </div>
                        <div className="text-xl font-black text-slate-800 font-mono">
                            Rp {Number(totalAcuan).toLocaleString('id-ID')}
                        </div>
                    </div>
                    <div className="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
                        <div className="flex items-center justify-between text-xs text-slate-500 font-bold uppercase mb-1">
                            <span>Total Realisasi SPJ</span>
                            <Coins className="w-4 h-4 text-[#0284c7]" />
                        </div>
                        <div className="text-xl font-black text-[#0284c7] font-mono">
                            Rp {Number(totalRealisasi).toLocaleString('id-ID')}
                        </div>
                    </div>
                    <div className="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
                        <div className="flex items-center justify-between text-xs text-slate-500 font-bold uppercase mb-1">
                            <span>Sisa Saldo Anggaran</span>
                            <span className={`text-[10px] font-bold px-2 py-0.5 rounded ${sisaAnggaran < 0 ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700'}`}>
                                {sisaAnggaran < 0 ? 'Defisit' : 'Tersedia'}
                            </span>
                        </div>
                        <div className={`text-xl font-black font-mono ${sisaAnggaran < 0 ? 'text-red-600' : 'text-emerald-600'}`}>
                            Rp {Number(sisaAnggaran).toLocaleString('id-ID')}
                        </div>
                    </div>
                </div>

                {/* Tab Switcher: Katalog SPJ vs Target Acuan */}
                <div className="flex items-center gap-2 border-b border-slate-200">
                    <button
                        type="button"
                        onClick={() => setActiveTab('katalog')}
                        className={`px-4 py-2.5 text-xs font-bold border-b-2 transition flex items-center gap-2 cursor-pointer ${
                            activeTab === 'katalog'
                                ? 'border-blue-600 text-blue-600'
                                : 'border-transparent text-slate-500 hover:text-slate-800'
                        }`}
                    >
                        <FolderKanban className="w-4 h-4" /> Katalog Dokumen SPJ (Data Barang)
                    </button>
                    <button
                        type="button"
                        onClick={() => setActiveTab('acuan')}
                        className={`px-4 py-2.5 text-xs font-bold border-b-2 transition flex items-center gap-2 cursor-pointer ${
                            activeTab === 'acuan'
                                ? 'border-blue-600 text-blue-600'
                                : 'border-transparent text-slate-500 hover:text-slate-800'
                        }`}
                    >
                        <Scale className="w-4 h-4" /> Target Acuan Kerja (Input Realisasi)
                    </button>
                </div>

                {activeTab === 'acuan' ? (
                    <div className="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                        <div className="p-4 border-b border-slate-200 bg-slate-50/50 flex justify-between items-center">
                            <div>
                                <h2 className="text-sm font-black text-slate-800">Target Acuan Belanja Modal</h2>
                                <p className="text-xs text-slate-500">
                                    Daftar kodering belanja modal dan progres realisasi bulan {bulanNames[bulan - 1]}.
                                </p>
                            </div>
                            <span className="text-xs font-bold bg-blue-50 text-blue-700 px-3 py-1 rounded-full border border-blue-200">
                                {Object.keys(groupedAcuan).length} Kodering
                            </span>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm border-collapse">
                                <thead className="bg-[#1e3a8a] text-white text-xs uppercase font-extrabold tracking-wider">
                                    <tr>
                                        <th className="p-3.5 w-1/3">Kode Rekening</th>
                                        <th className="p-3.5 text-right w-1/5">Nilai Acuan</th>
                                        <th className="p-3.5 text-right w-1/5">Realisasi</th>
                                        <th className="p-3.5 text-right w-1/5">Kekurangan</th>
                                        <th className="p-3.5 text-center w-28">Aksi Kerja</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 font-medium">
                                    {Object.keys(groupedAcuan).length === 0 ? (
                                        <tr>
                                            <td colSpan={5} className="p-8 text-center text-slate-400">
                                                Tidak ada target acuan kodering anggaran pada bulan ini.
                                            </td>
                                        </tr>
                                    ) : (
                                        Object.values(groupedAcuan).map((g) => {
                                            const isExpanded = expandedKodering.includes(g.kodering);
                                            const isDone = g.kekurangan <= 0;
                                            return (
                                                <tr key={g.kodering} className="hover:bg-slate-50/80 transition">
                                                    <td className="p-3.5">
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                setExpandedKodering((prev) =>
                                                                    prev.includes(g.kodering) ? prev.filter((k) => k !== g.kodering) : [...prev, g.kodering]
                                                                )
                                                            }
                                                            className="font-mono font-black text-slate-800 hover:text-blue-600 text-left flex items-center gap-1.5 cursor-pointer"
                                                        >
                                                            <span className="text-blue-600 text-xs">{isExpanded ? '▼' : '▶'}</span>
                                                            {g.kodering}
                                                        </button>
                                                        {isExpanded && (
                                                            <div className="mt-2 pl-4 border-l-2 border-blue-200 space-y-1 text-xs text-slate-600 bg-slate-50 p-2 rounded-r-lg">
                                                                <div className="font-bold text-slate-700">Daftar Uraian:</div>
                                                                {g.list_uraian.map((u, idx) => (
                                                                    <div key={idx}>• {u}</div>
                                                                ))}
                                                            </div>
                                                        )}
                                                    </td>
                                                    <td className="p-3.5 text-right font-mono text-xs font-semibold text-slate-600">
                                                        Rp {g.nominal_acuan.toLocaleString('id-ID')}
                                                    </td>
                                                    <td className="p-3.5 text-right font-mono text-xs font-black text-blue-600">
                                                        Rp {g.nominal_realisasi.toLocaleString('id-ID')}
                                                    </td>
                                                    <td className={`p-3.5 text-right font-mono text-xs font-black ${isDone ? 'text-emerald-600' : 'text-red-600'}`}>
                                                        Rp {g.kekurangan.toLocaleString('id-ID')}
                                                    </td>
                                                    <td className="p-3.5 text-center">
                                                        {isDone ? (
                                                            <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800">
                                                                <CheckCircle2 className="w-3.5 h-3.5" /> Selesai
                                                            </span>
                                                        ) : !isReadOnly ? (
                                                            <button
                                                                type="button"
                                                                onClick={() => openCreateForAcuan(g.id)}
                                                                className="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-bold bg-blue-600 text-white hover:bg-blue-700 transition shadow-sm cursor-pointer"
                                                                title="Input Realisasi Baru untuk Kodering Ini"
                                                            >
                                                                <Plus className="w-3.5 h-3.5" /> Input
                                                            </button>
                                                        ) : (
                                                            <span className="text-[11px] text-slate-400 italic">Terkunci</span>
                                                        )}
                                                    </td>
                                                </tr>
                                            );
                                        })
                                    )}
                                </tbody>
                                <tfoot className="bg-slate-50 font-bold border-t border-slate-200">
                                    <tr>
                                        <td className="p-3.5 text-right text-xs uppercase text-slate-600">Total:</td>
                                        <td className="p-3.5 text-right font-mono text-xs text-slate-800">
                                            Rp {totalAcuan.toLocaleString('id-ID')}
                                        </td>
                                        <td className="p-3.5 text-right font-mono text-xs text-blue-600">
                                            Rp {totalRealisasi.toLocaleString('id-ID')}
                                        </td>
                                        <td className={`p-3.5 text-right font-mono text-xs ${sisaAnggaran <= 0 ? 'text-emerald-600' : 'text-red-600'}`}>
                                            Rp {sisaAnggaran.toLocaleString('id-ID')}
                                        </td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                ) : (
                    <>
                        {/* Live Search Box (Legacy Match) */}
                        <div className="bg-white p-3.5 rounded-2xl shadow-sm border border-slate-200 flex items-center gap-2.5">
                            <Search className="w-4 h-4 text-slate-400 ms-1" />
                            <input
                                type="text"
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                placeholder="Cari instan berdasarkan No SPK, Nama Barang, atau Merk/Tipe..."
                                className="w-full bg-transparent border-none text-xs font-semibold text-slate-800 outline-none placeholder:text-slate-400"
                            />
                            {searchQuery && (
                                <button onClick={() => setSearchQuery('')} className="text-slate-400 hover:text-slate-600 text-xs px-2 py-1">
                                    Reset
                                </button>
                            )}
                        </div>

                {/* Table Data (Grouped by SPK) */}
                <div className="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm border-collapse">
                            <thead className="bg-[#1e3a8a] text-white text-xs uppercase font-extrabold tracking-wider">
                                <tr>
                                    <th className="p-3.5 w-1/4">Informasi Dokumen Berkas (SPJ)</th>
                                    <th className="p-3.5">Nama Barang / Spesifikasi</th>
                                    <th className="p-3.5 text-center w-20">Vol</th>
                                    <th className="p-3.5 text-right w-28">Harga Satuan</th>
                                    <th className="p-3.5 text-right w-32">Sub Perolehan</th>
                                    <th className="p-3.5 text-center w-16">Aktif</th>
                                    <th className="p-3.5 text-center w-20">Aksi</th>
                                    <th className="p-3.5 text-right w-32">Total SPK</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 font-medium">
                                {Object.keys(groupedSpj).length === 0 ? (
                                    <tr>
                                        <td colSpan={8} className="p-8 text-center text-slate-400">
                                            {searchQuery ? 'Tidak ada data SPJ yang cocok dengan pencarian.' : 'Belum ada rincian belanja modal untuk bulan ini.'}
                                        </td>
                                    </tr>
                                ) : (
                                    Object.entries(groupedSpj).map(([spkKey, group]) => {
                                        const itemCount = group.items.length;
                                        return group.items.map((item, index) => {
                                            const isFirst = index === 0;
                                            return (
                                                <tr
                                                    key={item.id}
                                                    className={`hover:bg-slate-50/80 transition ${!item.is_realisasi ? 'opacity-60 bg-slate-50/40' : ''}`}
                                                >
                                                    {isFirst && (
                                                        <td
                                                            rowSpan={itemCount}
                                                            className="p-3.5 bg-slate-50/60 border-r border-slate-200 align-top font-mono text-xs text-slate-700"
                                                        >
                                                            <div className="mb-2">
                                                                <span className="inline-block px-2 py-0.5 text-[10px] font-bold rounded bg-blue-100 text-blue-800 uppercase">
                                                                    {group.sumber_perolehan}
                                                                </span>
                                                            </div>
                                                            <div className="font-bold text-slate-900 mb-1">
                                                                SPK: {group.no_spk}
                                                            </div>
                                                            {group.ba_no && (
                                                                <div className="text-[11px] text-slate-500">
                                                                    BA: {group.ba_no}
                                                                </div>
                                                            )}
                                                            {group.ba_tgl && (
                                                                <div className="text-[10px] text-slate-400">
                                                                    Tgl: {group.ba_tgl.substring(0, 10)}
                                                                </div>
                                                            )}
                                                            {!isReadOnly && (
                                                                <div className="mt-3 flex flex-wrap gap-1.5">
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => router.visit(`/pelaporan-bm/spj/edit-spk/${encodeURIComponent(group.no_spk)}?bulan=${bulan}`)}
                                                                        className="inline-flex items-center gap-1 text-[11px] text-blue-600 hover:text-blue-800 font-bold px-2 py-1 rounded bg-blue-50 hover:bg-blue-100 transition cursor-pointer"
                                                                        title="Edit dokumen SPK dan rincian barangnya"
                                                                    >
                                                                        <Pencil className="w-3 h-3" /> Edit SPK
                                                                    </button>
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => handleDeleteSpk(group.no_spk)}
                                                                        className="inline-flex items-center gap-1 text-[11px] text-red-600 hover:text-red-800 font-bold px-2 py-1 rounded bg-red-50 hover:bg-red-100 transition cursor-pointer"
                                                                        title="Hapus seluruh dokumen SPK ini"
                                                                    >
                                                                        <Trash2 className="w-3 h-3" /> Hapus SPK
                                                                    </button>
                                                                </div>
                                                            )}
                                                        </td>
                                                    )}

                                                    <td className="p-3.5 font-semibold text-slate-900 border-r border-slate-100">
                                                        <div>{item.nama_barang}</div>
                                                        <div className="text-[11px] text-slate-500 font-normal">
                                                            {item.kode_barang} • {item.jenis_aset}
                                                            {item.merk_tipe ? ` (${item.merk_tipe})` : ''}
                                                            {item.no_sertifikat ? ` • Sertifikat: ${item.no_sertifikat}` : ''}
                                                        </div>
                                                    </td>
                                                    <td className="p-3.5 text-center font-mono text-xs border-r border-slate-100">
                                                        {Number(item.volume).toLocaleString('id-ID')} <span className="text-slate-400">{item.satuan}</span>
                                                    </td>
                                                    <td className="p-3.5 text-right font-mono text-xs border-r border-slate-100">
                                                        Rp {Number(item.harga_satuan).toLocaleString('id-ID')}
                                                    </td>
                                                    <td className="p-3.5 text-right font-black text-[#0284c7] font-mono text-xs border-r border-slate-100">
                                                        Rp {Number(item.nilai_perolehan).toLocaleString('id-ID')}
                                                    </td>
                                                    <td className="p-3.5 text-center border-r border-slate-100">
                                                        <input
                                                            type="checkbox"
                                                            checked={Boolean(item.is_realisasi)}
                                                            disabled={isReadOnly}
                                                            onChange={() => router.post(`/pelaporan-bm/spj/${item.id}/toggle-realisasi`, {}, { preserveScroll: true })}
                                                            className="w-4 h-4 rounded text-blue-600 focus:ring-blue-500 cursor-pointer disabled:opacity-50"
                                                            title="Centang untuk memasukkan ke realisasi laporan"
                                                        />
                                                    </td>
                                                    <td className="p-3.5 text-center border-r border-slate-100">
                                                        {!isReadOnly ? (
                                                            <div className="inline-flex items-center gap-1">
                                                                <button
                                                                    onClick={() => openEditModal(item)}
                                                                    className="p-1.5 text-slate-500 hover:text-blue-600 rounded-lg hover:bg-blue-50 transition cursor-pointer"
                                                                    title="Edit Item SPJ"
                                                                >
                                                                    <Pencil className="w-3.5 h-3.5" />
                                                                </button>
                                                                <button
                                                                    onClick={() => handleDelete(item.id)}
                                                                    className="p-1.5 text-slate-400 hover:text-red-600 rounded-lg hover:bg-red-50 transition cursor-pointer"
                                                                    title="Hapus Item"
                                                                >
                                                                    <Trash2 className="w-3.5 h-3.5" />
                                                                </button>
                                                            </div>
                                                        ) : (
                                                            <span className="text-[10px] text-slate-400 italic">Terkunci</span>
                                                        )}
                                                    </td>

                                                    {isFirst && (
                                                        <td
                                                            rowSpan={itemCount}
                                                            className="p-3.5 text-right font-black text-slate-900 font-mono text-xs bg-slate-50/40 align-middle"
                                                        >
                                                            Rp {group.totalNilaiSpk.toLocaleString('id-ID')}
                                                        </td>
                                                    )}
                                                </tr>
                                            );
                                        });
                                    })
                                )}
                            </tbody>
                            <tfoot className="bg-slate-50 font-bold border-t border-slate-200">
                                <tr>
                                    <td colSpan={4} className="p-3.5 text-right text-xs uppercase text-slate-600">
                                        Total Realisasi Bulan Ini:
                                    </td>
                                    <td className="p-3.5 text-right font-black text-blue-700 font-mono text-base">
                                        Rp {totalRealisasi.toLocaleString('id-ID')}
                                    </td>
                                    <td colSpan={3}></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                </>
            )}
            </div>

            {/* Modal Pilihan Kategori (Matching legacy modal_pilihan_kategori) */}
            {showKategoriModal && (
                <div className="fixed inset-0 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4 z-50">
                    <div className="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 border border-slate-100 animate-in fade-in zoom-in duration-150">
                        <div className="flex justify-between items-center mb-3">
                            <h3 className="text-base font-black text-slate-800">
                                Pilih Kategori Belanja
                            </h3>
                            <button onClick={() => setShowKategoriModal(false)} className="text-slate-400 hover:text-slate-600 cursor-pointer">
                                <X className="w-5 h-5" />
                            </button>
                        </div>
                        <p className="text-xs text-slate-500 mb-5">
                            Silakan pilih jenis kategori belanja terlebih dahulu untuk menyesuaikan spesifikasi dokumen SPJ.
                        </p>
                        <div className="space-y-3">
                            <button
                                type="button"
                                onClick={() => openCreateWithKategori('Peralatan dan Mesin')}
                                className="w-full flex items-center gap-3.5 p-3.5 border-2 border-slate-100 hover:border-blue-500 hover:bg-blue-50/50 rounded-xl text-left transition cursor-pointer group"
                            >
                                <div className="w-10 h-10 rounded-lg bg-blue-900 text-white flex items-center justify-center font-black">
                                    ⚙️
                                </div>
                                <div>
                                    <div className="text-sm font-bold text-slate-800 group-hover:text-blue-900">
                                        Peralatan & Mesin
                                    </div>
                                    <div className="text-[11px] text-slate-500">
                                        Komputer, Mebel, Alat Praktik, Kendaraan, dll.
                                    </div>
                                </div>
                            </button>
                            <button
                                type="button"
                                onClick={() => openCreateWithKategori('Buku')}
                                className="w-full flex items-center gap-3.5 p-3.5 border-2 border-slate-100 hover:border-teal-500 hover:bg-teal-50/50 rounded-xl text-left transition cursor-pointer group"
                            >
                                <div className="w-10 h-10 rounded-lg bg-teal-700 text-white flex items-center justify-center font-black">
                                    📚
                                </div>
                                <div>
                                    <div className="text-sm font-bold text-slate-800 group-hover:text-teal-900">
                                        Buku Perpustakaan / Umum
                                    </div>
                                    <div className="text-[11px] text-slate-500">
                                        Buku teks, referensi (Wajib isi No. Sertifikat / Pabrik)
                                    </div>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Modal Tambah / Edit */}
            {showModal && (
                <div className="fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-50">
                    <div className="bg-white rounded-2xl shadow-xl max-w-xl w-full p-6 max-h-[90vh] overflow-y-auto">
                        <div className="flex justify-between items-center mb-4 pb-2 border-b border-slate-100">
                            <h2 className="text-lg font-black text-slate-800">
                                {editingItem ? 'Edit SPJ Belanja Modal' : 'Input SPJ Belanja Modal'}
                            </h2>
                            <button onClick={() => setShowModal(false)} className="text-slate-400 hover:text-slate-600 cursor-pointer">
                                <X className="w-5 h-5" />
                            </button>
                        </div>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            {/* Section 1: Dokumen */}
                            <div className="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-3">
                                <div className="text-xs font-black text-slate-700 uppercase tracking-wider">
                                    I. Dokumen & Administrasi
                                </div>
                                {acuanList.length > 0 && (
                                    <div>
                                        <label className="block text-xs font-bold text-slate-600 uppercase mb-1">
                                            Kodering / Uraian Acuan
                                        </label>
                                        <select
                                            value={data.acuan_id}
                                            onChange={(e) => setData('acuan_id', e.target.value)}
                                            className="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500"
                                        >
                                            <option value="">-- Pilih Kodering Acuan --</option>
                                            {acuanList.map((a) => (
                                                <option key={a.id} value={a.id}>
                                                    {a.kodering} - {a.uraian} (Rp {Number(a.nominal).toLocaleString('id-ID')})
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                )}
                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="block text-xs font-bold text-slate-600 uppercase mb-1">Nomor SPK</label>
                                        <input
                                            type="text"
                                            value={data.no_spk}
                                            onChange={(e) => setData('no_spk', e.target.value)}
                                            className="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500 font-mono"
                                            placeholder="027/SPK/01/2026"
                                            required
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-xs font-bold text-slate-600 uppercase mb-1">Nomor SP2D</label>
                                        <input
                                            type="text"
                                            value={data.no_sp2d}
                                            onChange={(e) => setData('no_sp2d', e.target.value)}
                                            className="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500"
                                            placeholder="Opsional"
                                        />
                                    </div>
                                </div>
                                <div className="grid grid-cols-3 gap-3">
                                    <div>
                                        <label className="block text-xs font-bold text-slate-600 uppercase mb-1">Sumber Dana</label>
                                        <select
                                            value={data.sumber_perolehan}
                                            onChange={(e) => setData('sumber_perolehan', e.target.value)}
                                            className="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500"
                                        >
                                            <option value="BOS Reguler">BOS Reguler</option>
                                            <option value="BOS Kinerja">BOS Kinerja</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label className="block text-xs font-bold text-slate-600 uppercase mb-1">No. BA Penerimaan</label>
                                        <input
                                            type="text"
                                            value={data.ba_no}
                                            onChange={(e) => setData('ba_no', e.target.value)}
                                            className="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500 font-mono"
                                            placeholder="002/BA-PST/2026"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-xs font-bold text-slate-600 uppercase mb-1">Tanggal BA</label>
                                        <input
                                            type="date"
                                            value={data.ba_tgl}
                                            onChange={(e) => setData('ba_tgl', e.target.value)}
                                            className="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500"
                                        />
                                    </div>
                                </div>
                            </div>

                            {/* Section 2: Rincian Barang */}
                            <div className="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-3">
                                <div className="text-xs font-black text-slate-700 uppercase tracking-wider">
                                    II. Rincian & Spesifikasi Barang
                                </div>
                                <div className="relative">
                                    <label className="block text-xs font-bold text-slate-600 uppercase mb-1">Nama Barang</label>
                                    <div className="relative">
                                        <input
                                            type="text"
                                            value={data.nama_barang}
                                            onChange={(e) => handleSearch(e.target.value)}
                                            className="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 pr-8 text-sm outline-none focus:ring-2 focus:ring-blue-500"
                                            placeholder="Ketik nama barang untuk pencarian katalog..."
                                            required
                                        />
                                        <Search className="w-4 h-4 text-slate-400 absolute right-2.5 top-2.5 pointer-events-none" />
                                    </div>
                                    {suggestions.length > 0 && (
                                        <div className="absolute top-full left-0 right-0 bg-white border border-blue-200 rounded-xl shadow-lg z-20 mt-1 max-h-48 overflow-y-auto">
                                            {suggestions.map((s, idx) => (
                                                <div
                                                    key={idx}
                                                    onClick={() => handleSelectBarang(s)}
                                                    className="p-2.5 hover:bg-blue-50 cursor-pointer border-b border-slate-100 last:border-none text-xs"
                                                >
                                                    <div className="font-bold text-slate-800">{s.nama_barang}</div>
                                                    <div className="text-[10px] text-slate-500 font-mono">{s.kode_barang} • {s.jenis_aset}</div>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                     <div className="relative">
                                         <label className="block text-xs font-bold text-slate-600 uppercase mb-1">Kode Barang</label>
                                         <input
                                             type="text"
                                             value={data.kode_barang}
                                             onChange={(e) => handleSearchKodeBarang(e.target.value)}
                                             className="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500 font-mono"
                                             placeholder="1.3.2.05..."
                                             required
                                         />
                                         {suggestions.length > 0 && (
                                             <div className="absolute left-0 right-0 top-full mt-1 bg-white border border-slate-200 rounded-xl shadow-lg z-50 max-h-48 overflow-y-auto">
                                                 {suggestions.map((s, idx) => (
                                                     <div
                                                         key={idx}
                                                         onClick={() => handleSelectBarang(s)}
                                                         className="p-2.5 hover:bg-blue-50 cursor-pointer border-b border-slate-100 last:border-none text-xs"
                                                     >
                                                         <div className="font-mono font-bold text-blue-600">{s.kode_barang}</div>
                                                         <div className="text-slate-800">{s.nama_barang} • <span className="text-slate-500 font-normal">{s.jenis_aset}</span></div>
                                                     </div>
                                                 ))}
                                             </div>
                                         )}
                                     </div>
                                    <div>
                                        <label className="block text-xs font-bold text-slate-600 uppercase mb-1">Merk / Tipe</label>
                                        <input
                                            type="text"
                                            value={data.merk_tipe}
                                            onChange={(e) => setData('merk_tipe', e.target.value)}
                                            className="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500"
                                            placeholder="Contoh: Asus Vivobook / Honda Revo"
                                        />
                                    </div>
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="block text-xs font-bold text-slate-600 uppercase mb-1">
                                            No. Sertifikat / Pabrik {data.kategori === 'Buku' && <span className="text-red-500">*</span>}
                                        </label>
                                        <input
                                            type="text"
                                            value={data.no_sertifikat}
                                            onChange={(e) => setData('no_sertifikat', e.target.value)}
                                            className="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500"
                                            placeholder={data.kategori === 'Buku' ? 'ISBN / No. Sertifikat (Wajib)' : 'Opsional'}
                                            required={data.kategori === 'Buku'}
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-xs font-bold text-slate-600 uppercase mb-1">Ukuran / Dimensi</label>
                                        <input
                                            type="text"
                                            value={data.ukuran_bangunan}
                                            onChange={(e) => setData('ukuran_bangunan', e.target.value)}
                                            className="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500"
                                            placeholder="Opsional (misal: 14 inch / PxL)"
                                        />
                                    </div>
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-slate-600 uppercase mb-1">Jenis Aset</label>
                                    <select
                                        value={data.jenis_aset}
                                        onChange={(e) => setData('jenis_aset', e.target.value)}
                                        className="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500"
                                    >
                                        <option value="Peralatan dan Mesin">Peralatan dan Mesin</option>
                                        <option value="Gedung dan Bangunan">Gedung dan Bangunan</option>
                                        <option value="Tanah">Tanah</option>
                                        <option value="Jalan, Irigasi dan Jaringan">Jalan, Irigasi dan Jaringan</option>
                                        <option value="Aset Tetap Lainnya">Aset Tetap Lainnya</option>
                                    </select>
                                </div>
                                <div className="grid grid-cols-3 gap-3">
                                    <div>
                                        <label className="block text-xs font-bold text-slate-600 uppercase mb-1">Volume</label>
                                        <input
                                            type="number"
                                            min="0.01"
                                            step="any"
                                            value={data.volume}
                                            onChange={(e) => setData('volume', Number(e.target.value))}
                                            className="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500"
                                            required
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-xs font-bold text-slate-600 uppercase mb-1">Satuan</label>
                                        <input
                                            type="text"
                                            value={data.satuan}
                                            onChange={(e) => setData('satuan', e.target.value)}
                                            className="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500"
                                            placeholder="Unit/Pcs"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-xs font-bold text-slate-600 uppercase mb-1">Harga Satuan</label>
                                        <input
                                            type="number"
                                            min="0"
                                            value={data.harga_satuan}
                                            onChange={(e) => setData('harga_satuan', Number(e.target.value))}
                                            className="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500 font-mono"
                                            required
                                        />
                                    </div>
                                </div>
                            </div>

                            <div className="bg-blue-50 p-3.5 rounded-xl border border-blue-200 text-xs text-blue-900 flex justify-between items-center">
                                <span className="font-semibold">Estimasi Nilai Perolehan:</span>
                                <span className="font-mono font-black text-blue-800 text-base">
                                    Rp {(Number(data.volume || 0) * Number(data.harga_satuan || 0)).toLocaleString('id-ID')}
                                </span>
                            </div>

                            <div className="flex justify-end gap-2 pt-2">
                                <button
                                    type="button"
                                    onClick={() => setShowModal(false)}
                                    className="px-4 py-2 border border-slate-200 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-50 cursor-pointer"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="px-5 py-2 bg-[#2563eb] text-white rounded-xl text-sm font-bold hover:bg-blue-700 cursor-pointer disabled:opacity-50"
                                >
                                    {editingItem ? 'Simpan Perubahan' : 'Simpan SPJ'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
