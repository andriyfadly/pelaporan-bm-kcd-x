import { Head, Link, router } from '@inertiajs/react';
import React, { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';
import SearchInput from '@/Components/SearchInput';
import EmptyState from '@/Components/EmptyState';
import { formatRupiah } from '@/Utils/format';
import { Boxes, Eye } from 'lucide-react';

interface UnitItem {
    id: string;
    nomor_register: string;
    kode_barang: string;
    nama_barang: string | null;
    merk_tipe: string | null;
    satuan: string | null;
    harga_satuan: string;
    kondisi: string;
    status: string;
    ruang?: { id: string; nama_ruang: string } | null;
    pegawai?: { id: string; nama_lengkap: string } | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    units: {
        data: UnitItem[];
        links: PaginationLink[];
        total: number;
    };
    filter: { search: string; kondisi: string };
    kondisiList: Record<string, string>;
}

const BADGE_KONDISI: Record<string, string> = {
    B: 'bg-emerald-50 text-emerald-700 border-emerald-100',
    RR: 'bg-amber-50 text-amber-700 border-amber-100',
    RB: 'bg-rose-50 text-rose-700 border-rose-100',
};

const LABEL_STATUS: Record<string, string> = {
    aktif: 'Aktif',
    dikembalikan: 'Dikembalikan',
    dihapus_koreksi: 'Dihapus (koreksi)',
};

export default function Index({ units, filter, kondisiList }: Props) {
    const [search, setSearch] = useState(filter.search);
    const [kondisi, setKondisi] = useState(filter.kondisi);

    const terapkan = (nextSearch: string, nextKondisi: string) => {
        router.get(
            '/pencatatan-bm/inventaris',
            { search: nextSearch || undefined, kondisi: nextKondisi || undefined },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AppLayout>
            <Head title="Inventaris BM" />

            <div className="space-y-5">
                <header className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-bold text-slate-800">Inventaris BM</h1>
                        <p className="text-sm text-slate-500">Daftar unit aset hasil sinkronisasi realisasi yang disetujui.</p>
                    </div>
                    <span className="text-xs text-slate-400">
                        Total: <strong>{units.total.toLocaleString('id-ID')}</strong> unit
                    </span>
                </header>

                <div className="flex flex-wrap items-center gap-2">
                    <SearchInput
                        value={search}
                        onChange={(value) => {
                            setSearch(value);
                            terapkan(value, kondisi);
                        }}
                        placeholder="Cari nama, kode, register, atau merek..."
                        ariaLabel="Cari unit inventaris"
                        className="w-full sm:w-80"
                    />
                    <select
                        value={kondisi}
                        onChange={(e) => {
                            setKondisi(e.target.value);
                            terapkan(search, e.target.value);
                        }}
                        aria-label="Filter kondisi"
                        className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700"
                    >
                        <option value="">Semua kondisi</option>
                        {Object.entries(kondisiList).map(([kode, label]) => (
                            <option key={kode} value={kode}>
                                {label}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    {units.data.length === 0 ? (
                        <EmptyState
                            title="Belum ada unit inventaris"
                            description="Unit muncul setelah laporan realisasi periode terkait disetujui Admin KCD."
                            icon={<Boxes className="w-12 h-12 text-slate-300" />}
                        />
                    ) : (
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-400">
                                    <th className="px-4 py-3">Register</th>
                                    <th className="px-4 py-3">Kode Barang</th>
                                    <th className="px-4 py-3">Nama Barang</th>
                                    <th className="px-4 py-3">Ruang</th>
                                    <th className="px-4 py-3">Penanggung Jawab</th>
                                    <th className="px-4 py-3">Kondisi</th>
                                    <th className="px-4 py-3 text-right">Nilai</th>
                                    <th className="px-4 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {units.data.map((unit) => (
                                    <tr key={unit.id} className="border-b border-slate-50 last:border-0 hover:bg-slate-50/60">
                                        <td className="px-4 py-3 font-mono text-xs font-bold text-slate-700">{unit.nomor_register}</td>
                                        <td className="px-4 py-3 font-mono text-xs text-slate-500">{unit.kode_barang}</td>
                                        <td className="px-4 py-3">
                                            <p className="font-semibold text-slate-700">{unit.nama_barang ?? '-'}</p>
                                            <p className="text-xs text-slate-400">
                                                {unit.merk_tipe ?? '-'} · {LABEL_STATUS[unit.status] ?? unit.status}
                                            </p>
                                        </td>
                                        <td className="px-4 py-3 text-slate-600">{unit.ruang?.nama_ruang ?? '-'}</td>
                                        <td className="px-4 py-3 text-slate-600">{unit.pegawai?.nama_lengkap ?? '-'}</td>
                                        <td className="px-4 py-3">
                                            <span className={`rounded-full border px-2 py-0.5 text-xs font-bold ${BADGE_KONDISI[unit.kondisi] ?? ''}`}>
                                                {kondisiList[unit.kondisi] ?? unit.kondisi}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-right text-slate-600">{formatRupiah(unit.harga_satuan)}</td>
                                        <td className="px-4 py-3 text-right">
                                            <Link
                                                href={`/pencatatan-bm/inventaris/${unit.id}`}
                                                className="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-bold text-blue-600 hover:bg-blue-50"
                                            >
                                                <Eye className="w-3.5 h-3.5" /> Detail
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}

                    {units.data.length > 0 && (
                        <div className="px-4 pb-4">
                            <Pagination links={units.links} total={units.total} />
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
