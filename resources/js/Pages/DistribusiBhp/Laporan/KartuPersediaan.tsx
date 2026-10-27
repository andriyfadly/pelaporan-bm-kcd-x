import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { FileSpreadsheet, Search } from 'lucide-react';

interface MutasiRow {
    tanggal: string | null;
    jenis: string;
    referensi: string | null;
    masuk: number;
    keluar: number;
    saldo: number;
    keterangan: string | null;
}

interface Kartu {
    barang: {
        id: string;
        kode_barang: string | null;
        nama_barang: string;
        satuan: string | null;
        sumber_dana: string;
        saldo_awal: number;
        total_masuk: number;
        total_keluar: number;
        saldo_akhir: number;
    };
    mutasi: MutasiRow[];
}

interface BarangOption {
    id: string;
    kode_barang: string | null;
    nama_barang: string;
}

export default function KartuPersediaan({
    sumberDana,
    barangId,
    kartu,
    daftarBarang,
}: {
    sumberDana: string;
    barangId: string;
    kartu: Kartu | null;
    daftarBarang: BarangOption[];
}) {
    const [pilihBarang, setPilihBarang] = useState(barangId);

    const terapkan = (barang: string) => {
        router.get('/distribusi-bhp/laporan/kartu-persediaan', { sumber_dana: sumberDana, barang_id: barang }, { preserveState: true });
    };

    return (
        <AppLayout title="Kartu Persediaan BHP">
            <Head title="Kartu Persediaan BHP" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-lg font-bold text-slate-900">Kartu Persediaan BHP — {sumberDana}</h1>
                        <p className="text-sm text-slate-500">Mutasi masuk dan keluar beserta saldo berjalan per barang.</p>
                    </div>
                    
                </div>

                <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm flex flex-wrap gap-3 items-end">
                    <div className="flex-1 min-w-[240px]">
                        <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Pilih Barang</label>
                        <select
                            value={pilihBarang}
                            onChange={(e) => setPilihBarang(e.target.value)}
                            className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm"
                        >
                            <option value="">Pilih barang...</option>
                            {daftarBarang.map((b) => (
                                <option key={b.id} value={b.id}>
                                    {b.kode_barang ? `${b.kode_barang} — ` : ''}{b.nama_barang}
                                </option>
                            ))}
                        </select>
                    </div>
                    <button
                        type="button"
                        disabled={!pilihBarang}
                        onClick={() => terapkan(pilihBarang)}
                        className="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-bold disabled:opacity-50"
                    >
                        <Search className="w-4 h-4" /> Tampilkan
                    </button>
                    {kartu && (
                        <a
                            href={`/distribusi-bhp/laporan/export?sumber_dana=${sumberDana}&jenis=kartu_persediaan&barang_id=${kartu.barang.id}`}
                            className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold bg-emerald-600 text-white hover:bg-emerald-700"
                        >
                            <FileSpreadsheet className="w-4 h-4" /> Export Excel
                        </a>
                    )}
                </div>

                {kartu && (
                    <>
                        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                            {[
                                { label: 'Saldo Awal', value: kartu.barang.saldo_awal },
                                { label: 'Total Masuk', value: kartu.barang.total_masuk },
                                { label: 'Total Keluar', value: kartu.barang.total_keluar },
                                { label: 'Saldo Akhir', value: kartu.barang.saldo_akhir },
                            ].map((stat) => (
                                <div key={stat.label} className="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm">
                                    <div className="text-xs font-bold uppercase text-slate-500">{stat.label}</div>
                                    <div className="text-xl font-bold text-slate-900">{stat.value} <span className="text-sm font-normal text-slate-400">{kartu.barang.satuan}</span></div>
                                </div>
                            ))}
                        </div>

                        <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                            <div className="px-6 py-4 border-b border-slate-200 font-bold text-sm text-slate-700">
                                {kartu.barang.nama_barang} ({kartu.barang.kode_barang ?? '-'}) — {kartu.barang.sumber_dana}
                            </div>
                            <table className="w-full text-sm">
                                <thead className="bg-slate-50 text-slate-500 uppercase text-xs">
                                    <tr>
                                        <th className="text-left py-3 px-4">Tanggal</th>
                                        <th className="text-left py-3 px-4">Jenis</th>
                                        <th className="text-left py-3 px-4">Referensi</th>
                                        <th className="text-right py-3 px-4">Masuk</th>
                                        <th className="text-right py-3 px-4">Keluar</th>
                                        <th className="text-right py-3 px-4">Saldo</th>
                                        <th className="text-left py-3 px-4">Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {kartu.mutasi.length === 0 ? (
                                        <tr><td colSpan={7} className="py-10 text-center text-slate-400 text-xs">Belum ada mutasi.</td></tr>
                                    ) : (
                                        kartu.mutasi.map((m, i) => (
                                            <tr key={i}>
                                                <td className="py-3 px-4">{m.tanggal ?? '-'}</td>
                                                <td className="py-3 px-4">
                                                    <span className={`px-2 py-0.5 rounded-lg text-[11px] font-bold ${m.jenis === 'masuk' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'}`}>
                                                        {m.jenis}
                                                    </span>
                                                </td>
                                                <td className="py-3 px-4 font-mono text-xs text-slate-600">{m.referensi ?? '-'}</td>
                                                <td className="py-3 px-4 text-right">{m.masuk || '-'}</td>
                                                <td className="py-3 px-4 text-right">{m.keluar || '-'}</td>
                                                <td className="py-3 px-4 text-right font-bold">{m.saldo}</td>
                                                <td className="py-3 px-4 text-slate-500">{m.keterangan ?? '-'}</td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}

                {!kartu && (
                    <div className="bg-white rounded-2xl border border-slate-200 py-16 text-center text-slate-400 text-sm shadow-sm">
                        Pilih barang untuk menampilkan kartu persediaan.
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
