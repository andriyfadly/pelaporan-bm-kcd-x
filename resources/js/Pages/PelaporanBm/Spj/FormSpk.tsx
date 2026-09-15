import { Head, useForm, Link } from '@inertiajs/react';
import React, { useState, useRef } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Plus, Trash2, ArrowLeft, Search, FileText, Box as BoxIcon } from 'lucide-react';
import { formatRupiah } from '@/Utils/format';

interface ItemBarang {
    id?: string;
    kode_barang: string;
    nama_barang: string;
    jenis_aset: string;
    merk_tipe: string;
    no_sertifikat: string;
    ukuran_bangunan: string;
    satuan: string;
    volume: number;
    harga_satuan: number;
    nilai_perolehan: number;
}

interface Props {
    kategori: string;
    bulan: number;
    isEdit: boolean;
    spkData?: {
        no_spk: string;
        no_sp2d: string;
        sumber_perolehan: string;
        ba_no: string;
        ba_tgl: string;
        kategori: string;
        items: ItemBarang[];
    } | null;
}

export default function FormSpk({ kategori, bulan, isEdit, spkData }: Props) {
    const isBuku = kategori.toLowerCase().includes('buku');
    const defaultJenisAset = isBuku ? 'Buku' : 'PERSONAL KOMPUTER';

    const emptyItem: ItemBarang = {
        kode_barang: '',
        nama_barang: '',
        jenis_aset: defaultJenisAset,
        merk_tipe: '',
        no_sertifikat: '',
        ukuran_bangunan: '-',
        satuan: '',
        volume: 1,
        harga_satuan: 0,
        nilai_perolehan: 0,
    };

    const initialItems = spkData?.items && spkData.items.length > 0 ? spkData.items : [emptyItem];

    const { data, setData, post, processing, errors } = useForm({
        is_edit: isEdit,
        no_spk_lama: spkData?.no_spk ?? '',
        no_spk: spkData?.no_spk ?? '',
        no_sp2d: spkData?.no_sp2d ?? '',
        sumber_perolehan: spkData?.sumber_perolehan ?? 'BOS Reguler',
        bulan_realisasi: bulan,
        kategori: kategori,
        ba_no: spkData?.ba_no ?? '',
        ba_tgl: spkData?.ba_tgl ?? '',
        items: initialItems,
    });

    // Accordion state: map of index -> boolean (true = collapsed, false = expanded)
    // If edit mode with items, first is open, others collapsed; if new, first is open
    const [collapsed, setCollapsed] = useState<Record<number, boolean>>(() => {
        const state: Record<number, boolean> = {};
        initialItems.forEach((_, idx) => {
            state[idx] = idx !== initialItems.length - 1; // latest open
        });
        return state;
    });

    const [itemErrors, setItemErrors] = useState<Record<number, string>>({});
    const [activeSearchIdx, setActiveSearchIdx] = useState<number | null>(null);
    const [searchKeywords, setSearchKeywords] = useState<Record<number, string>>({});
    const [suggestions, setSuggestions] = useState<{ kode_barang: string; nama_barang: string; jenis_aset: string; satuan: string | null }[]>([]);
    const searchTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const toggleAccordion = (idx: number) => {
        setCollapsed((prev) => ({ ...prev, [idx]: !prev[idx] }));
    };

    const formatNumber = (num: number) => {
        return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(num);
    };

    const handleItemChange = (index: number, field: keyof ItemBarang, value: any) => {
        const newItems = [...data.items];
        newItems[index] = { ...newItems[index], [field]: value };
        if (field === 'volume' || field === 'harga_satuan') {
            const vol = field === 'volume' ? Number(value) : Number(newItems[index].volume);
            const hrg = field === 'harga_satuan' ? Number(value) : Number(newItems[index].harga_satuan);
            newItems[index].nilai_perolehan = vol > 0 && hrg >= 0 ? vol * hrg : 0;
        }
        setData('items', newItems);

        // Clear error if field filled
        if (itemErrors[index]) {
            setItemErrors((prev) => {
                const next = { ...prev };
                delete next[index];
                return next;
            });
        }
    };

    const handleHargaChange = (index: number, rawInput: string) => {
        const digits = rawInput.replace(/[^0-9]/g, '');
        const val = digits ? parseInt(digits, 10) : 0;
        handleItemChange(index, 'harga_satuan', val);
    };

    const handleSearchPagu = (index: number, query: string) => {
        setSearchKeywords((prev) => ({ ...prev, [index]: query }));
        if (searchTimeoutRef.current) clearTimeout(searchTimeoutRef.current);

        if (query.trim().length < 2) {
            setSuggestions([]);
            setActiveSearchIdx(null);
            return;
        }

        searchTimeoutRef.current = setTimeout(async () => {
            try {
                const res = await fetch(`/pelaporan-bm/cari-barang?q=${encodeURIComponent(query)}&kategori=${encodeURIComponent(kategori)}`);
                const json = await res.json();
                setSuggestions(json || []);
                setActiveSearchIdx(index);
            } catch {
                setSuggestions([]);
            }
        }, 250);
    };

    const selectSuggestion = (index: number, item: { kode_barang: string; nama_barang: string; jenis_aset: string; satuan: string | null }) => {
        const newItems = [...data.items];
        newItems[index] = {
            ...newItems[index],
            kode_barang: item.kode_barang,
            nama_barang: item.nama_barang,
            jenis_aset: item.jenis_aset || newItems[index].jenis_aset,
            satuan: item.satuan || newItems[index].satuan || 'UNIT',
        };
        setData('items', newItems);
        setSuggestions([]);
        setActiveSearchIdx(null);
        setSearchKeywords((prev) => ({ ...prev, [index]: '' }));
    };

    const validateItem = (index: number): boolean => {
        const item = data.items[index];
        const missing: string[] = [];

        if (!item.nama_barang.trim() || !item.kode_barang.trim()) missing.push('Pilihan Barang Dari Katalog');
        if (!item.merk_tipe.trim()) missing.push('Merk/Tipe');
        if (isBuku && !item.no_sertifikat.trim()) missing.push('No. Sertifikat/Penerbit (Wajib untuk Buku)');
        if (!item.satuan.trim()) missing.push('Satuan');
        if (!item.volume || Number(item.volume) <= 0) missing.push('Volume (QTY)');
        if (!item.harga_satuan || Number(item.harga_satuan) <= 0) missing.push('Harga Satuan');

        if (missing.length > 0) {
            setItemErrors((prev) => ({
                ...prev,
                [index]: `Gagal tambah! Mohon lengkapi field berikut: ${missing.join(', ')}`,
            }));
            return false;
        }

        setItemErrors((prev) => {
            const next = { ...prev };
            delete next[index];
            return next;
        });
        return true;
    };

    const tambahItemFormBaru = () => {
        // Collapse all previous cards
        const newCollapsed: Record<number, boolean> = {};
        data.items.forEach((_, i) => {
            newCollapsed[i] = true;
        });
        const nextIdx = data.items.length;
        newCollapsed[nextIdx] = false; // open newly added

        setData('items', [...data.items, { ...emptyItem }]);
        setCollapsed(newCollapsed);
    };

    const pemicuValidasiBarisTerakhir = () => {
        if (data.items.length > 0) {
            const lastIdx = data.items.length - 1;
            // Open last card if collapsed
            if (collapsed[lastIdx]) {
                setCollapsed((prev) => ({ ...prev, [lastIdx]: false }));
            }
            if (validateItem(lastIdx)) {
                tambahItemFormBaru();
            }
        } else {
            tambahItemFormBaru();
        }
    };

    const validasiDanTambahBaru = (currentIdx: number) => {
        if (validateItem(currentIdx)) {
            tambahItemFormBaru();
        }
    };

    const removeItem = (index: number, e: React.MouseEvent) => {
        e.stopPropagation();
        if (data.items.length <= 1) {
            alert('Minimal harus ada 1 barang untuk direalisasikan!');
            return;
        }
        const newItems = data.items.filter((_, i) => i !== index);
        setData('items', newItems);

        // adjust collapsed state
        const nextCollapsed: Record<number, boolean> = {};
        newItems.forEach((_, i) => {
            nextCollapsed[i] = i !== newItems.length - 1;
        });
        setCollapsed(nextCollapsed);
    };

    const grandTotal = data.items.reduce((acc, it) => acc + (Number(it.volume) * Number(it.harga_satuan) || 0), 0);

    const bersihkanFormatSebelumSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!data.ba_no.trim()) {
            alert('Nomor Berita Acara (BA NO) belum diisi!');
            return;
        }

        if (!data.ba_tgl.trim()) {
            alert('Tanggal Berita Acara (BA TGL) belum diisi!');
            return;
        }

        if (data.items.length === 0) {
            alert('Minimal harus ada 1 barang untuk direalisasikan!');
            return;
        }

        for (let i = 0; i < data.items.length; i++) {
            if (!validateItem(i)) {
                setCollapsed((prev) => ({ ...prev, [i]: false }));
                return;
            }
        }

        post('/pelaporan-bm/spj/store-spk');
    };

    const bgColor = isBuku ? '#dcfce7' : '#e0f2fe';
    const borderColor = isBuku ? '#22c55e' : '#0ea5e9';
    const textColor = isBuku ? '#166534' : '#075985';

    return (
        <AppLayout title={`${isEdit ? 'Edit' : 'Tambah'} Dokumen SPJ - ${kategori}`}>
            <Head title={`${isEdit ? 'Edit' : 'Tambah'} Dokumen SPJ`} />

            <div className="spj-container py-3 max-w-6xl mx-auto font-sans" style={{ background: '#fdfdfd' }}>
                {/* LABEL STICKY KATEGORI */}
                <div
                    style={{
                        position: 'sticky',
                        top: '10px',
                        zIndex: 1050,
                        display: 'inline-block',
                        backgroundColor: bgColor,
                        border: `2px solid ${borderColor}`,
                        borderRadius: '8px',
                        padding: '10px 20px',
                        marginBottom: '20px',
                        boxShadow: '0 4px 6px rgba(0,0,0,0.1)',
                    }}
                >
                    <span style={{ fontWeight: 800, color: textColor }}>
                        KATEGORI SPJ: {kategori.toUpperCase()}
                    </span>
                </div>

                <form onSubmit={bersihkanFormatSebelumSubmit}>
                    {/* BAGIAN I: DOKUMEN & ADMINISTRASI KEUANGAN SPJ */}
                    <div
                        className="mb-3 flex items-center shadow-sm"
                        style={{
                            backgroundColor: '#0b3c7c',
                            color: 'white',
                            fontWeight: 700,
                            fontSize: '13px',
                            letterSpacing: '0.5px',
                            borderRadius: '6px',
                            padding: '12px 16px',
                        }}
                    >
                        <FileText className="w-4 h-4 mr-2" /> I. DOKUMEN & ADMINISTRASI KEUANGAN SPJ
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-12 gap-3 mb-6 px-1">
                        <div className="md:col-span-4">
                            <label className="block font-bold text-[11px] text-slate-700 uppercase mb-1.5">
                                No. SP2D
                            </label>
                            <input
                                type="text"
                                value={data.no_sp2d}
                                onChange={(e) => setData('no_sp2d', e.target.value)}
                                placeholder="Boleh dikosongkan (Opsional)"
                                className="w-full px-3 py-2 text-[13px] text-slate-700 bg-white rounded-lg focus:outline-none"
                                style={{ border: '1px solid #7dd3fc' }}
                            />
                        </div>

                        <div className="md:col-span-4">
                            <label className="block font-bold text-[11px] text-slate-700 uppercase mb-1.5">
                                Sumber Perolehan *
                            </label>
                            <select
                                value={data.sumber_perolehan}
                                onChange={(e) => setData('sumber_perolehan', e.target.value)}
                                required
                                className="w-full px-3 py-2 text-[13px] text-slate-700 bg-white rounded-lg focus:outline-none"
                                style={{ border: '1px solid #7dd3fc' }}
                            >
                                <option value="BOS Reguler">BOS Reguler</option>
                                <option value="BOS Kinerja">BOS Kinerja</option>
                            </select>
                        </div>

                        <div className="md:col-span-4">
                            <label className="block font-bold text-[11px] text-slate-700 uppercase mb-1.5">
                                No. SPK / Kwitansi *
                            </label>
                            <input
                                type="text"
                                required
                                value={data.no_spk}
                                onChange={(e) => setData('no_spk', e.target.value)}
                                placeholder="Nomor Kwitansi/Nota Belanja"
                                className="w-full px-3 py-2 text-[13px] text-slate-700 bg-white rounded-lg focus:outline-none font-semibold"
                                style={{ border: '1px solid #7dd3fc' }}
                            />
                            {errors.no_spk && <p className="text-red-500 text-xs mt-1">{errors.no_spk}</p>}
                        </div>

                        <div className="md:col-span-8">
                            <label className="block font-bold text-[11px] text-slate-700 uppercase mb-1.5">
                                Nomor Berita Acara Penerimaan (BA NO) *
                            </label>
                            <input
                                type="text"
                                required
                                value={data.ba_no}
                                onChange={(e) => setData('ba_no', e.target.value)}
                                placeholder="Masukkan nomor berita acara..."
                                className="w-full px-3 py-2 text-[13px] text-slate-700 bg-white rounded-lg focus:outline-none"
                                style={{ border: '1px solid #7dd3fc' }}
                            />
                        </div>

                        <div className="md:col-span-4">
                            <label className="block font-bold text-[11px] text-slate-700 uppercase mb-1.5">
                                Tanggal BA (BA TGL) *
                            </label>
                            <input
                                type="date"
                                required
                                value={data.ba_tgl}
                                onChange={(e) => setData('ba_tgl', e.target.value)}
                                className="w-full px-3 py-2 text-[13px] text-slate-700 bg-white rounded-lg focus:outline-none"
                                style={{ border: '1px solid #7dd3fc' }}
                            />
                        </div>
                    </div>

                    {/* BAGIAN II & III: DETAIL ITEM BARANG */}
                    <div className="flex flex-row items-center justify-between gap-2 mb-3 px-1">
                        <div
                            className="flex-1 flex items-center shadow-sm"
                            style={{
                                backgroundColor: '#008080',
                                color: 'white',
                                fontWeight: 700,
                                fontSize: '13px',
                                borderRadius: '6px',
                                padding: '12px 16px',
                            }}
                        >
                            <BoxIcon className="w-4 h-4 mr-2" /> II & III. DETAIL ITEM BARANG UNTUK SPJ INI
                        </div>
                        <button
                            type="button"
                            id="btn_tambah_atas"
                            onClick={pemicuValidasiBarisTerakhir}
                            className="font-bold text-white px-4 flex items-center shadow-sm hover:opacity-90 transition"
                            style={{
                                backgroundColor: '#0284c7',
                                border: 'none',
                                borderRadius: '6px',
                                padding: '12px 16px',
                                fontSize: '13px',
                            }}
                        >
                            <Plus className="w-4 h-4 mr-1.5" /> Tambah Item Barang
                        </button>
                    </div>

                    {/* WRAPPER CONTAINER ITEMS */}
                    <div id="wrapper_container_items" className="space-y-4 mb-4">
                        {data.items.map((item, index) => {
                            const isCardCollapsed = collapsed[index] ?? false;
                            const subtotalItem = Number(item.volume) * Number(item.harga_satuan) || 0;
                            const headLabel = item.nama_barang ? `ITEM: ${item.nama_barang}` : 'ITEM BARANG BARU';

                            return (
                                <div
                                    key={index}
                                    className="accordion-item-barang bg-white shadow-sm overflow-hidden"
                                    style={{
                                        border: '1px solid #7dd3fc',
                                        borderRadius: '12px',
                                        marginBottom: '20px',
                                    }}
                                >
                                    {/* ACCORDION HEADER */}
                                    <div
                                        onClick={() => toggleAccordion(index)}
                                        className="accordion-header-custom flex justify-between items-center cursor-pointer transition select-none"
                                        style={{
                                            backgroundColor: '#f0f9ff',
                                            padding: '14px 20px',
                                            borderBottom: isCardCollapsed ? 'none' : '1px solid #7dd3fc',
                                        }}
                                    >
                                        <div className="flex items-center gap-2">
                                            <span
                                                className="rounded-full bg-blue-600 text-white flex items-center justify-center font-extrabold text-[11px] shrink-0"
                                                style={{ width: '24px', height: '24px' }}
                                            >
                                                {index + 1}
                                            </span>
                                            <div className="flex flex-col">
                                                <span className="font-bold text-slate-600 text-xs uppercase tracking-wide">
                                                    {headLabel}
                                                </span>
                                                <span className="text-slate-400 text-[11px] font-semibold">
                                                    Merk/Tipe:{' '}
                                                    <span className="text-blue-600 font-bold">
                                                        {item.merk_tipe || '-'}
                                                    </span>
                                                </span>
                                            </div>
                                        </div>

                                        <div className="flex items-center gap-3">
                                            <span className="font-extrabold text-slate-800 text-sm">
                                                Rp {formatNumber(subtotalItem)}
                                            </span>
                                            <button
                                                type="button"
                                                onClick={(e) => removeItem(index, e)}
                                                className="p-1 text-rose-500 hover:bg-rose-50 rounded transition"
                                                title="Hapus baris barang ini"
                                            >
                                                <Trash2 className="w-4 h-4" />
                                            </button>
                                        </div>
                                    </div>

                                    {/* ACCORDION BODY */}
                                    {!isCardCollapsed && (
                                        <div className="accordion-body-custom p-5 space-y-4">
                                            {/* Pencarian Katalog */}
                                            <div className="relative wrapper-cari">
                                                <label className="block font-bold text-[11px] text-slate-700 uppercase mb-1.5 flex items-center gap-1.5">
                                                    <Search className="w-3.5 h-3.5" /> Cari Nama Barang / Kode Aset Dari Katalog Pagu
                                                </label>
                                                <input
                                                    type="text"
                                                    value={searchKeywords[index] ?? ''}
                                                    onChange={(e) => handleSearchPagu(index, e.target.value)}
                                                    placeholder="Ketik nama barang yang dicari..."
                                                    className="w-full px-3 py-2 text-[13px] text-slate-700 bg-white rounded-lg focus:outline-none"
                                                    style={{ border: '1px solid #7dd3fc' }}
                                                />

                                                {activeSearchIdx === index && suggestions.length > 0 && (
                                                    <div
                                                        className="absolute top-full left-0 right-0 z-50 bg-white border border-slate-300 rounded-b-lg shadow-xl max-h-60 overflow-y-auto mt-0.5"
                                                    >
                                                        {suggestions.map((sug, sIdx) => (
                                                            <div
                                                                key={sIdx}
                                                                onClick={() => selectSuggestion(index, sug)}
                                                                className="p-3 hover:bg-sky-50 cursor-pointer border-b border-slate-100 last:border-0"
                                                            >
                                                                <div className="font-bold text-[14px] text-slate-900">
                                                                    {sug.nama_barang}
                                                                </div>
                                                                <div className="mt-1 flex items-center gap-2">
                                                                    <span className="px-2 py-0.5 bg-slate-100 border border-slate-200 text-slate-700 rounded text-[11px] font-mono">
                                                                        {sug.kode_barang}
                                                                    </span>
                                                                    <span className="px-2 py-0.5 bg-slate-200 text-slate-700 rounded text-[11px] font-bold">
                                                                        {sug.jenis_aset}
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        ))}
                                                    </div>
                                                )}
                                            </div>

                                            {/* Baris Kode & Nama Barang */}
                                            <div className="grid grid-cols-1 md:grid-cols-12 gap-3">
                                                <div className="md:col-span-4">
                                                    <label className="block font-bold text-[11px] text-slate-700 uppercase mb-1.5">
                                                        Kode Barang (Asset)
                                                    </label>
                                                    <input
                                                        type="text"
                                                        readOnly
                                                        value={item.kode_barang}
                                                        className="w-full px-3 py-2 text-[13px] rounded-lg font-semibold"
                                                        style={{
                                                            backgroundColor: '#e2e8f0',
                                                            color: '#475569',
                                                            border: '1px solid #cbd5e1',
                                                            cursor: 'not-allowed',
                                                        }}
                                                    />
                                                </div>
                                                <div className="md:col-span-8">
                                                    <label className="block font-bold text-[11px] text-slate-700 uppercase mb-1.5">
                                                        Nama Barang / Uraian
                                                    </label>
                                                    <input
                                                        type="text"
                                                        readOnly
                                                        required
                                                        value={item.nama_barang}
                                                        className="w-full px-3 py-2 text-[13px] rounded-lg font-semibold"
                                                        style={{
                                                            backgroundColor: '#e2e8f0',
                                                            color: '#475569',
                                                            border: '1px solid #cbd5e1',
                                                            cursor: 'not-allowed',
                                                        }}
                                                    />
                                                </div>
                                            </div>

                                            {/* Baris Jenis Aset, Merk/Tipe, No Sertifikat */}
                                            <div className="grid grid-cols-1 md:grid-cols-12 gap-3">
                                                <div className="md:col-span-4">
                                                    <label className="block font-bold text-[11px] text-slate-700 uppercase mb-1.5">
                                                        Jenis Aset
                                                    </label>
                                                    <input
                                                        type="text"
                                                        readOnly
                                                        required
                                                        value={item.jenis_aset}
                                                        className="w-full px-3 py-2 text-[13px] rounded-lg font-semibold"
                                                        style={{
                                                            backgroundColor: '#e2e8f0',
                                                            color: '#475569',
                                                            border: '1px solid #cbd5e1',
                                                            cursor: 'not-allowed',
                                                        }}
                                                    />
                                                </div>
                                                <div className="md:col-span-4">
                                                    <label className="block font-bold text-[11px] text-slate-700 uppercase mb-1.5">
                                                        Merk / Tipe *
                                                    </label>
                                                    <input
                                                        type="text"
                                                        required
                                                        value={item.merk_tipe}
                                                        onChange={(e) => handleItemChange(index, 'merk_tipe', e.target.value)}
                                                        placeholder="Contoh: Lenovo Core i3"
                                                        className="w-full px-3 py-2 text-[13px] text-slate-700 bg-white rounded-lg focus:outline-none"
                                                        style={{ border: '1px solid #7dd3fc' }}
                                                    />
                                                </div>
                                                <div className="md:col-span-4">
                                                    <label className="block font-bold text-[11px] text-slate-700 uppercase mb-1.5">
                                                        No. Sertifikat / Pabrik / Penerbit{' '}
                                                        <span className="text-red-500">{isBuku ? '*' : ''}</span>
                                                    </label>
                                                    <input
                                                        type="text"
                                                        required={isBuku}
                                                        value={item.no_sertifikat}
                                                        onChange={(e) => handleItemChange(index, 'no_sertifikat', e.target.value)}
                                                        placeholder={isBuku ? 'Wajib Diisi (Kategori Buku)' : 'Wajib jika kategori Buku / Pabrik'}
                                                        className="w-full px-3 py-2 text-[13px] text-slate-700 bg-white rounded-lg focus:outline-none"
                                                        style={{ border: '1px solid #7dd3fc' }}
                                                    />
                                                </div>
                                            </div>

                                            {/* Baris Ukuran, Satuan, Qty, Harga Satuan */}
                                            <div className="grid grid-cols-1 md:grid-cols-12 gap-3">
                                                <div className="md:col-span-4">
                                                    <label className="block font-bold text-[11px] text-slate-700 uppercase mb-1.5">
                                                        Ukuran / Dimensi Bangunan
                                                    </label>
                                                    <input
                                                        type="text"
                                                        value={item.ukuran_bangunan}
                                                        onChange={(e) => handleItemChange(index, 'ukuran_bangunan', e.target.value)}
                                                        placeholder="-"
                                                        className="w-full px-3 py-2 text-[13px] text-slate-700 bg-white rounded-lg focus:outline-none"
                                                        style={{ border: '1px solid #7dd3fc' }}
                                                    />
                                                </div>
                                                <div className="md:col-span-2">
                                                    <label className="block font-bold text-[11px] text-slate-700 uppercase mb-1.5">
                                                        Satuan *
                                                    </label>
                                                    <input
                                                        type="text"
                                                        required
                                                        value={item.satuan}
                                                        onChange={(e) => handleItemChange(index, 'satuan', e.target.value)}
                                                        placeholder="Pcs / Unit / Rim"
                                                        className="w-full px-3 py-2 text-[13px] text-slate-700 bg-white rounded-lg focus:outline-none"
                                                        style={{ border: '1px solid #7dd3fc' }}
                                                    />
                                                </div>
                                                <div className="md:col-span-3">
                                                    <label className="block font-bold text-[11px] text-slate-700 uppercase mb-1.5">
                                                        Volume (QTY) *
                                                    </label>
                                                    <input
                                                        type="number"
                                                        step="any"
                                                        min="0"
                                                        required
                                                        value={item.volume}
                                                        onChange={(e) => handleItemChange(index, 'volume', e.target.value)}
                                                        placeholder="Qty"
                                                        className="w-full px-3 py-2 text-[13px] text-slate-700 bg-white rounded-lg text-center font-bold focus:outline-none"
                                                        style={{ border: '1px solid #7dd3fc' }}
                                                    />
                                                </div>
                                                <div className="md:col-span-3">
                                                    <label className="block font-bold text-[11px] text-slate-700 uppercase mb-1.5">
                                                        Harga Satuan (RP) *
                                                    </label>
                                                    <input
                                                        type="text"
                                                        required
                                                        value={item.harga_satuan ? formatNumber(item.harga_satuan) : ''}
                                                        onChange={(e) => handleHargaChange(index, e.target.value)}
                                                        placeholder="Rp 0"
                                                        className="w-full px-3 py-2 text-[13px] text-slate-700 bg-white rounded-lg text-right font-bold focus:outline-none"
                                                        style={{ border: '1px solid #7dd3fc' }}
                                                    />
                                                </div>
                                            </div>

                                            {/* Subtotal & Validasi Error */}
                                            <div className="flex flex-col md:flex-row justify-between items-start md:items-center mt-3 pt-3 border-t border-slate-200 gap-2">
                                                <div>
                                                    {itemErrors[index] && (
                                                        <div
                                                            className="text-red-700 bg-red-50 border border-red-300 rounded px-3 py-1.5 text-xs font-semibold"
                                                        >
                                                            {itemErrors[index]}
                                                        </div>
                                                    )}
                                                </div>
                                                <div className="text-right ml-auto">
                                                    <span className="text-slate-500 font-bold text-xs uppercase mr-2">
                                                        Subtotal Item:
                                                    </span>
                                                    <span className="font-extrabold text-slate-900 text-lg">
                                                        Rp {formatNumber(subtotalItem)}
                                                    </span>
                                                </div>
                                            </div>

                                            {/* Tombol Tambah di Bawah Kartu */}
                                            <div className="flex justify-end mt-2">
                                                <button
                                                    type="button"
                                                    onClick={() => validasiDanTambahBaru(index)}
                                                    className="font-bold px-3 py-2 flex items-center shadow-sm text-white rounded-md text-xs hover:opacity-90 transition"
                                                    style={{ backgroundColor: '#0284c7' }}
                                                >
                                                    <Plus className="w-4 h-4 mr-1.5" /> Tambah Item Barang
                                                </button>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>

                    {/* TOTAL AKUMULASI REALISASI BOX */}
                    <div
                        className="total-akumulasi-box text-center mb-6 shadow-sm"
                        style={{
                            backgroundColor: '#f1f5f9',
                            border: '1px solid #cbd5e1',
                            borderRadius: '10px',
                            padding: '20px',
                        }}
                    >
                        <div
                            style={{
                                fontWeight: 800,
                                color: '#000000',
                                fontSize: '14px',
                                textTransform: 'uppercase',
                                letterSpacing: '0.5px',
                            }}
                        >
                            Total Akumulasi Realisasi
                        </div>
                        <h3
                            style={{
                                fontWeight: 800,
                                color: '#000000',
                                fontSize: '24px',
                                marginTop: '4px',
                                marginBottom: 0,
                            }}
                        >
                            Rp {formatNumber(grandTotal)}
                        </h3>
                    </div>

                    {/* FOOTER ACTIONS */}
                    <div className="flex justify-end gap-2 border-t border-slate-200 pt-4">
                        <Link
                            href={`/pelaporan-bm/spj?bulan=${bulan}`}
                            className="px-4 py-2 font-bold bg-white hover:bg-slate-50 flex items-center gap-1 text-slate-600 transition"
                            style={{
                                borderRadius: '8px',
                                border: '1px solid #cbd5e1',
                                fontSize: '13px',
                            }}
                        >
                            <ArrowLeft className="w-4 h-4" /> Kembali
                        </Link>
                        <button
                            type="submit"
                            disabled={processing}
                            className="px-5 py-2 font-bold text-white hover:opacity-90 shadow-sm transition disabled:opacity-50"
                            style={{
                                backgroundColor: '#0284c7',
                                border: 'none',
                                borderRadius: '8px',
                                fontSize: '13px',
                            }}
                        >
                            {processing ? 'Menyimpan...' : 'Simpan Realisasi'}
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
