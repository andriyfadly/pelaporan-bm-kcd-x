import { Head, useForm, router, Link } from '@inertiajs/react';
import React, { useState, useRef } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Plus, Trash2, ArrowLeft, Search, Save } from 'lucide-react';
import { formatRupiah, BULAN_LIST, getNamaBulan } from '@/Utils/format';

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
    const emptyItem: ItemBarang = {
        kode_barang: '',
        nama_barang: '',
        jenis_aset: kategori === 'Buku' ? 'Aset Tetap Lainnya' : 'Peralatan dan Mesin',
        merk_tipe: '',
        no_sertifikat: '',
        ukuran_bangunan: '',
        satuan: kategori === 'Buku' ? 'Eksemplar' : 'Unit',
        volume: 1,
        harga_satuan: 0,
        nilai_perolehan: 0,
    };

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
        items: spkData?.items && spkData.items.length > 0 ? spkData.items : [emptyItem],
    });

    const [activeSuggestionIdx, setActiveSuggestionIdx] = useState<number | null>(null);
    const [suggestions, setSuggestions] = useState<{ kode_barang: string; nama_barang: string; jenis_aset: string; satuan: string | null }[]>([]);
    const searchTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const handleItemChange = (index: number, field: keyof ItemBarang, value: any) => {
        const newItems = [...data.items];
        newItems[index] = { ...newItems[index], [field]: value };
        if (field === 'volume' || field === 'harga_satuan') {
            const vol = field === 'volume' ? Number(value) : Number(newItems[index].volume);
            const hrg = field === 'harga_satuan' ? Number(value) : Number(newItems[index].harga_satuan);
            newItems[index].nilai_perolehan = (vol > 0 && hrg >= 0) ? vol * hrg : 0;
        }
        setData('items', newItems);
    };

    const addItem = () => {
        setData('items', [...data.items, { ...emptyItem }]);
    };

    const removeItem = (index: number) => {
        if (data.items.length <= 1) {
            alert('Minimal harus ada 1 item barang dalam dokumen SPK.');
            return;
        }
        setData('items', data.items.filter((_, i) => i !== index));
    };

    const handleSearchKode = (index: number, query: string) => {
        handleItemChange(index, 'nama_barang', query);
        if (searchTimeoutRef.current) clearTimeout(searchTimeoutRef.current);
        if (query.trim().length < 2) {
            setSuggestions([]);
            setActiveSuggestionIdx(null);
            return;
        }
        searchTimeoutRef.current = setTimeout(async () => {
            try {
                const res = await fetch(`/pelaporan-bm/cari-barang?q=${encodeURIComponent(query)}`);
                const json = await res.json();
                setSuggestions(json || []);
                setActiveSuggestionIdx(index);
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
            satuan: item.satuan || newItems[index].satuan,
        };
        setData('items', newItems);
        setSuggestions([]);
        setActiveSuggestionIdx(null);
    };

    const grandTotal = data.items.reduce((acc, it) => acc + (Number(it.nilai_perolehan) || 0), 0);
    const namaBulan = getNamaBulan(bulan);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/pelaporan-bm/spj/store-spk');
    };

    return (
        <AppLayout title={`${isEdit ? 'Edit' : 'Tambah'} Dokumen SPJ - ${kategori}`}>
            <Head title={`${isEdit ? 'Edit' : 'Tambah'} Dokumen SPJ`} />

            <div className="max-w-6xl mx-auto space-y-6 pb-16">
                {/* Header info */}
                <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="px-3 py-1 bg-blue-100 text-blue-700 font-bold rounded-lg text-xs tracking-wider uppercase">
                                {kategori}
                            </span>
                            <span className="px-3 py-1 bg-amber-100 text-amber-800 font-bold rounded-lg text-xs">
                                Periode: {namaBulan}
                            </span>
                        </div>
                        <h1 className="text-xl font-extrabold text-slate-800 mt-2">
                            {isEdit ? `Edit Dokumen SPK: ${data.no_spk}` : 'Form Input SPJ & Rincian Barang'}
                        </h1>
                        <p className="text-xs text-slate-500 mt-0.5">
                            Kelola dokumen SPK beserta rincian item barang yang dibelanjakan secara kolektif.
                        </p>
                    </div>
                    <Link
                        href={`/pelaporan-bm/spj?bulan=${bulan}`}
                        className="inline-flex items-center gap-2 px-4 py-2 border border-slate-300 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-50 transition"
                    >
                        <ArrowLeft className="w-4 h-4" /> Kembali
                    </Link>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* BAGIAN I: DOKUMEN & ADMINISTRASI */}
                    <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                        <div className="bg-[#0b3c7c] text-white px-5 py-3 font-bold text-xs uppercase tracking-wider flex items-center gap-2">
                            I. Dokumen & Administrasi Keuangan SPJ
                        </div>
                        <div className="p-5 grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label className="block text-xs font-bold text-slate-700 uppercase mb-1">
                                    No. SP2D (Opsional)
                                </label>
                                <input
                                    type="text"
                                    value={data.no_sp2d}
                                    onChange={(e) => setData('no_sp2d', e.target.value)}
                                    placeholder="Contoh: 0012/SP2D/2026"
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:border-blue-500 focus:outline-none"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-slate-700 uppercase mb-1">
                                    Sumber Perolehan *
                                </label>
                                <select
                                    value={data.sumber_perolehan}
                                    onChange={(e) => setData('sumber_perolehan', e.target.value)}
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white focus:border-blue-500 focus:outline-none"
                                >
                                    <option value="BOS Reguler">BOS Reguler</option>
                                    <option value="BOS Kinerja">BOS Kinerja</option>
                                    <option value="BOSP">BOSP</option>
                                    <option value="DAK Fisik">DAK Fisik</option>
                                    <option value="Lainnya">Lainnya</option>
                                </select>
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-slate-700 uppercase mb-1">
                                    No. SPK / Faktur / Kuitansi *
                                </label>
                                <input
                                    type="text"
                                    required
                                    value={data.no_spk}
                                    onChange={(e) => setData('no_spk', e.target.value)}
                                    placeholder="Nomor SPK/Faktur..."
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm font-semibold focus:border-blue-500 focus:outline-none"
                                />
                                {errors.no_spk && <p className="text-red-500 text-xs mt-1">{errors.no_spk}</p>}
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-slate-700 uppercase mb-1">
                                    BA Penerimaan No
                                </label>
                                <input
                                    type="text"
                                    value={data.ba_no}
                                    onChange={(e) => setData('ba_no', e.target.value)}
                                    placeholder="Nomor Berita Acara..."
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:border-blue-500 focus:outline-none"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-slate-700 uppercase mb-1">
                                    BA Penerimaan Tanggal
                                </label>
                                <input
                                    type="date"
                                    value={data.ba_tgl}
                                    onChange={(e) => setData('ba_tgl', e.target.value)}
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:border-blue-500 focus:outline-none"
                                />
                            </div>
                        </div>
                    </div>

                    {/* BAGIAN II: RINCIAN ITEM BARANG */}
                    <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                        <div className="bg-[#008080] text-white px-5 py-3 font-bold text-xs uppercase tracking-wider flex justify-between items-center">
                            <span>II. Rincian Barang yang Diadakan ({data.items.length} Item)</span>
                            <button
                                type="button"
                                onClick={addItem}
                                className="inline-flex items-center gap-1.5 px-3 py-1 bg-white/20 hover:bg-white/30 text-white rounded-md text-xs font-bold transition"
                            >
                                <Plus className="w-3.5 h-3.5" /> Tambah Baris Barang
                            </button>
                        </div>

                        <div className="p-5 space-y-4">
                            {data.items.map((item, idx) => (
                                <div
                                    key={idx}
                                    className="border border-sky-200 rounded-xl p-4 bg-sky-50/20 relative space-y-3"
                                >
                                    <div className="flex justify-between items-center pb-2 border-b border-sky-100">
                                        <div className="flex items-center gap-2">
                                            <span className="w-6 h-6 rounded-full bg-blue-600 text-white font-extrabold text-xs flex items-center justify-center">
                                                {idx + 1}
                                            </span>
                                            <span className="font-bold text-sm text-slate-800">
                                                {item.nama_barang || 'Barang Baru'}
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <span className="text-xs font-bold font-mono text-blue-700 bg-white px-2.5 py-1 rounded border border-blue-200">
                                                Subtotal: {formatRupiah(item.nilai_perolehan)}
                                            </span>
                                            {data.items.length > 1 && (
                                                <button
                                                    type="button"
                                                    onClick={() => removeItem(idx)}
                                                    className="p-1.5 text-rose-500 hover:bg-rose-50 rounded-lg transition"
                                                    title="Hapus baris barang ini"
                                                >
                                                    <Trash2 className="w-4 h-4" />
                                                </button>
                                            )}
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-1 md:grid-cols-4 gap-3">
                                        {/* Nama Barang & Search */}
                                        <div className="md:col-span-2 relative">
                                            <label className="block text-[11px] font-bold text-slate-600 uppercase mb-1">
                                                Cari & Nama Barang *
                                            </label>
                                            <div className="relative">
                                                <input
                                                    type="text"
                                                    required
                                                    value={item.nama_barang}
                                                    onChange={(e) => handleSearchKode(idx, e.target.value)}
                                                    placeholder="Ketik untuk mencari master barang..."
                                                    className="w-full pl-8 pr-3 py-2 border border-slate-300 rounded-lg text-sm bg-white focus:border-blue-500 focus:outline-none"
                                                />
                                                <Search className="w-4 h-4 text-slate-400 absolute left-2.5 top-2.5" />
                                            </div>

                                            {activeSuggestionIdx === idx && suggestions.length > 0 && (
                                                <div className="absolute top-full left-0 right-0 z-30 bg-white border border-slate-200 rounded-lg shadow-lg max-h-56 overflow-y-auto mt-1">
                                                    {suggestions.map((sug, sIdx) => (
                                                        <div
                                                            key={sIdx}
                                                            onClick={() => selectSuggestion(idx, sug)}
                                                            className="p-2.5 hover:bg-blue-50 cursor-pointer border-b border-slate-100 last:border-0"
                                                        >
                                                            <div className="font-bold text-xs text-slate-800">{sug.nama_barang}</div>
                                                            <div className="text-[11px] text-slate-500 font-mono">
                                                                Kode: {sug.kode_barang} &bull; {sug.jenis_aset}
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            )}
                                        </div>

                                        <div>
                                            <label className="block text-[11px] font-bold text-slate-600 uppercase mb-1">
                                                Kode Barang *
                                            </label>
                                            <input
                                                type="text"
                                                required
                                                value={item.kode_barang}
                                                onChange={(e) => handleItemChange(idx, 'kode_barang', e.target.value)}
                                                placeholder="Kode rekening barang..."
                                                className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm font-mono bg-white focus:border-blue-500 focus:outline-none"
                                            />
                                        </div>

                                        <div>
                                            <label className="block text-[11px] font-bold text-slate-600 uppercase mb-1">
                                                Jenis Aset *
                                            </label>
                                            <input
                                                type="text"
                                                required
                                                value={item.jenis_aset}
                                                onChange={(e) => handleItemChange(idx, 'jenis_aset', e.target.value)}
                                                className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white focus:border-blue-500 focus:outline-none"
                                            />
                                        </div>

                                        <div>
                                            <label className="block text-[11px] font-bold text-slate-600 uppercase mb-1">
                                                Merk / Tipe / Spesifikasi
                                            </label>
                                            <input
                                                type="text"
                                                value={item.merk_tipe}
                                                onChange={(e) => handleItemChange(idx, 'merk_tipe', e.target.value)}
                                                placeholder="Contoh: Asus Vivobook / Penerbit Erlangga"
                                                className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white focus:border-blue-500 focus:outline-none"
                                            />
                                        </div>

                                        <div>
                                            <label className="block text-[11px] font-bold text-slate-600 uppercase mb-1">
                                                No. Sertifikat / Pabrik / Sasis
                                            </label>
                                            <input
                                                type="text"
                                                value={item.no_sertifikat}
                                                onChange={(e) => handleItemChange(idx, 'no_sertifikat', e.target.value)}
                                                placeholder="Boleh dikosongkan (-)"
                                                className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white focus:border-blue-500 focus:outline-none"
                                            />
                                        </div>

                                        <div>
                                            <label className="block text-[11px] font-bold text-slate-600 uppercase mb-1">
                                                Ukuran / Konstruksi
                                            </label>
                                            <input
                                                type="text"
                                                value={item.ukuran_bangunan}
                                                onChange={(e) => handleItemChange(idx, 'ukuran_bangunan', e.target.value)}
                                                placeholder="Boleh dikosongkan (-)"
                                                className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white focus:border-blue-500 focus:outline-none"
                                            />
                                        </div>

                                        <div>
                                            <label className="block text-[11px] font-bold text-slate-600 uppercase mb-1">
                                                Satuan
                                            </label>
                                            <input
                                                type="text"
                                                value={item.satuan}
                                                onChange={(e) => handleItemChange(idx, 'satuan', e.target.value)}
                                                placeholder="UNIT / BUAH / EKS"
                                                className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm uppercase bg-white focus:border-blue-500 focus:outline-none"
                                            />
                                        </div>

                                        <div>
                                            <label className="block text-[11px] font-bold text-slate-600 uppercase mb-1">
                                                Volume *
                                            </label>
                                            <input
                                                type="number"
                                                min="0.01"
                                                step="any"
                                                required
                                                value={item.volume}
                                                onChange={(e) => handleItemChange(idx, 'volume', e.target.value)}
                                                className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm font-semibold bg-white focus:border-blue-500 focus:outline-none"
                                            />
                                        </div>

                                        <div>
                                            <label className="block text-[11px] font-bold text-slate-600 uppercase mb-1">
                                                Harga Satuan (Rp) *
                                            </label>
                                            <input
                                                type="number"
                                                min="0"
                                                step="any"
                                                required
                                                value={item.harga_satuan}
                                                onChange={(e) => handleItemChange(idx, 'harga_satuan', e.target.value)}
                                                className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm font-semibold bg-white focus:border-blue-500 focus:outline-none"
                                            />
                                        </div>
                                    </div>
                                </div>
                            ))}

                            <button
                                type="button"
                                onClick={addItem}
                                className="w-full py-3 border-2 border-dashed border-sky-300 hover:border-sky-500 text-sky-700 rounded-xl font-bold text-xs flex items-center justify-center gap-2 transition bg-sky-50/50"
                            >
                                <Plus className="w-4 h-4" /> Tambah Baris Barang Baru
                            </button>
                        </div>
                    </div>

                    {/* Grand Total & Action Bar */}
                    <div className="bg-slate-50 border-2 border-dashed border-blue-200 rounded-2xl p-6 flex flex-col md:flex-row justify-between items-center gap-4">
                        <div>
                            <span className="text-xs font-bold uppercase text-slate-500">Total Akumulasi Belanja Dokumen SPK:</span>
                            <div className="text-2xl font-black font-mono text-blue-700">
                                {formatRupiah(grandTotal)}
                            </div>
                        </div>

                        <div className="flex items-center gap-3 w-full md:w-auto">
                            <Link
                                href={`/pelaporan-bm/spj?bulan=${bulan}`}
                                className="w-full md:w-auto px-5 py-2.5 border border-slate-300 bg-white rounded-xl text-sm font-semibold text-slate-600 text-center hover:bg-slate-100 transition"
                            >
                                Batal
                            </Link>
                            <button
                                type="submit"
                                disabled={processing}
                                className="w-full md:w-auto inline-flex items-center justify-center gap-2 px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-bold shadow-md transition disabled:opacity-50"
                            >
                                <Save className="w-4 h-4" />
                                {processing ? 'Menyimpan...' : 'Simpan Dokumen SPK'}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
