import { Head, Link, router, useForm } from '@inertiajs/react';
import React, { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';
import SearchInput from '@/Components/SearchInput';
import EmptyState from '@/Components/EmptyState';
import Modal from '@/Components/Modal';
import { formatRupiah } from '@/Utils/format';
import { Boxes, Download, Eye, Plus, Upload } from 'lucide-react';

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
    canKelola: boolean;
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

export default function Index({ units, filter, kondisiList, canKelola }: Props) {
    const [search, setSearch] = useState(filter.search);
    const [kondisi, setKondisi] = useState(filter.kondisi);
    const [tambah, setTambah] = useState(false);
    const [berkasImpor, setBerkasImpor] = useState<File | null>(null);

    const form = useForm({
        kode_barang: '',
        nama_barang: '',
        merk_tipe: '',
        satuan: '',
        tanggal_perolehan: new Date().toISOString().slice(0, 10),
        volume: 1,
        harga_satuan: 0,
        asal_perolehan: '',
        kondisi: 'B',
        keterangan: '',
    });

    const simpan = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/pencatatan-bm/inventaris', {
            preserveScroll: true,
            onSuccess: () => {
                setTambah(false);
                form.reset();
            },
        });
    };

    const impor = (e: React.FormEvent) => {
        e.preventDefault();
        if (!berkasImpor) {
            return;
        }

        router.post(
            '/pencatatan-bm/inventaris-import',
            { file: berkasImpor },
            {
                preserveScroll: true,
                forceFormData: true,
                onSuccess: () => setBerkasImpor(null),
            },
        );
    };

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
                <header className="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
                    <div>
                        <h1 className="text-xl font-bold text-slate-800">Inventaris BM</h1>
                        <p className="text-sm text-slate-500">
                            Unit aset dari sinkronisasi realisasi yang disetujui, tambah manual, atau impor Excel/CSV.
                        </p>
                    </div>
                    <div className="flex w-full flex-wrap items-center gap-2 sm:w-auto">
                        <span className="w-full text-xs text-slate-500 sm:w-auto">
                            Total: <strong>{units.total.toLocaleString('id-ID')}</strong> unit
                        </span>
                        <a
                            href={`/pencatatan-bm/inventaris-export${kondisi ? `?kondisi=${kondisi}` : ''}`}
                            className="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl border border-slate-200 px-3 min-h-10 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 sm:flex-none"
                        >
                            <Download className="w-3.5 h-3.5" /> Ekspor Excel
                        </a>
                        {canKelola && (
                            <button
                                type="button"
                                onClick={() => setTambah(true)}
                                className="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-3 min-h-10 py-2 text-xs font-bold text-white hover:bg-blue-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 sm:flex-none"
                            >
                                <Plus className="w-3.5 h-3.5" /> Tambah Barang
                            </button>
                        )}
                    </div>
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
                        className="min-h-10 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
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
                            description="Unit muncul dari realisasi yang disetujui, tambah barang manual, atau impor Excel/CSV."
                            icon={<Boxes className="w-12 h-12 text-slate-300" />}
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[1080px] text-sm">
                                <thead>
                                    <tr className="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-500">
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
                                        <tr key={unit.id} className="border-b border-slate-50 last:border-0 hover:bg-slate-50/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500">
                                            <td className="whitespace-nowrap px-4 py-3 font-mono text-xs font-bold text-slate-700">{unit.nomor_register}</td>
                                            <td className="whitespace-nowrap px-4 py-3 font-mono text-xs text-slate-500">{unit.kode_barang}</td>
                                            <td className="px-4 py-3">
                                                <p className="font-semibold text-slate-700">{unit.nama_barang ?? '-'}</p>
                                                <p className="text-xs text-slate-500">
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
                                            <td className="whitespace-nowrap px-4 py-3 text-right text-slate-600">{formatRupiah(unit.harga_satuan)}</td>
                                            <td className="px-4 py-3 text-right">
                                                <Link
                                                    href={`/pencatatan-bm/inventaris/${unit.id}`}
                                                    className="inline-flex items-center gap-1 rounded-lg min-h-9 px-3 py-1.5 text-xs font-bold text-blue-600 hover:bg-blue-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                                                >
                                                    <Eye className="w-3.5 h-3.5" /> Detail
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {units.data.length > 0 && (
                        <div className="px-4 pb-4">
                            <Pagination links={units.links} total={units.total} />
                        </div>
                    )}
                </div>
            </div>

            <Modal isOpen={tambah} onClose={() => setTambah(false)} title="Tambah Barang Inventaris">
                <form onSubmit={simpan} className="space-y-3">
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <label className="block text-xs font-semibold text-slate-600">
                            Kode Barang *
                            <input
                                type="text"
                                value={form.data.kode_barang}
                                onChange={(e) => form.setData('kode_barang', e.target.value)}
                                placeholder="1.3.2.10.01.02.002"
                                className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                            />
                            {form.errors.kode_barang && <p className="mt-1 text-xs text-rose-600">{form.errors.kode_barang}</p>}
                        </label>

                        <label className="block text-xs font-semibold text-slate-600">
                            Nama Barang
                            <input
                                type="text"
                                value={form.data.nama_barang}
                                onChange={(e) => form.setData('nama_barang', e.target.value)}
                                placeholder="Kosongkan = ikut master kode barang"
                                className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                            />
                        </label>

                        <label className="block text-xs font-semibold text-slate-600">
                            Merek / Tipe
                            <input
                                type="text"
                                value={form.data.merk_tipe}
                                onChange={(e) => form.setData('merk_tipe', e.target.value)}
                                className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                            />
                        </label>

                        <label className="block text-xs font-semibold text-slate-600">
                            Tanggal Perolehan *
                            <input
                                type="date"
                                value={form.data.tanggal_perolehan}
                                onChange={(e) => form.setData('tanggal_perolehan', e.target.value)}
                                className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                            />
                            {form.errors.tanggal_perolehan && (
                                <p className="mt-1 text-xs text-rose-600">{form.errors.tanggal_perolehan}</p>
                            )}
                        </label>

                        <label className="block text-xs font-semibold text-slate-600">
                            Volume *
                            <input
                                type="number"
                                min={1}
                                max={500}
                                value={form.data.volume}
                                onChange={(e) => form.setData('volume', Number(e.target.value))}
                                className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                            />
                            {form.errors.volume && <p className="mt-1 text-xs text-rose-600">{form.errors.volume}</p>}
                        </label>

                        <label className="block text-xs font-semibold text-slate-600">
                            Satuan
                            <input
                                type="text"
                                value={form.data.satuan}
                                onChange={(e) => form.setData('satuan', e.target.value)}
                                placeholder="Unit"
                                className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                            />
                        </label>

                        <label className="block text-xs font-semibold text-slate-600">
                            Harga Satuan
                            <input
                                type="number"
                                min={0}
                                value={form.data.harga_satuan}
                                onChange={(e) => form.setData('harga_satuan', Number(e.target.value))}
                                className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                            />
                        </label>

                        <label className="block text-xs font-semibold text-slate-600">
                            Asal Perolehan
                            <input
                                type="text"
                                value={form.data.asal_perolehan}
                                onChange={(e) => form.setData('asal_perolehan', e.target.value)}
                                placeholder="BOS / APBN / Hibah"
                                className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                            />
                        </label>

                        <label className="block text-xs font-semibold text-slate-600">
                            Kondisi
                            <select
                                value={form.data.kondisi}
                                onChange={(e) => form.setData('kondisi', e.target.value)}
                                className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                            >
                                {Object.entries(kondisiList).map(([kode, label]) => (
                                    <option key={kode} value={kode}>
                                        {label}
                                    </option>
                                ))}
                            </select>
                        </label>
                    </div>

                    <div className="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            onClick={() => setTambah(false)}
                            className="rounded-xl px-4 min-h-10 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            disabled={form.processing}
                            className="rounded-xl bg-blue-600 px-4 min-h-10 py-2 text-xs font-bold text-white hover:bg-blue-700 disabled:opacity-60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                        >
                            Simpan
                        </button>
                    </div>
                </form>

                <form onSubmit={impor} className="mt-5 space-y-2 border-t border-slate-100 pt-4">
                    <p className="text-xs text-slate-500">
                        Impor massal: kolom Kode, Nama, Merek/Tipe, Tanggal, Bulan, Tahun, Satuan, Volume, Harga Satuan,
                        Asal Perolehan.
                    </p>
                    <div className="flex flex-wrap items-center gap-2">
                        <input
                            type="file"
                            accept=".xlsx,.xls,.csv"
                            onChange={(e) => setBerkasImpor(e.target.files?.[0] ?? null)}
                            aria-label="Pilih berkas impor inventaris"
                            className="min-h-10 flex-1 text-xs text-slate-600 file:mr-2 file:cursor-pointer file:rounded-lg file:border file:border-slate-200 file:bg-white file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-slate-700 hover:file:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                        />
                        <button
                            type="submit"
                            disabled={!berkasImpor}
                            className="inline-flex items-center gap-1.5 rounded-xl bg-slate-800 px-3 min-h-10 py-2 text-xs font-bold text-white hover:bg-slate-900 disabled:opacity-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                        >
                            <Upload className="w-3.5 h-3.5" /> Impor
                        </button>
                        <a
                            href="/pencatatan-bm/inventaris-template"
                            className="text-xs font-bold text-blue-600 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                        >
                            Unduh template
                        </a>
                    </div>
                </form>
            </Modal>
        </AppLayout>
    );
}
