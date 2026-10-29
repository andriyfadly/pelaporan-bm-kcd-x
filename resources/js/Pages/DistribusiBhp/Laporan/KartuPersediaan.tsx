import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import EmptyState from '@/Components/EmptyState';
import PeriodeFilter, { periodeQuery, type PeriodeValue } from '@/Components/PeriodeFilter';
import { FileSpreadsheet, Search } from 'lucide-react';
import { formatTanggalTabel } from '@/Utils/format';

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
    periode,
    kartu,
    daftarBarang,
}: {
    sumberDana: string;
    barangId: string;
    periode: PeriodeValue;
    kartu: Kartu | null;
    daftarBarang: BarangOption[];
}) {
    const [pilihBarang, setPilihBarang] = useState(barangId);
    const prefix = `/distribusi-bhp/${sumberDana.toLowerCase()}`;

    const terapkan = (barang: string) => {
        router.get(
            `${prefix}/laporan/kartu-persediaan`,
            { barang_id: barang, tahun: periode.tahun, bulan: periode.bulan },
            { preserveState: true },
        );
    };

    const exportUrl = kartu
        ? `${prefix}/laporan/export?jenis=kartu_persediaan&barang_id=${kartu.barang.id}&${periodeQuery(periode)}`
        : null;

    return (
        <AppLayout title="Kartu Persediaan BHP">
            <Head title="Kartu Persediaan BHP" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-lg font-bold text-slate-900">Kartu Persediaan BHP — {sumberDana}</h1>
                        <p className="text-sm text-slate-500">
                            Mutasi {periode.label} dengan saldo awal dan saldo akhir periode.
                        </p>
                    </div>
                    <PeriodeFilter
                        path={`${prefix}/laporan/kartu-persediaan`}
                        periode={periode}
                        sumberDana={sumberDana}
                        extra={{ barang_id: pilihBarang }}
                    />
                </div>

                <div className="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm flex flex-wrap gap-3 items-end sm:p-6">
                    <div className="w-full sm:flex-1 sm:min-w-[240px]">
                        <label htmlFor="pilih-barang" className="block text-xs font-bold uppercase text-slate-500 mb-1">Pilih Barang</label>
                        <select
                            id="pilih-barang"
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
                        className="inline-flex min-h-10 w-full items-center justify-center gap-2 px-4 bg-blue-600 text-white rounded-xl text-sm font-bold disabled:opacity-50 sm:w-auto"
                    >
                        <Search className="w-4 h-4" /> Tampilkan
                    </button>
                    {exportUrl && (
                        <a
                            href={exportUrl}
                            className="inline-flex min-h-10 w-full items-center justify-center gap-2 px-4 rounded-xl text-sm font-semibold bg-emerald-600 text-white hover:bg-emerald-700 sm:w-auto"
                        >
                            <FileSpreadsheet className="w-4 h-4" /> Export Excel
                        </a>
                    )}
                </div>

                {kartu && (
                    <>
                        <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                            {[
                                { label: 'Saldo Awal', value: kartu.barang.saldo_awal },
                                { label: 'Masuk Periode', value: kartu.barang.total_masuk },
                                { label: 'Keluar Periode', value: kartu.barang.total_keluar },
                                { label: 'Saldo Akhir', value: kartu.barang.saldo_akhir },
                            ].map((stat) => (
                                <div key={stat.label} className="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm">
                                    <div className="text-xs font-bold uppercase tracking-wide text-slate-500">{stat.label}</div>
                                    <div className="mt-1 text-xl font-bold text-slate-900">{stat.value} <span className="text-sm font-normal text-slate-500">{kartu.barang.satuan}</span></div>
                                </div>
                            ))}
                        </div>

                        <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                            <div className="px-4 py-4 border-b border-slate-200 font-bold text-sm text-slate-700 sm:px-6">
                                {kartu.barang.nama_barang} ({kartu.barang.kode_barang ?? '-'}) — {kartu.barang.sumber_dana} · {periode.label}
                            </div>
                            {kartu.mutasi.length === 0 ? (
                                <EmptyState
                                    title="Belum Ada Mutasi"
                                    description="Belum ada pergerakan stok untuk barang ini pada periode terpilih."
                                />
                            ) : (
                                <>
                                    <ul className="md:hidden divide-y divide-slate-100">
                                        {kartu.mutasi.map((m, i) => (
                                            <li key={i} className="p-4 space-y-1">
                                                <div className="flex items-center justify-between gap-2">
                                                    <span className="text-sm font-semibold text-slate-800">{formatTanggalTabel(m.tanggal)}</span>
                                                    <span className={`px-2 py-1 rounded-lg text-xs font-bold ${m.jenis === 'masuk' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'}`}>
                                                        {m.jenis}
                                                    </span>
                                                </div>
                                                <div className="text-xs text-slate-500 font-mono">{m.referensi ?? '-'}</div>
                                                <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-600">
                                                    <span>Masuk: <strong>{m.masuk || '-'}</strong></span>
                                                    <span>Keluar: <strong>{m.keluar || '-'}</strong></span>
                                                    <span>Saldo: <strong className="text-slate-900">{m.saldo}</strong></span>
                                                </div>
                                                {m.keterangan && <div className="text-xs text-slate-500">{m.keterangan}</div>}
                                            </li>
                                        ))}
                                    </ul>

                                    <div className="hidden md:block overflow-x-auto">
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
                                                {kartu.mutasi.map((m, i) => (
                                                    <tr key={i}>
                                                        <td className="py-3 px-4">{formatTanggalTabel(m.tanggal)}</td>
                                                        <td className="py-3 px-4">
                                                            <span className={`px-2 py-1 rounded-lg text-xs font-bold ${m.jenis === 'masuk' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'}`}>
                                                                {m.jenis}
                                                            </span>
                                                        </td>
                                                        <td className="py-3 px-4 font-mono text-xs text-slate-600">{m.referensi ?? '-'}</td>
                                                        <td className="py-3 px-4 text-right">{m.masuk || '-'}</td>
                                                        <td className="py-3 px-4 text-right">{m.keluar || '-'}</td>
                                                        <td className="py-3 px-4 text-right font-bold">{m.saldo}</td>
                                                        <td className="py-3 px-4 text-slate-500">{m.keterangan ?? '-'}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </>
                            )}
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
