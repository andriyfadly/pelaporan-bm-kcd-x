import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import PeriodeFilter, { periodeQuery, type PeriodeValue } from '@/Components/PeriodeFilter';
import { BULAN_LIST } from '@/Utils/format';
import { AlertTriangle, FileSpreadsheet } from 'lucide-react';

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

interface BulanRekap {
    buka: number;
    masuk: number;
    keluar: number;
    tutup: number;
}

interface BarangRekap {
    barang_id: string;
    kode_barang: string | null;
    nama_barang: string;
    satuan: string | null;
    saldo_awal: number;
    bulan: Record<string, BulanRekap>;
    saldo_akhir: number;
}

interface Rekonsiliasi {
    tahun: number;
    bulan_awal: number;
    bulan_akhir: number;
    sumber_dana: string;
    barang: BarangRekap[];
    anomali: string[];
}

export default function Index({
    sumberDana,
    periode,
    bulanAwal,
    bulanAkhir,
    stok,
    pengeluaran,
    rekonsiliasi,
}: {
    sumberDana: string;
    periode: PeriodeValue;
    bulanAwal: number;
    bulanAkhir: number;
    stok: StokRow[];
    pengeluaran: PengeluaranRow[];
    rekonsiliasi: Rekonsiliasi;
}) {
    const [pilihBulanAwal, setPilihBulanAwal] = useState(bulanAwal);
    const [pilihBulanAkhir, setPilihBulanAkhir] = useState(bulanAkhir);

    const query = `sumber_dana=${sumberDana}&${periodeQuery(periode)}`;
    const bulanTampil = BULAN_LIST.slice(bulanAwal - 1, bulanAkhir);

    const terapkanRentang = () => {
        router.get(
            `/distribusi-bhp/${sumberDana.toLowerCase()}/laporan`,
            {
                sumber_dana: sumberDana,
                tahun: periode.tahun,
                bulan: periode.bulan,
                bulan_awal: pilihBulanAwal,
                bulan_akhir: pilihBulanAkhir,
            },
            { preserveState: true },
        );
    };

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
                    <PeriodeFilter
                        path={`/distribusi-bhp/${sumberDana.toLowerCase()}/laporan`}
                        periode={periode}
                        sumberDana={sumberDana}
                    />
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
                    <div className="overflow-x-auto">
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
                </div>

                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-slate-200 font-bold text-sm text-slate-700">
                        Buku Pengeluaran — {periode.label}
                    </div>
                    <div className="overflow-x-auto">
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

                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3">
                        <div className="font-bold text-sm text-slate-700">
                            Rekonsiliasi Bulanan {rekonsiliasi.tahun} — {rekonsiliasi.sumber_dana}
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <select
                                value={pilihBulanAwal}
                                onChange={(e) => setPilihBulanAwal(Number(e.target.value))}
                                className="px-3 py-1.5 border border-slate-300 rounded-xl text-sm"
                                aria-label="Bulan awal rekonsiliasi"
                            >
                                {BULAN_LIST.map((nama, i) => (
                                    <option key={nama} value={i + 1}>{nama}</option>
                                ))}
                            </select>
                            <span className="text-xs text-slate-400">s/d</span>
                            <select
                                value={pilihBulanAkhir}
                                onChange={(e) => setPilihBulanAkhir(Number(e.target.value))}
                                className="px-3 py-1.5 border border-slate-300 rounded-xl text-sm"
                                aria-label="Bulan akhir rekonsiliasi"
                            >
                                {BULAN_LIST.map((nama, i) => (
                                    <option key={nama} value={i + 1}>{nama}</option>
                                ))}
                            </select>
                            <button
                                type="button"
                                onClick={terapkanRentang}
                                disabled={pilihBulanAwal > pilihBulanAkhir}
                                className="px-3 py-1.5 rounded-xl text-xs font-semibold bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                Tampilkan
                            </button>
                        </div>
                    </div>

                    {rekonsiliasi.anomali.length > 0 && (
                        <div className="px-6 py-4 bg-amber-50 border-b border-amber-200 text-amber-800 text-xs space-y-1">
                            <div className="flex items-center gap-2 font-bold">
                                <AlertTriangle className="w-4 h-4" /> {rekonsiliasi.anomali.length} anomali terdeteksi
                            </div>
                            {rekonsiliasi.anomali.slice(0, 5).map((pesan, i) => (
                                <div key={i}>• {pesan}</div>
                            ))}
                        </div>
                    )}

                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-slate-50 text-slate-500 uppercase text-xs">
                                <tr>
                                    <th className="text-left py-3 px-4 sticky left-0 bg-slate-50">Barang</th>
                                    <th className="text-right py-3 px-3 whitespace-nowrap">
                                        Buka {BULAN_LIST[bulanAwal - 1]?.slice(0, 3)}
                                    </th>
                                    {bulanTampil.map((nama) => (
                                        <th key={nama} className="text-right py-3 px-3 whitespace-nowrap">{nama.slice(0, 3)}</th>
                                    ))}
                                    <th className="text-right py-3 px-3 whitespace-nowrap">
                                        Tutup {BULAN_LIST[bulanAkhir - 1]?.slice(0, 3)}
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {rekonsiliasi.barang.length === 0 ? (
                                    <tr>
                                        <td colSpan={bulanTampil.length + 3} className="py-10 text-center text-slate-400 text-xs">
                                            Belum ada mutasi pada rentang ini.
                                        </td>
                                    </tr>
                                ) : (
                                    rekonsiliasi.barang.map((row) => (
                                        <tr key={row.barang_id}>
                                            <td className="py-3 px-4 sticky left-0 bg-white">
                                                {row.nama_barang}
                                                <span className="block text-[11px] text-slate-400">{row.kode_barang ?? '-'}</span>
                                            </td>
                                            <td className={`py-3 px-3 text-right ${row.saldo_awal < 0 ? 'bg-rose-50 text-rose-700 font-bold' : 'text-slate-500'}`}>
                                                {row.saldo_awal}
                                            </td>
                                            {bulanTampil.map((nama, i) => {
                                                const bulan = row.bulan[String(bulanAwal + i)];
                                                const negatif = bulan.tutup < 0;

                                                return (
                                                    <td
                                                        key={nama}
                                                        className={`py-3 px-3 text-right ${negatif ? 'bg-rose-50 text-rose-700 font-bold' : ''}`}
                                                        title={`Buka ${bulan.buka} + Masuk ${bulan.masuk} − Keluar ${bulan.keluar}`}
                                                    >
                                                        {bulan.tutup}
                                                    </td>
                                                );
                                            })}
                                            <td className="py-3 px-3 text-right font-bold">{row.saldo_akhir}</td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                    <div className="px-6 py-3 text-[11px] text-slate-500 border-t border-slate-100">
                        Angka tiap sel = saldo tutup bulan. Kolom Buka adalah saldo buka bulan awal rentang
                        (sudah memuat seluruh mutasi bulan sebelumnya). Sorot merah berarti saldo negatif.
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
