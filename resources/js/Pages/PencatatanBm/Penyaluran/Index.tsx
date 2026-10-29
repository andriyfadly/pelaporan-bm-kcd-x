import { Head, router } from '@inertiajs/react';
import React, { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';
import Modal from '@/Components/Modal';
import EmptyState from '@/Components/EmptyState';
import { ArrowRight, PackageCheck, Plus } from 'lucide-react';

interface Unit {
    id: string;
    nomor_register: string;
    kode_barang: string;
    nama_barang: string | null;
    tahun_perolehan: number | null;
}

interface Riwayat {
    id: string;
    tanggal: string;
    keterangan: string | null;
    unit?: { nomor_register: string; nama_barang: string | null } | null;
    tujuan_ruang?: { nama_ruang: string } | null;
    tujuan_pegawai?: { nama_lengkap: string } | null;
    user?: { name: string } | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    riwayat: {
        data: Riwayat[];
        links: PaginationLink[];
        total: number;
    };
    belumDisalurkan: Unit[];
    ruangList: { id: string; nama_ruang: string }[];
    pegawaiList: { id: string; nama_lengkap: string }[];
    kondisiList: Record<string, string>;
}

export default function Index({
    riwayat,
    belumDisalurkan,
    ruangList,
    pegawaiList,
    kondisiList,
}: Props) {
    const [open, setOpen] = useState(false);
    const [proses, setProses] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [form, setForm] = useState({
        unit_id: '',
        ruang_id: '',
        pegawai_id: '',
        kondisi: '',
        tanggal: new Date().toISOString().slice(0, 10),
        keterangan: '',
    });

    const tutup = () => {
        setOpen(false);
        setErrors({});
    };

    const simpan = (e: React.FormEvent) => {
        e.preventDefault();
        setProses(true);
        setErrors({});

        router.post(
            `/pencatatan-bm/penyaluran/${form.unit_id}`,
            {
                ruang_id: form.ruang_id || null,
                pegawai_id: form.pegawai_id || null,
                kondisi: form.kondisi || null,
                tanggal: form.tanggal,
                keterangan: form.keterangan || null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    tutup();
                    setForm({
                        unit_id: '',
                        ruang_id: '',
                        pegawai_id: '',
                        kondisi: '',
                        tanggal: new Date().toISOString().slice(0, 10),
                        keterangan: '',
                    });
                },
                onError: (bag) => setErrors(bag as Record<string, string>),
                onFinish: () => setProses(false),
            },
        );
    };

    return (
        <AppLayout>
            <Head title="Penyaluran Inventaris" />

            <div className="space-y-5">
                <header className="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
                    <div>
                        <h1 className="text-xl font-bold text-slate-800">Penyaluran</h1>
                        <p className="text-sm text-slate-500">
                            Penempatan pertama barang ke ruang/penerima. Perpindahan setelahnya ada di menu Mutasi.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={() => setOpen(true)}
                        disabled={belumDisalurkan.length === 0}
                        title={belumDisalurkan.length === 0 ? 'Semua unit sudah punya lokasi' : undefined}
                        className="inline-flex w-full items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 min-h-10 py-2 sm:w-auto text-xs font-bold text-white hover:bg-blue-700 disabled:opacity-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                    >
                        <Plus className="w-4 h-4" /> Salurkan Barang
                    </button>
                </header>

                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    <div className="flex items-center gap-2 border-b border-slate-100 px-5 py-4">
                        <PackageCheck className="w-4 h-4 text-slate-500" />
                        <h2 className="font-bold text-slate-800">Riwayat Penyaluran</h2>
                    </div>

                    {riwayat.data.length === 0 ? (
                        <EmptyState
                            title="Belum ada penyaluran"
                            description="Riwayat muncul setelah unit ditempatkan pertama kali."
                        />
                    ) : (
                        <ul className="divide-y divide-slate-50">
                            {riwayat.data.map((item) => (
                                <li key={item.id} className="px-5 py-3 text-sm">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <span className="font-semibold text-slate-700">
                                            {item.unit?.nama_barang ?? '-'} · {item.unit?.nomor_register ?? '-'}
                                        </span>
                                        <span className="text-xs text-slate-500">{item.tanggal}</span>
                                    </div>
                                    <p className="mt-1 flex flex-wrap items-center gap-1 text-xs text-slate-500">
                                        <ArrowRight className="w-3 h-3" />
                                        <span>{item.tujuan_ruang?.nama_ruang ?? '-'}</span>
                                        <span className="mx-1 text-slate-300">|</span>
                                        <span>{item.tujuan_pegawai?.nama_lengkap ?? '-'}</span>
                                    </p>
                                    {item.keterangan && <p className="text-xs text-slate-500">{item.keterangan}</p>}
                                </li>
                            ))}
                        </ul>
                    )}

                    {riwayat.data.length > 0 && (
                        <div className="px-4 pb-4">
                            <Pagination links={riwayat.links} total={riwayat.total} />
                        </div>
                    )}
                </div>
            </div>

            <Modal isOpen={open} onClose={tutup} title="Salurkan Barang">
                <form onSubmit={simpan} className="space-y-3">
                    <label className="block text-xs font-semibold text-slate-600">
                        Unit
                        <select
                            value={form.unit_id}
                            onChange={(e) => setForm({ ...form, unit_id: e.target.value })}
                            required
                            className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                        >
                            <option value="">Pilih unit yang belum disalurkan</option>
                            {belumDisalurkan.map((unit) => (
                                <option key={unit.id} value={unit.id}>
                                    {unit.nomor_register} · {unit.nama_barang ?? unit.kode_barang}
                                    {unit.tahun_perolehan ? ` (${unit.tahun_perolehan})` : ''}
                                </option>
                            ))}
                        </select>
                        {errors.unit && <p className="mt-1 text-xs text-rose-600">{errors.unit}</p>}
                    </label>

                    <label className="block text-xs font-semibold text-slate-600">
                        Ruang Tujuan *
                        <select
                            value={form.ruang_id}
                            onChange={(e) => setForm({ ...form, ruang_id: e.target.value })}
                            required
                            className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                        >
                            <option value="">Pilih ruang</option>
                            {ruangList.map((ruang) => (
                                <option key={ruang.id} value={ruang.id}>
                                    {ruang.nama_ruang}
                                </option>
                            ))}
                        </select>
                        {errors.ruang_id && <p className="mt-1 text-xs text-rose-600">{errors.ruang_id}</p>}
                    </label>

                    <label className="block text-xs font-semibold text-slate-600">
                        Penanggung Jawab / Penerima
                        <select
                            value={form.pegawai_id}
                            onChange={(e) => setForm({ ...form, pegawai_id: e.target.value })}
                            className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                        >
                            <option value="">-</option>
                            {pegawaiList.map((pegawai) => (
                                <option key={pegawai.id} value={pegawai.id}>
                                    {pegawai.nama_lengkap}
                                </option>
                            ))}
                        </select>
                        {errors.pegawai_id && <p className="mt-1 text-xs text-rose-600">{errors.pegawai_id}</p>}
                    </label>

                    <label className="block text-xs font-semibold text-slate-600">
                        Kondisi Saat Disalurkan
                        <select
                            value={form.kondisi}
                            onChange={(e) => setForm({ ...form, kondisi: e.target.value })}
                            className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                        >
                            <option value="">Ikuti kondisi saat ini</option>
                            {Object.entries(kondisiList).map(([kode, label]) => (
                                <option key={kode} value={kode}>
                                    {label}
                                </option>
                            ))}
                        </select>
                        {errors.kondisi && <p className="mt-1 text-xs text-rose-600">{errors.kondisi}</p>}
                    </label>

                    <label className="block text-xs font-semibold text-slate-600">
                        Tanggal
                        <input
                            type="date"
                            value={form.tanggal}
                            onChange={(e) => setForm({ ...form, tanggal: e.target.value })}
                            required
                            className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                        />
                        {errors.tanggal && <p className="mt-1 text-xs text-rose-600">{errors.tanggal}</p>}
                    </label>

                    <label className="block text-xs font-semibold text-slate-600">
                        Keterangan
                        <textarea
                            value={form.keterangan}
                            onChange={(e) => setForm({ ...form, keterangan: e.target.value })}
                            rows={2}
                            className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                        />
                    </label>

                    <div className="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            onClick={tutup}
                            className="rounded-xl px-4 min-h-10 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            disabled={proses}
                            className="rounded-xl bg-blue-600 px-4 min-h-10 py-2 text-xs font-bold text-white hover:bg-blue-700 disabled:opacity-60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                        >
                            Salurkan
                        </button>
                    </div>
                </form>
            </Modal>
        </AppLayout>
    );
}
