import { Head, useForm, router, Link } from '@inertiajs/react';
import React, { useState, useMemo } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import ConfirmDialog from '@/Components/ConfirmDialog';
import {
    ArrowLeft,
    Search,
    Save,
    CheckSquare,
    Square,
    AlertCircle,
    Info,
    Calendar,
    ChevronDown,
} from 'lucide-react';
import { formatRupiah, BULAN_LIST, getNamaBulan } from '@/Utils/format';

interface SpjItem {
    id: string;
    kode_barang: string;
    nama_barang: string;
    jenis_aset: string;
    merk_tipe?: string | null;
    satuan?: string | null;
    volume: number;
    harga_satuan: number;
    nilai_perolehan: number;
    is_realisasi: boolean;
}

interface SpkGroup {
    no_spk: string;
    no_sp2d: string;
    sumber_perolehan: string;
    ba_no: string;
    ba_tgl: string;
    total_belanja_spk: number;
    items: SpjItem[];
}

interface Props {
    kodering: string;
    bulan: number;
    paguAcuan: number;
    totalRealisasiSaatIni: number;
    sisaAnggaran: number;
    listUraian: string[];
    spkGroups: SpkGroup[];
}

export default function Tambah({
    kodering,
    bulan,
    paguAcuan,
    totalRealisasiSaatIni,
    sisaAnggaran,
    listUraian = [],
    spkGroups = [],
}: Props) {
    const [searchQuery, setSearchQuery] = useState('');
    const [showUraian, setShowUraian] = useState(false);
    const [showEmptyAlert, setShowEmptyAlert] = useState(false);
    const [selectedItemIds, setSelectedItemIds] = useState<string[]>([]);

    const { post, processing } = useForm({
        kodering,
        bulan_realisasi: bulan,
        item_ids: [] as string[],
    });

    // Map item lookup
    const allAvailableItems = useMemo(() => {
        const map = new Map<string, SpjItem>();
        spkGroups.forEach((g) => {
            g.items.forEach((it) => {
                map.set(it.id, it);
            });
        });
        return map;
    }, [spkGroups]);

    // Hitung total pilihan baru
    const totalPilihanBaru = useMemo(() => {
        return selectedItemIds.reduce((sum, id) => {
            const it = allAvailableItems.get(id);
            return sum + (it ? Number(it.nilai_perolehan) : 0);
        }, 0);
    }, [selectedItemIds, allAvailableItems]);

    const filteredGroups = useMemo(() => {
        if (!searchQuery.trim()) return spkGroups;
        const q = searchQuery.toLowerCase();
        return spkGroups
            .map((group) => {
                const spkMatch = group.no_spk.toLowerCase().includes(q);
                const matchingItems = group.items.filter(
                    (it) =>
                        spkMatch ||
                        it.nama_barang.toLowerCase().includes(q) ||
                        it.kode_barang.toLowerCase().includes(q) ||
                        (it.merk_tipe && it.merk_tipe.toLowerCase().includes(q))
                );
                if (matchingItems.length > 0) {
                    return { ...group, items: matchingItems };
                }
                return null;
            })
            .filter((g): g is SpkGroup => g !== null);
    }, [spkGroups, searchQuery]);

    const toggleItem = (id: string) => {
        setSelectedItemIds((prev) =>
            prev.includes(id) ? prev.filter((i) => i !== id) : [...prev, id]
        );
    };

    const toggleGroup = (group: SpkGroup) => {
        const selectableItemIds = group.items.filter((it) => !it.is_realisasi).map((it) => it.id);
        if (selectableItemIds.length === 0) return;

        const allSelected = selectableItemIds.every((id) => selectedItemIds.includes(id));
        if (allSelected) {
            setSelectedItemIds((prev) => prev.filter((id) => !selectableItemIds.includes(id)));
        } else {
            const toAdd = selectableItemIds.filter((id) => !selectedItemIds.includes(id));
            setSelectedItemIds((prev) => [...prev, ...toAdd]);
        }
    };

    const handleSubmit = () => {
        if (selectedItemIds.length === 0) {
            setShowEmptyAlert(true);
            return;
        }
        router.post(
            '/pelaporan-bm/input-realisasi/simpan',
            {
                kodering,
                bulan_realisasi: bulan,
                item_ids: selectedItemIds,
            },
            {
                preserveScroll: true,
            }
        );
    };

    return (
        <AppLayout title={`Input Realisasi - ${kodering}`}>
            <Head title={`Input Realisasi - ${kodering}`} />

            <div className="space-y-6 max-w-7xl mx-auto pb-16">
                {/* Floating summary bar */}
                <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div className="space-y-2">
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="px-3 py-1 bg-amber-100 text-amber-800 rounded-lg text-xs font-bold font-mono">
                                <Calendar className="w-3.5 h-3.5 inline mr-1" />
                                Bulan: {getNamaBulan(bulan)}
                            </span>

                            <div className="relative">
                                <button
                                    type="button"
                                    onClick={() => setShowUraian(!showUraian)}
                                    className="px-3 py-1 bg-blue-50 text-blue-700 border border-blue-200 rounded-lg text-xs font-bold font-mono inline-flex items-center gap-1 hover:bg-blue-100"
                                >
                                    Kode Rek: {kodering}
                                    <ChevronDown className={`w-3.5 h-3.5 transition ${showUraian ? 'rotate-180' : ''}`} />
                                </button>

                                {showUraian && (
                                    <div className="absolute top-full left-0 mt-1 z-30 bg-white border border-slate-200 shadow-xl rounded-xl p-3 w-80 text-xs text-slate-700 space-y-1">
                                        <div className="font-extrabold uppercase text-[10px] text-slate-400">
                                            Daftar Uraian Pekerjaan:
                                        </div>
                                        {listUraian.length === 0 ? (
                                            <div className="italic text-slate-400">Uraian tidak ditemukan.</div>
                                        ) : (
                                            listUraian.map((u, i) => (
                                                <div key={i} className="truncate">
                                                    {i + 1}. {u}
                                                </div>
                                            ))
                                        )}
                                    </div>
                                )}
                            </div>

                            <span className="px-3 py-1 bg-slate-100 text-slate-700 rounded-lg text-xs font-bold font-mono">
                                Pagu: {formatRupiah(paguAcuan)}
                            </span>

                            <span className="px-3 py-1 bg-blue-100 text-blue-800 rounded-lg text-xs font-bold font-mono">
                                Realisasi Saat Ini: {formatRupiah(totalRealisasiSaatIni)}
                            </span>

                            <span
                                className={`px-3 py-1 rounded-lg text-xs font-bold font-mono ${
                                    sisaAnggaran <= 0
                                        ? 'bg-emerald-100 text-emerald-800'
                                        : 'bg-rose-100 text-rose-800'
                                }`}
                            >
                                Sisa Anggaran: {formatRupiah(sisaAnggaran)}
                            </span>
                        </div>
                    </div>

                    <Link
                        href={`/pelaporan-bm/input-realisasi?bulan_realisasi=${bulan}`}
                        className="inline-flex items-center gap-1.5 px-4 py-2 border border-slate-300 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50 transition"
                    >
                        <ArrowLeft className="w-4 h-4" /> Kembali ke Daftar
                    </Link>
                </div>

                {/* Instructions */}
                <div className="bg-blue-50/60 border border-blue-200 rounded-xl p-3.5 flex items-start gap-2.5 text-xs text-blue-900">
                    <Info className="w-4 h-4 text-blue-600 shrink-0 mt-0.5" />
                    <div>
                        Centang item barang dari berkas SPK yang dialokasikan ke rekening <strong>{kodering}</strong>.
                        Pastikan <strong>Total Pilihan Baru</strong> seimbang (*balance*) dengan sisa anggaran belanja modal.
                    </div>
                </div>

                {/* Search Bar */}
                <div className="relative">
                    <input
                        type="text"
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        placeholder="Cari nomor SPK, nama barang, atau spesifikasi..."
                        className="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-300 rounded-xl text-sm focus:border-blue-500 focus:outline-none"
                    />
                    <Search className="w-4 h-4 text-slate-400 absolute left-3.5 top-3" />
                </div>

                {/* Table of SPK & Items */}
                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div className="overflow-x-auto max-h-[55vh]">
                        <table className="w-full text-left text-sm border-collapse">
                            <thead className="bg-[#1e3a8a] text-white text-xs uppercase font-extrabold tracking-wider sticky top-0 z-20">
                                <tr>
                                    <th className="p-3.5 w-1/4">Informasi Dokumen Berkas (SPK)</th>
                                    <th className="p-3.5">Nama Barang / Uraian</th>
                                    <th className="p-3.5 text-center w-20">Vol</th>
                                    <th className="p-3.5 text-right w-28">Harga Satuan</th>
                                    <th className="p-3.5 text-right w-32">Total Perolehan</th>
                                    <th className="p-3.5 text-right w-32">Total SPK</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200 font-medium">
                                {filteredGroups.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="p-12 text-center text-slate-400">
                                            Tidak ada data barang belanja modal yang ditemukan pada bulan ini.
                                        </td>
                                    </tr>
                                ) : (
                                    filteredGroups.map((group, gIdx) => {
                                        const selectableItems = group.items.filter((it) => !it.is_realisasi);
                                        const allSelectableChosen =
                                            selectableItems.length > 0 &&
                                            selectableItems.every((it) => selectedItemIds.includes(it.id));
                                        const isAllDone = group.items.every((it) => it.is_realisasi);

                                        return group.items.map((item, index) => {
                                            const isFirst = index === 0;
                                            const isChosen = selectedItemIds.includes(item.id);

                                            return (
                                                <tr
                                                    key={item.id}
                                                    className={`transition ${
                                                        item.is_realisasi
                                                            ? 'bg-slate-50/70 text-slate-400'
                                                            : isChosen
                                                            ? 'bg-blue-50/60'
                                                            : 'hover:bg-slate-50/60'
                                                    }`}
                                                >
                                                    {isFirst && (
                                                        <td
                                                            rowSpan={group.items.length}
                                                            className="p-3.5 bg-slate-50/90 border-r border-slate-200 align-top text-xs"
                                                        >
                                                            <div className="flex items-center gap-2 mb-2">
                                                                <input
                                                                    type="checkbox"
                                                                    checked={isAllDone || allSelectableChosen}
                                                                    disabled={isAllDone}
                                                                    onChange={() => toggleGroup(group)}
                                                                    className="w-4 h-4 rounded text-blue-600 cursor-pointer disabled:opacity-50"
                                                                    title="Centang seluruh item di SPK ini"
                                                                />
                                                                <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-800">
                                                                    {group.sumber_perolehan}
                                                                </span>
                                                            </div>
                                                            <div className="font-mono font-bold text-slate-900 text-[11px]">
                                                                SPK: {group.no_spk}
                                                            </div>
                                                            {group.ba_no && (
                                                                <div className="text-[10px] text-slate-500">
                                                                    BA: {group.ba_no}
                                                                </div>
                                                            )}
                                                            {group.ba_tgl && (
                                                                <div className="text-[10px] text-slate-400">
                                                                    Tgl: {group.ba_tgl.substring(0, 10)}
                                                                </div>
                                                            )}
                                                        </td>
                                                    )}

                                                    <td className="p-3.5 border-r border-slate-100">
                                                        <div className="flex items-start gap-2.5">
                                                            <input
                                                                type="checkbox"
                                                                checked={item.is_realisasi || isChosen}
                                                                disabled={item.is_realisasi}
                                                                onChange={() => toggleItem(item.id)}
                                                                className="w-4 h-4 mt-0.5 rounded text-blue-600 cursor-pointer disabled:opacity-50"
                                                            />
                                                            <div>
                                                                <div
                                                                    className={`font-semibold ${
                                                                        item.is_realisasi
                                                                            ? 'line-through text-slate-400'
                                                                            : 'text-slate-900'
                                                                    }`}
                                                                >
                                                                    {item.nama_barang}
                                                                    {item.is_realisasi && (
                                                                        <span className="ml-2 px-1.5 py-0.5 bg-slate-200 text-slate-600 text-[10px] rounded font-bold">
                                                                            Sudah Realisasi
                                                                        </span>
                                                                    )}
                                                                </div>
                                                                <div className="text-[11px] text-slate-500">
                                                                    {item.kode_barang} &bull; {item.jenis_aset}
                                                                    {item.merk_tipe ? ` (${item.merk_tipe})` : ''}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>

                                                    <td className="p-3.5 text-center font-mono text-xs border-r border-slate-100">
                                                        {Number(item.volume).toLocaleString('id-ID')}{' '}
                                                        <span className="text-slate-400">{item.satuan}</span>
                                                    </td>

                                                    <td className="p-3.5 text-right font-mono text-xs border-r border-slate-100">
                                                        {formatRupiah(item.harga_satuan)}
                                                    </td>

                                                    <td className="p-3.5 text-right font-mono text-xs font-bold text-blue-600 border-r border-slate-100">
                                                        {formatRupiah(item.nilai_perolehan)}
                                                    </td>

                                                    {isFirst && (
                                                        <td
                                                            rowSpan={group.items.length}
                                                            className="p-3.5 text-right font-mono text-xs font-bold text-slate-800 align-middle bg-slate-50/30"
                                                        >
                                                            {formatRupiah(group.total_belanja_spk)}
                                                        </td>
                                                    )}
                                                </tr>
                                            );
                                        });
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Grand Total Pilihan Baru & Action */}
                <div className="bg-sky-50 border-2 border-dashed border-sky-300 rounded-2xl p-6 flex flex-col md:flex-row justify-between items-center gap-4">
                    <div>
                        <span className="text-xs font-bold uppercase text-sky-800 tracking-wider">
                            Total Pilihan Baru:
                        </span>
                        <div className="text-3xl font-black font-mono text-sky-700 mt-0.5">
                            {formatRupiah(totalPilihanBaru)}
                        </div>
                        <div className="text-xs text-slate-500 mt-1">
                            Sisa anggaran yang harus dipenuhi: <strong>{formatRupiah(sisaAnggaran)}</strong>
                        </div>
                    </div>

                    <div className="flex items-center gap-3 w-full md:w-auto">
                        <Link
                            href={`/pelaporan-bm/input-realisasi?bulan_realisasi=${bulan}`}
                            className="w-full md:w-auto px-5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm font-semibold text-slate-600 text-center hover:bg-slate-100 transition"
                        >
                            Batal
                        </Link>
                        <button
                            type="button"
                            disabled={processing || selectedItemIds.length === 0}
                            onClick={handleSubmit}
                            className="w-full md:w-auto inline-flex items-center justify-center gap-2 px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-bold shadow-md transition disabled:opacity-50 cursor-pointer"
                        >
                            <Save className="w-4 h-4" />
                            {processing ? 'Menyimpan...' : 'Simpan Realisasi SPJ'}
                        </button>
                    </div>
                </div>

                <ConfirmDialog
                    isOpen={showEmptyAlert}
                    onClose={() => setShowEmptyAlert(false)}
                    onConfirm={() => setShowEmptyAlert(false)}
                    title="Belum Ada Item Dipilih"
                    message="Pilih minimal satu item barang untuk direalisasikan."
                    confirmText="Mengerti"
                    isDestructive={false}
                />
            </div>
        </AppLayout>
    );
}
