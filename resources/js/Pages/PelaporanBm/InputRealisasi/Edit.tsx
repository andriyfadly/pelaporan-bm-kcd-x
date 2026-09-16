import { Head, router, Link } from '@inertiajs/react';
import React, { useState, useMemo } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { ArrowLeft, Save, AlertCircle, Calendar } from 'lucide-react';
import { formatRupiah, getNamaBulan } from '@/Utils/format';

interface RealisasiItem {
    id: string;
    spj_id?: string | null;
    no_spk: string;
    no_sp2d?: string | null;
    sumber_perolehan?: string | null;
    ba_no?: string | null;
    ba_tgl?: string | null;
    kode_barang: string;
    nama_barang: string;
    jenis_aset?: string | null;
    merk_tipe?: string | null;
    satuan?: string | null;
    volume: number;
    harga_satuan: number;
    nilai_perolehan: number;
}

interface Props {
    kodering: string;
    bulan: number;
    paguAcuan: number;
    items: RealisasiItem[];
    isReadOnly: boolean;
}

export default function Edit({ kodering, bulan, paguAcuan, items = [], isReadOnly }: Props) {
    // unchecked IDs are tracked for deletion
    const [uncheckedIds, setUncheckedIds] = useState<string[]>([]);
    const [processing, setProcessing] = useState(false);

    const toggleItem = (id: string) => {
        if (isReadOnly) return;
        setUncheckedIds((prev) =>
            prev.includes(id) ? prev.filter((i) => i !== id) : [...prev, id]
        );
    };

    const remainingTotal = useMemo(() => {
        return items.reduce((acc, it) => {
            if (uncheckedIds.includes(it.id)) return acc;
            return acc + Number(it.nilai_perolehan);
        }, 0);
    }, [items, uncheckedIds]);

    const handleSave = () => {
        if (uncheckedIds.length === 0) {
            alert('Tidak ada perubahan alokasi barang.');
            return;
        }

        setProcessing(true);
        router.post(
            '/pelaporan-bm/input-realisasi/update',
            {
                kodering,
                bulan_realisasi: bulan,
                uncheck_ids: uncheckedIds,
            },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
            }
        );
    };

    return (
        <AppLayout title={`Edit Realisasi - ${kodering}`}>
            <Head title={`Edit Realisasi - ${kodering}`} />

            <div className="space-y-6 max-w-6xl mx-auto pb-16">
                <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="px-3 py-1 bg-amber-100 text-amber-800 rounded-lg text-xs font-bold font-mono">
                                <Calendar className="w-3.5 h-3.5 inline mr-1" />
                                Bulan: {getNamaBulan(bulan)}
                            </span>
                            <span className="px-3 py-1 bg-blue-50 text-blue-700 border border-blue-200 rounded-lg text-xs font-bold font-mono">
                                Kodering: {kodering}
                            </span>
                            <span className="px-3 py-1 bg-slate-100 text-slate-700 rounded-lg text-xs font-bold font-mono">
                                Pagu Acuan: {formatRupiah(paguAcuan)}
                            </span>
                        </div>
                        <h1 className="text-xl font-extrabold text-slate-800 mt-2">
                            Ubah Alokasi Realisasi SPJ
                        </h1>
                        <p className="text-xs text-slate-500 mt-0.5">
                            Hilangkan centang (*uncheck*) pada item barang yang ingin dibatalkan dari rekening belanja ini.
                        </p>
                    </div>

                    <Link
                        href={`/pelaporan-bm/input-realisasi?bulan_realisasi=${bulan}`}
                        className="inline-flex items-center gap-1.5 px-4 py-2 border border-slate-300 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50 transition"
                    >
                        <ArrowLeft className="w-4 h-4" /> Kembali
                    </Link>
                </div>

                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm border-collapse">
                            <thead className="bg-[#1e3a8a] text-white text-xs uppercase font-extrabold tracking-wider">
                                <tr>
                                    <th className="p-3.5 w-12 text-center">Status</th>
                                    <th className="p-3.5 w-1/4">No. SPK & Sumber</th>
                                    <th className="p-3.5">Nama Barang & Kode</th>
                                    <th className="p-3.5 text-center w-24">Vol</th>
                                    <th className="p-3.5 text-right w-28">Harga Satuan</th>
                                    <th className="p-3.5 text-right w-32">Nilai Perolehan</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 font-medium">
                                {items.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="p-8 text-center text-slate-400">
                                            Tidak ada item realisasi pada kodering ini.
                                        </td>
                                    </tr>
                                ) : (
                                    items.map((item) => {
                                        const isChecked = !uncheckedIds.includes(item.id);
                                        return (
                                            <tr
                                                key={item.id}
                                                className={`transition ${
                                                    !isChecked ? 'bg-rose-50/40 text-slate-400' : 'hover:bg-slate-50/80'
                                                }`}
                                            >
                                                <td className="p-3.5 text-center">
                                                    <input
                                                        type="checkbox"
                                                        checked={isChecked}
                                                        disabled={isReadOnly}
                                                        onChange={() => toggleItem(item.id)}
                                                        className="w-4 h-4 rounded text-blue-600 cursor-pointer disabled:opacity-50"
                                                        title="Hilangkan centang untuk membatalkan realisasi item ini"
                                                    />
                                                </td>

                                                <td className="p-3.5 font-mono text-xs">
                                                    <div className="font-bold text-slate-800">{item.no_spk}</div>
                                                    <div className="text-[11px] text-slate-500">{item.sumber_perolehan}</div>
                                                </td>

                                                <td className="p-3.5">
                                                    <div className={`font-semibold ${!isChecked ? 'line-through' : 'text-slate-900'}`}>
                                                        {item.nama_barang}
                                                    </div>
                                                    <div className="text-[11px] text-slate-500 font-mono">
                                                        {item.kode_barang} &bull; {item.jenis_aset}
                                                    </div>
                                                </td>

                                                <td className="p-3.5 text-center font-mono text-xs">
                                                    {Number(item.volume).toLocaleString('id-ID')} {item.satuan}
                                                </td>

                                                <td className="p-3.5 text-right font-mono text-xs">
                                                    {formatRupiah(item.harga_satuan)}
                                                </td>

                                                <td className="p-3.5 text-right font-mono text-xs font-bold text-blue-600">
                                                    {formatRupiah(item.nilai_perolehan)}
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="bg-slate-50 border-2 border-dashed border-slate-300 rounded-2xl p-6 flex flex-col md:flex-row justify-between items-center gap-4">
                    <div>
                        <span className="text-xs font-bold uppercase text-slate-500">
                            Total Realisasi Yang Dipertahankan:
                        </span>
                        <div className="text-2xl font-black font-mono text-blue-700">
                            {formatRupiah(remainingTotal)}
                        </div>
                        {uncheckedIds.length > 0 && (
                            <div className="text-xs text-rose-600 font-bold mt-1">
                                {uncheckedIds.length} item akan dilepas dari rekening realisasi ini.
                            </div>
                        )}
                    </div>

                    {!isReadOnly && (
                        <div className="flex items-center gap-3 w-full md:w-auto">
                            <Link
                                href={`/pelaporan-bm/input-realisasi?bulan_realisasi=${bulan}`}
                                className="w-full md:w-auto px-5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm font-semibold text-slate-600 text-center hover:bg-slate-100 transition"
                            >
                                Batal
                            </Link>
                            <button
                                type="button"
                                disabled={processing || uncheckedIds.length === 0}
                                onClick={handleSave}
                                className="w-full md:w-auto inline-flex items-center justify-center gap-2 px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-bold shadow-md transition disabled:opacity-50 cursor-pointer"
                            >
                                <Save className="w-4 h-4" />
                                {processing ? 'Menyimpan...' : 'Simpan Perubahan'}
                            </button>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
