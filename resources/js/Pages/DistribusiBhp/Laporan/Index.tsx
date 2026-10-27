import { Head } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import PeriodeFilter, { periodeQuery, type PeriodeValue } from '@/Components/PeriodeFilter';
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

export default function Index({
    sumberDana,
    periode,
    stok,
    pengeluaran,
}: {
    sumberDana: string;
    periode: PeriodeValue;
    stok: StokRow[];
    pengeluaran: PengeluaranRow[];
}) {
    const query = `sumber_dana=${sumberDana}&${periodeQuery(periode)}`;

    return (
        <AppLayout title="Laporan BHP">
            <Head title="Laporan BHP" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-lg font-bold text-slate-900">Laporan Distribusi BHP — {sumberDana}</h1>
                        <p className="text-sm text-slate-500">
                            Posisi stok dan pengeluaran per akhir {periode.label}.
                        </p>
                    </div>
                    <PeriodeFilter path="/distribusi-bhp/laporan" periode={periode} sumberDana={sumberDana} />
                </div>

                <div className="flex flex-wrap gap-2">
                    <a
                        href={`/distribusi-bhp/laporan/kartu-persediaan?${query}`}
                        className="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold bg-white text-slate-700 border border-slate-300 hover:bg-slate-50"
                    >
                        Kartu Persediaan
                    </a>
                    <a
                        href={`/distribusi-bhp/laporan/export?${query}&jenis=stok`}
                        className="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold bg-emerald-600 text-white hover:bg-emerald-700"
                    >
                        <FileSpreadsheet className="w-4 h-4" /> Export Stok
                    </a>
                    <a
                        href={`/distribusi-bhp/laporan/export?${query}&jenis=pengeluaran`}
                        className="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold bg-emerald-600 text-white hover:bg-emerald-700"
                    >
                        <FileSpreadsheet className="w-4 h-4" /> Export Buku Pengeluaran
                    </a>
                </div>

                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-slate-200 font-bold text-sm text-slate-700">
                        Laporan Stok — akhir {periode.label}
                    </div>
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
                    <div className="px-6 py-4 border-b border-slate-200 font-bold text-sm text-slate-700">
                        Buku Pengeluaran — {periode.label}
                    </div>
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
                                <tr><td colSpan={4} className="py-10 text-center text-slate-400 text-xs">Belum ada pengeluaran pada periode ini.</td></tr>
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
            </div>
        </AppLayout>
    );
}
