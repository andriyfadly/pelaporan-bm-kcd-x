import { Head, router } from '@inertiajs/react';
import React, { useState, useMemo } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import {
    PieChart,
    CheckCircle2,
    AlertCircle,
    Search,
    Eye,
    Check,
    Unlock,
    Lock,
    X,
    FolderOpen,
    Plus,
    Minus,
    Hourglass,
    Calendar,
} from 'lucide-react';

interface RekeningAcuanItem {
    kodering: string;
    acuan: number;
    realisasi: number;
    kekurangan: number;
    is_match: boolean;
    uraian: string[];
}

interface LogFisikItem {
    id: string;
    no_sp2d: string;
    tanggal: string;
    bulan: string;
    tahun: string;
    bulan_realisasi: string;
    kodering: string;
    jenis_aset: string;
    kode_barang: string;
    nama_barang: string;
    merk_tipe: string;
    no_sertifikat: string;
    volume: number;
    satuan: string;
    harga_satuan: number;
    nilai_perolehan: number;
    is_locked: boolean;
}

interface RekapanItem {
    id: string;
    nama: string;
    nama_sekolah: string;
    kota_kab: string;
    npsn: string;
    bulan_disp: number;
    status: 'TUNTAS' | 'BELUM';
    status_kirim: 'Belum Kirim' | 'Menunggu Approval' | 'Disetujui';
    is_locked: boolean;
    progres: {
        match: number;
        total: number;
        is_selesai: boolean;
    };
    rekening_acuan: RekeningAcuanItem[];
    log_fisik: LogFisikItem[];
    total_acuan: number;
    total_realisasi: number;
}

interface Props {
    items: RekapanItem[];
    bulan: number;
    namaBulan: string;
    search: string;
    counter: {
        tuntas: number;
        belum: number;
    };
    totalKeseluruhanAcuan: number;
    totalKeseluruhanRealisasi: number;
}

export default function Index({
    items,
    bulan,
    namaBulan,
    counter,
}: Props) {
    const [filterWidget, setFilterWidget] = useState<'' | 'TUNTAS' | 'BELUM'>('');
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedSchoolId, setSelectedSchoolId] = useState<string | null>(null);
    const [detailTab, setDetailTab] = useState<'acuan' | 'realisasi'>('acuan');
    const [expandedKodering, setExpandedKodering] = useState<string[]>([]);

    const [modalAcc, setModalAcc] = useState<RekapanItem | null>(null);
    const [modalBukaEdit, setModalBukaEdit] = useState<RekapanItem | null>(null);

    const bulanNames = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];

    const toggleWidget = (type: 'TUNTAS' | 'BELUM') => {
        setFilterWidget((prev) => (prev === type ? '' : type));
    };

    const toggleKoderingExpand = (kodering: string) => {
        setExpandedKodering((prev) =>
            prev.includes(kodering) ? prev.filter((k) => k !== kodering) : [...prev, kodering]
        );
    };

    const handleBulanChange = (newBulan: number) => {
        setSelectedSchoolId(null);
        router.get('/pelaporan-bm/rekapan', { bulan: newBulan }, { preserveState: true });
    };

    const handleConfirmAcc = () => {
        if (!modalAcc) return;
        router.post(
            '/pelaporan-bm/kunci-laporan/status',
            {
                sekolah_id: modalAcc.id,
                bulan: bulan,
                status_kirim: 'disetujui',
            },
            {
                preserveScroll: true,
                onSuccess: () => setModalAcc(null),
            }
        );
    };

    const handleConfirmBukaEdit = () => {
        if (!modalBukaEdit) return;
        router.post(
            '/pelaporan-bm/kunci-laporan/status',
            {
                sekolah_id: modalBukaEdit.id,
                bulan: bulan,
                status_kirim: 'draft',
            },
            {
                preserveScroll: true,
                onSuccess: () => setModalBukaEdit(null),
            }
        );
    };

    // Filter baris sekolah secara instan berdasarkan input teks dan filter widget
    const filteredItems = useMemo(() => {
        const q = searchQuery.toLowerCase().trim();
        return items.filter((item) => {
            const matchQuery =
                !q ||
                item.nama.toLowerCase().includes(q) ||
                (item.npsn && item.npsn.toLowerCase().includes(q));

            const matchWidget = !filterWidget || item.status === filterWidget;

            return matchQuery && matchWidget;
        });
    }, [items, searchQuery, filterWidget]);

    const selectedSchool = useMemo(() => {
        return items.find((s) => s.id === selectedSchoolId) || null;
    }, [items, selectedSchoolId]);

    // Hitung total untuk sekolah yang dipilih pada tab acuan
    const selectedSchoolTotals = useMemo(() => {
        if (!selectedSchool) return { acuan: 0, realisasi: 0, kekurangan: 0, valid: 0, total: 0 };
        let acuan = 0;
        let realisasi = 0;
        let kekurangan = 0;
        let valid = 0;
        let total = 0;

        selectedSchool.rekening_acuan.forEach((r) => {
            acuan += r.acuan;
            realisasi += r.realisasi;
            kekurangan += r.kekurangan;
            if (r.kodering !== 'TANPA KODERING') {
                total++;
                if (r.is_match) valid++;
            }
        });

        return { acuan, realisasi, kekurangan, valid, total };
    }, [selectedSchool]);

    return (
        <AppLayout title={`Kendali Realisasi - Bulan ${namaBulan}`}>
            <Head title="Sistem Kendali Realisasi | SINVENTARIS" />

            <div className="max-w-7xl mx-auto space-y-6">
                {/* Navbar Topbar Legacy */}
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
                    <div className="flex items-center gap-2.5">
                        <PieChart className="w-6 h-6 text-blue-600" />
                        <h2 className="text-lg font-extrabold text-slate-900 tracking-tight">
                            SISTEM KENDALI REALISASI
                        </h2>
                    </div>

                    {/* Month selector dropdown */}
                    <div className="flex items-center gap-2">
                        <label className="text-xs font-bold text-slate-500 uppercase flex items-center gap-1">
                            <Calendar className="w-3.5 h-3.5" /> Ganti Bulan:
                        </label>
                        <select
                            value={bulan}
                            onChange={(e) => handleBulanChange(Number(e.target.value))}
                            className="bg-slate-50 border border-slate-300 rounded-xl px-3 py-1.5 text-xs font-bold text-slate-800 outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer"
                        >
                            {bulanNames.map((nama, idx) => (
                                <option key={idx + 1} value={idx + 1}>
                                    {nama}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                {/* Subheader & 2 Summary Clickable Filter Widgets */}
                <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-center">
                    <div className="lg:col-span-6">
                        <h1 className="text-2xl font-black text-slate-900 tracking-tight">
                            Monitoring Kendali Realisasi
                        </h1>
                        <p className="text-xs text-slate-500 font-medium mt-1">
                            Menampilkan Satuan Pendidikan Realisasi{' '}
                            <span className="inline-block px-2.5 py-0.5 bg-blue-50 text-blue-700 border border-blue-200 rounded-lg font-bold text-[11px]">
                                BULAN {namaBulan}
                            </span>
                        </p>
                    </div>

                    <div className="lg:col-span-6">
                        <div className="grid grid-cols-2 gap-4">
                            {/* Widget 1: SUDAH SELESAI */}
                            <div
                                onClick={() => toggleWidget('TUNTAS')}
                                className={`cursor-pointer p-4 rounded-2xl border transition-all flex items-center justify-between ${
                                    filterWidget === 'TUNTAS'
                                        ? 'bg-emerald-50 border-emerald-500 shadow-sm'
                                        : 'bg-white border-slate-200 hover:border-slate-300 shadow-sm'
                                }`}
                            >
                                <div>
                                    <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-0.5">
                                        SUDAH SELESAI
                                    </span>
                                    <div className="text-2xl font-black text-emerald-600">
                                        {counter.tuntas}{' '}
                                        <span className="text-xs font-bold text-slate-600">SEKOLAH</span>
                                    </div>
                                </div>
                                <CheckCircle2 className="w-8 h-8 text-emerald-500 opacity-80" />
                            </div>

                            {/* Widget 2: BELUM SELESAI */}
                            <div
                                onClick={() => toggleWidget('BELUM')}
                                className={`cursor-pointer p-4 rounded-2xl border transition-all flex items-center justify-between ${
                                    filterWidget === 'BELUM'
                                        ? 'bg-rose-50 border-rose-500 shadow-sm'
                                        : 'bg-white border-slate-200 hover:border-slate-300 shadow-sm'
                                }`}
                            >
                                <div>
                                    <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-0.5">
                                        BELUM SELESAI
                                    </span>
                                    <div className="text-2xl font-black text-rose-600">
                                        {counter.belum}{' '}
                                        <span className="text-xs font-bold text-slate-600">SEKOLAH</span>
                                    </div>
                                </div>
                                <AlertCircle className="w-8 h-8 text-rose-500 opacity-80" />
                            </div>
                        </div>
                    </div>
                </div>

                {/* Search Bar persis legacy */}
                <div className="bg-white border border-slate-200 rounded-2xl p-2.5 shadow-sm flex items-center px-4">
                    <Search className="w-4 h-4 text-slate-400 mr-2 shrink-0" />
                    <input
                        type="text"
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        placeholder="MASUKKAN NAMA SEKOLAH ATAU NPSN YANG INGIN DICARI..."
                        className="w-full text-xs font-semibold text-slate-800 placeholder-slate-400 outline-none bg-transparent"
                    />
                    {searchQuery && (
                        <button
                            onClick={() => setSearchQuery('')}
                            className="text-slate-400 hover:text-slate-600 text-xs font-bold p-1"
                        >
                            <X className="w-4 h-4" />
                        </button>
                    )}
                </div>

                {/* Main Table: Satuan Pendidikan */}
                <div className="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div className="overflow-x-auto max-h-[460px] overflow-y-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="bg-slate-50 text-slate-600 uppercase font-bold tracking-wider border-b border-slate-200 sticky top-0 z-10 text-[11px]">
                                <tr>
                                    <th className="p-3 text-center w-12">No</th>
                                    <th className="p-3 w-32">NPSN</th>
                                    <th className="p-3">Satuan Pendidikan</th>
                                    <th className="p-3 text-center w-28">Bulan Acuan</th>
                                    <th className="p-3 text-center w-28">Progres</th>
                                    <th className="p-3 text-center w-36">Status Berkas</th>
                                    <th className="p-3 text-center w-40">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 font-semibold">
                                {filteredItems.length === 0 ? (
                                    <tr>
                                        <td colSpan={7} className="p-8 text-center text-slate-400">
                                            Tidak ada data unit sekolah yang cocok dengan pencarian.
                                        </td>
                                    </tr>
                                ) : (
                                    filteredItems.map((sek, idx) => {
                                        const isSelected = selectedSchoolId === sek.id;

                                        return (
                                            <tr
                                                key={sek.id}
                                                className={`transition hover:bg-slate-50/90 ${
                                                    isSelected ? 'bg-blue-50/70 border-l-4 border-blue-600' : ''
                                                }`}
                                            >
                                                <td className="p-3 text-center text-slate-500 font-mono">
                                                    {idx + 1}
                                                </td>
                                                <td className="p-3 text-slate-700 font-mono">
                                                    {sek.npsn || '-'}
                                                </td>
                                                <td className="p-3 text-slate-900 font-bold uppercase">
                                                    {sek.nama}
                                                </td>
                                                <td className="p-3 text-center text-slate-700 font-bold uppercase">
                                                    Bulan {sek.bulan_disp}
                                                </td>
                                                <td className="p-3 text-center">
                                                    {sek.progres.is_selesai ? (
                                                        <span className="px-2.5 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-md text-[11px] font-bold">
                                                            SELESAI
                                                        </span>
                                                    ) : (
                                                        <span className="px-2.5 py-1 bg-blue-50 text-blue-700 border border-blue-200 rounded-md text-[11px] font-bold font-mono">
                                                            {sek.progres.match} / {sek.progres.total}
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="p-3 text-center">
                                                    {sek.status_kirim === 'Menunggu Approval' ? (
                                                        <span className="inline-flex items-center gap-1 px-2.5 py-1 bg-amber-50 text-amber-800 border border-amber-200 rounded-full text-[10px] font-bold">
                                                            <Hourglass className="w-3 h-3 text-amber-600" />
                                                            Wait ACC
                                                        </span>
                                                    ) : sek.status_kirim === 'Disetujui' ? (
                                                        <span className="inline-flex items-center gap-1 px-2.5 py-1 bg-emerald-600 text-white rounded-full text-[10px] font-bold">
                                                            <Lock className="w-3 h-3" />
                                                            Disetujui
                                                        </span>
                                                    ) : (
                                                        <span className="px-2.5 py-1 bg-slate-100 text-slate-600 rounded-full text-[10px] font-medium">
                                                            Belum Kirim
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="p-3 text-center">
                                                    <div className="flex items-center justify-center gap-1">
                                                        <button
                                                            onClick={() =>
                                                                setSelectedSchoolId(
                                                                    isSelected ? null : sek.id
                                                                )
                                                            }
                                                            className={`inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-bold transition shadow-xs cursor-pointer ${
                                                                isSelected
                                                                    ? 'bg-slate-800 text-white'
                                                                    : 'bg-blue-600 text-white hover:bg-blue-700'
                                                            }`}
                                                        >
                                                            <Eye className="w-3.5 h-3.5" />
                                                            {isSelected ? 'Tutup' : 'Lihat'}
                                                        </button>

                                                        {sek.status_kirim === 'Menunggu Approval' && (
                                                            <button
                                                                onClick={() => setModalAcc(sek)}
                                                                className="inline-flex items-center gap-1 px-2 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold transition shadow-xs cursor-pointer"
                                                                title="Setujui Laporan"
                                                            >
                                                                <Check className="w-3.5 h-3.5" /> ACC
                                                            </button>
                                                        )}

                                                        {(sek.status_kirim === 'Menunggu Approval' ||
                                                            sek.status_kirim === 'Disetujui') && (
                                                            <button
                                                                onClick={() => setModalBukaEdit(sek)}
                                                                className="inline-flex items-center gap-1 px-2 py-1.5 bg-amber-400 hover:bg-amber-500 text-slate-900 rounded-lg text-xs font-bold transition shadow-xs cursor-pointer"
                                                                title="Buka Kunci Edit User"
                                                            >
                                                                <Unlock className="w-3.5 h-3.5" /> Buka Edit
                                                            </button>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* DETAIL DRILL-DOWN PANEL (Muncul saat user klik tombol "Lihat") */}
                {selectedSchool && (
                    <div className="space-y-4 pt-2">
                        {/* Header Panel Detail */}
                        <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                            <div>
                                <h3 className="text-base font-bold text-slate-800 flex items-center gap-2">
                                    <FolderOpen className="w-5 h-5 text-blue-600" />
                                    Rincian Rekening Acuan & Input Realisasi (Bulan {namaBulan})
                                </h3>
                                <p className="text-xs text-slate-500 font-medium mt-0.5">
                                    Satuan Pendidikan:{' '}
                                    <strong className="text-slate-800 uppercase">
                                        {selectedSchool.nama}
                                    </strong>
                                </p>
                                <div className="flex gap-2 mt-2">
                                    <span className="px-2 py-0.5 bg-slate-100 text-slate-700 rounded text-[10px] font-bold">
                                        Mode Admin: Kontrol Penuh Akses
                                    </span>
                                    <span className="px-2 py-0.5 bg-slate-800 text-white rounded text-[10px] font-bold">
                                        Status: {selectedSchool.status_kirim.toUpperCase()}
                                    </span>
                                </div>
                            </div>

                            <div className="flex items-center gap-2">
                                {selectedSchool.status_kirim === 'Menunggu Approval' && (
                                    <button
                                        onClick={() => setModalAcc(selectedSchool)}
                                        className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 text-white rounded-xl text-xs font-bold hover:bg-emerald-700 transition cursor-pointer"
                                    >
                                        <Check className="w-4 h-4" /> ACC Laporan
                                    </button>
                                )}

                                {(selectedSchool.status_kirim === 'Menunggu Approval' ||
                                    selectedSchool.status_kirim === 'Disetujui') && (
                                    <button
                                        onClick={() => setModalBukaEdit(selectedSchool)}
                                        className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-400 text-slate-900 rounded-xl text-xs font-bold hover:bg-amber-500 transition cursor-pointer"
                                    >
                                        <Unlock className="w-4 h-4" /> Buka Kunci Edit User
                                    </button>
                                )}

                                <button
                                    onClick={() => setSelectedSchoolId(null)}
                                    className="inline-flex items-center gap-1.5 px-3 py-1.5 border border-slate-200 text-slate-600 rounded-xl text-xs font-semibold hover:bg-slate-50 transition cursor-pointer"
                                >
                                    <X className="w-4 h-4" /> Tutup Detail
                                </button>
                            </div>
                        </div>

                        {/* Detail Subtabs */}
                        <div className="flex gap-2 border-b border-slate-200 pb-2">
                            <button
                                onClick={() => setDetailTab('acuan')}
                                className={`px-4 py-2 rounded-xl text-xs font-bold transition cursor-pointer ${
                                    detailTab === 'acuan'
                                        ? 'bg-blue-600 text-white shadow-sm'
                                        : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'
                                }`}
                            >
                                REKENING ACUAN
                            </button>
                            <button
                                onClick={() => setDetailTab('realisasi')}
                                className={`px-4 py-2 rounded-xl text-xs font-bold transition cursor-pointer ${
                                    detailTab === 'realisasi'
                                        ? 'bg-blue-600 text-white shadow-sm'
                                        : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'
                                }`}
                            >
                                INPUT REALISASI (LOG FISIK)
                            </button>
                        </div>

                        {/* Tab 1: REKENING ACUAN */}
                        {detailTab === 'acuan' && (
                            <div className="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                                <div className="p-4 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
                                    <h4 className="text-xs font-bold text-slate-700 uppercase tracking-wide">
                                        Daftar Rekening Belanja Modal
                                    </h4>
                                    <span className="px-3 py-1 bg-blue-600 text-white rounded-lg text-xs font-bold">
                                        {selectedSchool.nama}
                                    </span>
                                </div>

                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-xs">
                                        <thead className="bg-slate-50 text-slate-600 uppercase font-bold tracking-wider border-b border-slate-200 text-[11px]">
                                            <tr>
                                                <th className="p-3.5 w-1/3">Kode Rekening</th>
                                                <th className="p-3.5 text-right w-1/5">Nilai Acuan</th>
                                                <th className="p-3.5 text-right w-1/5">Realisasi</th>
                                                <th className="p-3.5 text-right w-1/6">Kekurangan</th>
                                                <th className="p-3.5 text-center w-28">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100">
                                            {selectedSchool.rekening_acuan.length === 0 ? (
                                                <tr>
                                                    <td colSpan={5} className="p-8 text-center text-slate-400">
                                                        Tidak ada data target belanja acuan untuk bulan ini.
                                                    </td>
                                                </tr>
                                            ) : (
                                                selectedSchool.rekening_acuan.map((rec, rIdx) => {
                                                    const isExpanded = expandedKodering.includes(rec.kodering);

                                                    return (
                                                        <React.Fragment key={rIdx}>
                                                            <tr className="hover:bg-slate-50/80 transition">
                                                                <td className="p-3.5">
                                                                    <div className="flex items-center gap-2">
                                                                        <button
                                                                            onClick={() =>
                                                                                toggleKoderingExpand(rec.kodering)
                                                                            }
                                                                            className="w-5 h-5 rounded border border-blue-500 bg-blue-50 hover:bg-blue-600 hover:text-white text-blue-600 flex items-center justify-center transition cursor-pointer"
                                                                            title="Lihat Rincian Uraian"
                                                                        >
                                                                            {isExpanded ? (
                                                                                <Minus className="w-3 h-3" />
                                                                            ) : (
                                                                                <Plus className="w-3 h-3" />
                                                                            )}
                                                                        </button>
                                                                        <span className="font-bold text-slate-900 font-mono text-sm">
                                                                            {rec.kodering}
                                                                        </span>
                                                                    </div>
                                                                </td>
                                                                <td className="p-3.5 text-right font-bold text-slate-700 font-mono">
                                                                    Rp {Number(rec.acuan).toLocaleString('id-ID')}
                                                                </td>
                                                                <td className="p-3.5 text-right font-bold text-blue-600 font-mono">
                                                                    Rp {Number(rec.realisasi).toLocaleString('id-ID')}
                                                                </td>
                                                                <td
                                                                    className={`p-3.5 text-right font-bold font-mono ${
                                                                        rec.kekurangan <= 0
                                                                            ? 'text-emerald-600'
                                                                            : 'text-rose-600'
                                                                    }`}
                                                                >
                                                                    Rp {Number(rec.kekurangan).toLocaleString('id-ID')}
                                                                </td>
                                                                <td className="p-3.5 text-center">
                                                                    <span
                                                                        className={`px-2.5 py-1 rounded text-[10px] font-bold uppercase border ${
                                                                            rec.is_match
                                                                                ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                                                                : 'bg-rose-50 text-rose-700 border-rose-200'
                                                                        }`}
                                                                    >
                                                                        {rec.is_match ? 'SESUAI' : 'BELUM SESUAI'}
                                                                    </span>
                                                                </td>
                                                            </tr>
                                                            {isExpanded && (
                                                                <tr className="bg-slate-50/80">
                                                                    <td colSpan={5} className="p-4 pl-10">
                                                                        <div className="border-l-2 border-blue-500 pl-3">
                                                                            <span className="text-[11px] font-bold text-slate-600 uppercase block mb-1">
                                                                                Daftar Uraian Pekerjaan:
                                                                            </span>
                                                                            {rec.uraian.length === 0 ? (
                                                                                <p className="text-xs text-slate-400">
                                                                                    Tidak ada rincian uraian.
                                                                                </p>
                                                                            ) : (
                                                                                <ol className="list-decimal pl-4 space-y-1 text-xs text-slate-700">
                                                                                    {rec.uraian.map((u, uIdx) => (
                                                                                        <li key={uIdx}>{u}</li>
                                                                                    ))}
                                                                                </ol>
                                                                            )}
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            )}
                                                        </React.Fragment>
                                                    );
                                                })
                                            )}
                                        </tbody>
                                        <tfoot className="bg-slate-50 border-t-2 border-slate-300 font-bold text-xs">
                                            <tr>
                                                <td className="p-3.5 text-slate-900">TOTAL</td>
                                                <td className="p-3.5 text-right font-mono text-slate-900">
                                                    Rp {Number(selectedSchoolTotals.acuan).toLocaleString('id-ID')}
                                                </td>
                                                <td className="p-3.5 text-right font-mono text-blue-600">
                                                    Rp {Number(selectedSchoolTotals.realisasi).toLocaleString('id-ID')}
                                                </td>
                                                <td
                                                    className={`p-3.5 text-right font-mono ${
                                                        selectedSchoolTotals.kekurangan <= 0
                                                            ? 'text-emerald-600'
                                                            : 'text-rose-600'
                                                    }`}
                                                >
                                                    Rp {Number(selectedSchoolTotals.kekurangan).toLocaleString('id-ID')}
                                                </td>
                                                <td className="p-3.5 text-center text-slate-500 font-mono text-[11px]">
                                                    {selectedSchoolTotals.valid} / {selectedSchoolTotals.total} VALID
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        )}

                        {/* Tab 2: INPUT REALISASI (LOG FISIK) */}
                        {detailTab === 'realisasi' && (
                            <div className="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                                <div className="p-4 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
                                    <h4 className="text-xs font-bold text-slate-700 uppercase tracking-wide">
                                        Log Fisik Realisasi Input User
                                    </h4>
                                    {selectedSchool.status_kirim === 'Menunggu Approval' ||
                                    selectedSchool.status_kirim === 'Disetujui' ? (
                                        <button
                                            onClick={() => setModalBukaEdit(selectedSchool)}
                                            className="px-3 py-1 bg-amber-400 text-slate-900 rounded-lg text-xs font-bold hover:bg-amber-500 transition cursor-pointer"
                                        >
                                            <Unlock className="w-3.5 h-3.5 inline mr-1" /> Buka Kunci Edit
                                        </button>
                                    ) : (
                                        <span className="px-2.5 py-1 bg-emerald-50 text-emerald-700 rounded-md text-[11px] font-bold">
                                            Status Pengisian: Terbuka
                                        </span>
                                    )}
                                </div>

                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-xs min-w-[1200px]">
                                        <thead className="bg-slate-50 text-slate-600 uppercase font-bold tracking-wider border-b border-slate-200 text-[11px]">
                                            <tr>
                                                <th className="p-3">ID</th>
                                                <th className="p-3">No SP2D</th>
                                                <th className="p-3 text-center">Tgl</th>
                                                <th className="p-3 text-center">Bln</th>
                                                <th className="p-3 text-center">Thn</th>
                                                <th className="p-3">Kodering</th>
                                                <th className="p-3">Jenis Aset</th>
                                                <th className="p-3">Kode & Nama Barang</th>
                                                <th className="p-3">Merk / Tipe</th>
                                                <th className="p-3">No Sertifikat</th>
                                                <th className="p-3 text-center">Vol</th>
                                                <th className="p-3 text-right">Harga</th>
                                                <th className="p-3 text-right">Total Nilai</th>
                                                <th className="p-3 text-center">Kunci</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100 font-medium">
                                            {selectedSchool.log_fisik.length === 0 ? (
                                                <tr>
                                                    <td colSpan={14} className="p-8 text-center text-slate-400">
                                                        Belum ada rincian log input fisik realisasi pada bulan acuan ini.
                                                    </td>
                                                </tr>
                                            ) : (
                                                selectedSchool.log_fisik.map((log) => (
                                                    <tr key={log.id} className="hover:bg-slate-50/80 transition">
                                                        <td className="p-3 font-mono text-[11px] text-slate-400">
                                                            #{log.id.substring(0, 6)}
                                                        </td>
                                                        <td className="p-3 font-bold text-slate-800">
                                                            {log.no_sp2d}
                                                        </td>
                                                        <td className="p-3 text-center text-slate-600">
                                                            {log.tanggal}
                                                        </td>
                                                        <td className="p-3 text-center text-slate-600">
                                                            {log.bulan}
                                                        </td>
                                                        <td className="p-3 text-center text-slate-600">
                                                            {log.tahun}
                                                        </td>
                                                        <td className="p-3 font-mono font-bold text-slate-700">
                                                            {log.kodering}
                                                        </td>
                                                        <td className="p-3 text-slate-600">
                                                            {log.jenis_aset}
                                                        </td>
                                                        <td className="p-3">
                                                            <div className="font-mono font-bold text-blue-600">
                                                                {log.kode_barang}
                                                            </div>
                                                            <div className="text-slate-800 uppercase text-[11px]">
                                                                {log.nama_barang}
                                                            </div>
                                                        </td>
                                                        <td className="p-3 text-slate-600">{log.merk_tipe}</td>
                                                        <td className="p-3 text-slate-600">{log.no_sertifikat}</td>
                                                        <td className="p-3 text-center text-slate-700">
                                                            {log.volume} {log.satuan}
                                                        </td>
                                                        <td className="p-3 text-right font-mono text-slate-700">
                                                            Rp {Number(log.harga_satuan).toLocaleString('id-ID')}
                                                        </td>
                                                        <td className="p-3 text-right font-mono font-bold text-blue-600">
                                                            Rp {Number(log.nilai_perolehan).toLocaleString('id-ID')}
                                                        </td>
                                                        <td className="p-3 text-center">
                                                            {log.is_locked ? (
                                                                <span className="px-2 py-0.5 bg-rose-50 text-rose-700 border border-rose-200 rounded text-[10px] font-bold">
                                                                    <Lock className="w-2.5 h-2.5 inline mr-0.5" /> Terkunci
                                                                </span>
                                                            ) : (
                                                                <span className="px-2 py-0.5 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded text-[10px] font-bold">
                                                                    <Unlock className="w-2.5 h-2.5 inline mr-0.5" /> Terbuka
                                                                </span>
                                                            )}
                                                        </td>
                                                    </tr>
                                                ))
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}
                    </div>
                )}
            </div>

            {/* Modal Konfirmasi ACC */}
            {modalAcc && (
                <div className="fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-50">
                    <div className="bg-white rounded-2xl shadow-xl max-w-sm w-full p-6 text-center">
                        <CheckCircle2 className="w-12 h-12 text-emerald-600 mx-auto mb-3" />
                        <h3 className="text-lg font-bold text-slate-800 mb-1">Konfirmasi ACC</h3>
                        <div className="bg-blue-50 text-blue-800 border border-blue-200 rounded-xl p-2.5 mb-3 text-xs font-bold uppercase">
                            {modalAcc.nama}
                        </div>
                        <p className="text-xs text-slate-500 mb-5">
                            Apakah Anda yakin ingin menyetujui (ACC) laporan realisasi unit sekolah ini?
                        </p>
                        <div className="flex justify-center gap-2">
                            <button
                                onClick={() => setModalAcc(null)}
                                className="px-4 py-2 border border-slate-200 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-50 cursor-pointer"
                            >
                                Batal
                            </button>
                            <button
                                onClick={handleConfirmAcc}
                                className="px-4 py-2 bg-emerald-600 text-white rounded-xl text-xs font-bold hover:bg-emerald-700 cursor-pointer shadow-sm"
                            >
                                Ya, Setujui
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Modal Konfirmasi Buka Edit */}
            {modalBukaEdit && (
                <div className="fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-50">
                    <div className="bg-white rounded-2xl shadow-xl max-w-sm w-full p-6 text-center">
                        <Unlock className="w-12 h-12 text-amber-500 mx-auto mb-3" />
                        <h3 className="text-lg font-bold text-slate-800 mb-1">Buka Kunci Edit?</h3>
                        <div className="bg-amber-50 text-amber-900 border border-amber-200 rounded-xl p-2.5 mb-3 text-xs font-bold uppercase">
                            {modalBukaEdit.nama}
                        </div>
                        <p className="text-xs text-slate-500 mb-5">
                            Aksi ini membuat user bisa mengisi/mengedit kembali inputan realisasi fisik mereka (Tanpa menghapus data). Lanjutkan?
                        </p>
                        <div className="flex justify-center gap-2">
                            <button
                                onClick={() => setModalBukaEdit(null)}
                                className="px-4 py-2 border border-slate-200 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-50 cursor-pointer"
                            >
                                Batal
                            </button>
                            <button
                                onClick={handleConfirmBukaEdit}
                                className="px-4 py-2 bg-amber-400 text-slate-900 rounded-xl text-xs font-bold hover:bg-amber-500 cursor-pointer shadow-sm"
                            >
                                Ya, Buka Kunci
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
