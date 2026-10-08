import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { FileSpreadsheet } from 'lucide-react';

interface StokRow {
    barang_id: string;
    kode_barang: string | null;
    nama_barang: string;
    satuan: string | null;
    fisik: number;
    terpakai: number;
    reservasi: number;
    tersedia: number;
}

interface PengeluaranRow {
    tanggal: string | null;
    nomor_pengajuan: string | null;
    nama_barang: string;
    satuan: string | null;
    jumlah: number;
    keperluan: string | null;
}

interface Kartu {
    barang: { nama_barang: string; satuan: string | null; sumber_dana: string; fisik: number; terpakai: number; reservasi: number; tersedia: number };
    mutasi: { tanggal: string | null; jenis: string; referensi: string | null; jumlah: number }[];
}

export default function Index({
    sumberDana,
    stok,
    pengeluaran,
    kartu,
}: {
    sumberDana: string;
    stok: StokRow[];
    pengeluaran: PengeluaranRow[];
    kartu: Kartu | null;
}) {
    return (
        <AppLayout title="Laporan BHP">
            <Head title="Laporan BHP" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-lg font-bold text-slate-900">Laporan Distribusi BHP — {sumberDana}</h1>
                    <div className="flex gap-2">
                        {['BOS', 'BOPD'].map((dana) => (
                            <Link
                                key={dana}
                                href={`/distribusi-bhp/laporan?sumber_dana=${dana}`}
                                className={`px-3 py-2 rounded-xl text-sm font-semibold border ${dana === sumberDana ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-700 border-slate-300'}`}
                            >
                                {dana}
                            </Link>
                        ))}
                    </div>
                </div>

                <div className="flex flex-wrap gap-2">
                    <a
                        href={`/distribusi-bhp/laporan/kartu-persediaan?sumber_dana=${sumberDana}`}
                        className="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold bg-white text-slate-700 border border-slate-300 hover:bg-slate-50"
                    >
                        Kartu Persediaan
                    </a>
                    <a
                        href={`/distribusi-bhp/laporan/export?sumber_dana=${sumberDana}&jenis=stok`}
                        className="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold bg-emerald-600 text-white hover:bg-emerald-700"
                    >
                        <FileSpreadsheet className="w-4 h-4" /> Export Stok
                    </a>
                    <a
                        href={`/distribusi-bhp/laporan/export?sumber_dana=${sumberDana}&jenis=pengeluaran`}
                        className="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold bg-emerald-600 text-white hover:bg-emerald-700"
                    >
                        <FileSpreadsheet className="w-4 h-4" /> Export Buku Pengeluaran
                    </a>
                </div>

                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-slate-200 font-bold text-sm text-slate-700">Laporan Stok</div>
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 text-slate-500 uppercase text-xs">
                            <tr>
                                <th className="text-left py-3 px-4">Barang</th>
                                <th className="text-right py-3 px-4">Fisik</th>
                                <th className="text-right py-3 px-4">Terpakai</th>
                                <th className="text-right py-3 px-4">Reservasi</th>
                                <th className="text-right py-3 px-4">Tersedia</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {stok.length === 0 ? (
                                <tr><td colSpan={5} className="py-10 text-center text-slate-400 text-xs">Belum ada data stok.</td></tr>
                            ) : (
                                stok.map((row) => (
                                    <tr key={row.barang_id}>
                                        <td className="py-3 px-4">{row.nama_barang} <span className="text-xs text-slate-400">({row.satuan ?? '-'})</span></td>
                                        <td className="py-3 px-4 text-right">{row.fisik}</td>
                                        <td className="py-3 px-4 text-right">{row.terpakai}</td>
                                        <td className="py-3 px-4 text-right">{row.reservasi}</td>
                                        <td className="py-3 px-4 text-right font-bold">{row.tersedia}</td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-slate-200 font-bold text-sm text-slate-700">Buku Pengeluaran</div>
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 text-slate-500 uppercase text-xs">
                            <tr>
                                <th className="text-left py-3 px-4">Tanggal</th>
                                <th className="text-left py-3 px-4">Barang</th>
                                <th className="text-right py-3 px-4">Jumlah</th>
                                <th className="text-left py-3 px-4">Keperluan</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {pengeluaran.length === 0 ? (
                                <tr><td colSpan={4} className="py-10 text-center text-slate-400 text-xs">Belum ada pengeluaran.</td></tr>
                            ) : (
                                pengeluaran.map((row, i) => (
                                    <tr key={i}>
                                        <td className="py-3 px-4">{row.tanggal ?? '-'}</td>
                                        <td className="py-3 px-4">{row.nama_barang}</td>
                                        <td className="py-3 px-4 text-right">{row.jumlah} {row.satuan}</td>
                                        <td className="py-3 px-4">{row.keperluan ?? '-'}</td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {kartu && (
                    <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                        <div className="px-6 py-4 border-b border-slate-200 font-bold text-sm text-slate-700">
                            Kartu Barang — {kartu.barang.nama_barang} ({kartu.barang.sumber_dana})
                        </div>
                        <table className="w-full text-sm">
                            <thead className="bg-slate-50 text-slate-500 uppercase text-xs">
                                <tr>
                                    <th className="text-left py-3 px-4">Tanggal</th>
                                    <th className="text-left py-3 px-4">Jenis</th>
                                    <th className="text-left py-3 px-4">Referensi</th>
                                    <th className="text-right py-3 px-4">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {kartu.mutasi.length === 0 ? (
                                    <tr><td colSpan={4} className="py-10 text-center text-slate-400 text-xs">Belum ada mutasi.</td></tr>
                                ) : (
                                    kartu.mutasi.map((m, i) => (
                                        <tr key={i}>
                                            <td className="py-3 px-4">{m.tanggal ?? '-'}</td>
                                            <td className="py-3 px-4">
                                                <span className={`px-2 py-0.5 rounded-lg text-[11px] font-bold ${m.jenis === 'masuk' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'}`}>
                                                    {m.jenis}
                                                </span>
                                            </td>
                                            <td className="py-3 px-4">{m.referensi ?? '-'}</td>
                                            <td className="py-3 px-4 text-right">{m.jumlah}</td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
