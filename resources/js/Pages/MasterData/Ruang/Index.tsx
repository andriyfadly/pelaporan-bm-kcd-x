import { Head, router, useForm } from '@inertiajs/react';
import React, { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Modal from '@/Components/Modal';
import ConfirmDialog from '@/Components/ConfirmDialog';
import EmptyState from '@/Components/EmptyState';
import SearchInput from '@/Components/SearchInput';
import { Building2, Download, Pencil, Plus, Trash2, Upload } from 'lucide-react';

interface Ruang {
    id: string;
    nama_ruang: string;
    kode: string | null;
    unit_aktif_count: number;
}

interface Props {
    ruang: Ruang[];
}

interface BarisMassal {
    nama_ruang: string;
    kode: string;
}

type BarisPayload = { [kunci: string]: string };

const BARIS_KOSONG: BarisMassal = { nama_ruang: '', kode: '' };

export default function Index({ ruang }: Props) {
    const [modalOpen, setModalOpen] = useState(false);
    const [editTarget, setEditTarget] = useState<Ruang | null>(null);
    const [hapusTarget, setHapusTarget] = useState<Ruang | null>(null);
    const [modalMassal, setModalMassal] = useState(false);
    const [baris, setBaris] = useState<BarisMassal[]>([{ ...BARIS_KOSONG }]);
    const [berkas, setBerkas] = useState<File | null>(null);
    const [hapusSemua, setHapusSemua] = useState(false);
    const [cari, setCari] = useState('');

    const form = useForm({ nama_ruang: '', kode: '' });

    const daftar = ruang.filter((item) => item.nama_ruang.toLowerCase().includes(cari.toLowerCase()));

    const buka = (item: Ruang | null) => {
        setEditTarget(item);
        form.setData({ nama_ruang: item?.nama_ruang ?? '', kode: item?.kode ?? '' });
        form.clearErrors();
        setModalOpen(true);
    };

    const simpan = (e: React.FormEvent) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setModalOpen(false);
                form.reset();
            },
        };

        if (editTarget) {
            form.put(`/master-data/ruang/${editTarget.id}`, options);
            return;
        }

        form.post('/master-data/ruang', options);
    };

    const hapus = () => {
        if (!hapusTarget) {
            return;
        }

        router.delete(`/master-data/ruang/${hapusTarget.id}`, {
            preserveScroll: true,
            onFinish: () => setHapusTarget(null),
        });
    };

    const ubahBaris = (index: number, patch: Partial<BarisMassal>) => {
        setBaris((lama) => lama.map((row, i) => (i === index ? { ...row, ...patch } : row)));
    };

    const simpanMassal = () => {
        const terisi = baris.filter((row) => row.nama_ruang.trim() !== '');

        if (terisi.length === 0) {
            return;
        }

        router.post('/master-data/ruang/massal', { baris: terisi.map((row): BarisPayload => ({ ...row })) }, {
            preserveScroll: true,
            onSuccess: () => {
                setModalMassal(false);
                setBaris([{ ...BARIS_KOSONG }]);
            },
        });
    };

    const impor = (e: React.FormEvent) => {
        e.preventDefault();

        if (!berkas) {
            return;
        }

        router.post('/master-data/ruang/import', { file: berkas }, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => setBerkas(null),
        });
    };

    return (
        <AppLayout>
            <Head title="Master Ruang" />

            <div className="space-y-5">
                <header className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-bold text-slate-800">Master Ruang</h1>
                        <p className="text-sm text-slate-500">Ruang sekolah dipakai sebagai penempatan unit inventaris.</p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            onClick={() => setModalMassal(true)}
                            className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50"
                        >
                            <Plus className="w-4 h-4" /> Tambah Banyak
                        </button>
                        <button
                            type="button"
                            onClick={() => setHapusSemua(true)}
                            className="inline-flex items-center gap-1.5 rounded-xl border border-rose-200 px-3 py-2 text-xs font-bold text-rose-600"
                        >
                            <Trash2 className="w-4 h-4" /> Hapus Semua
                        </button>
                        <button
                            type="button"
                            onClick={() => buka(null)}
                            className="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700"
                        >
                            <Plus className="w-4 h-4" /> Tambah Ruang
                        </button>
                    </div>
                </header>

                <section className="rounded-2xl border border-slate-200 bg-white p-4">
                    <h2 className="font-bold text-slate-800">Import Excel</h2>
                    <p className="text-xs text-slate-500">Kolom: Nama Ruangan, Kode (opsional).</p>
                    <form onSubmit={impor} className="mt-3 flex flex-wrap items-center gap-2">
                        <input
                            type="file"
                            accept=".xlsx,.xls,.csv"
                            onChange={(e) => setBerkas(e.target.files?.[0] ?? null)}
                            aria-label="Pilih file Excel ruang"
                            className="min-h-10 flex-1 text-xs text-slate-600 file:mr-2 file:cursor-pointer file:rounded-lg file:border file:border-slate-200 file:bg-white file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-slate-700"
                        />
                        <button
                            type="submit"
                            disabled={!berkas}
                            className="inline-flex items-center gap-1.5 rounded-xl bg-slate-800 px-3 py-2 text-xs font-bold text-white disabled:opacity-50"
                        >
                            <Upload className="h-3.5 w-3.5" /> Import Data
                        </button>
                        <a href="/master-data/ruang/template" className="inline-flex items-center gap-1 text-xs font-bold text-blue-600 hover:underline">
                            <Download className="h-3.5 w-3.5" /> Download Template
                        </a>
                    </form>
                </section>

                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    <div className="flex flex-wrap items-center gap-2 border-b border-slate-100 p-4">
                        <h2 className="mr-auto font-bold text-slate-800">Daftar Ruang</h2>
                        <SearchInput
                            value={cari}
                            onChange={setCari}
                            placeholder="Cari nama ruang..."
                            ariaLabel="Cari ruang"
                            className="w-full sm:w-72"
                        />
                    </div>

                    {daftar.length === 0 ? (
                        <EmptyState
                            title={ruang.length === 0 ? 'Belum ada ruang' : 'Ruang tidak ditemukan'}
                            description="Tambahkan ruang terlebih dahulu agar unit inventaris bisa ditempatkan."
                            icon={<Building2 className="w-12 h-12 text-slate-300" />}
                        />
                    ) : (
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-400">
                                    <th className="px-4 py-3">Nama Ruang</th>
                                    <th className="px-4 py-3">Kode</th>
                                    <th className="px-4 py-3 text-right">Unit Aktif</th>
                                    <th className="px-4 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {daftar.map((item) => (
                                    <tr key={item.id} className="border-b border-slate-50 last:border-0">
                                        <td className="px-4 py-3 font-semibold text-slate-700">{item.nama_ruang}</td>
                                        <td className="px-4 py-3 text-slate-500">{item.kode ?? '-'}</td>
                                        <td className="px-4 py-3 text-right text-slate-600">{item.unit_aktif_count}</td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-end gap-1">
                                                <button
                                                    type="button"
                                                    onClick={() => buka(item)}
                                                    aria-label={`Ubah ruang ${item.nama_ruang}`}
                                                    className="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100"
                                                >
                                                    <Pencil className="w-4 h-4" />
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => setHapusTarget(item)}
                                                    aria-label={`Hapus ruang ${item.nama_ruang}`}
                                                    className="rounded-lg p-1.5 text-rose-600 hover:bg-rose-50"
                                                >
                                                    <Trash2 className="w-4 h-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            </div>

            <Modal isOpen={modalOpen} onClose={() => setModalOpen(false)} title={editTarget ? 'Ubah Ruang' : 'Tambah Ruang'}>
                <form onSubmit={simpan} className="space-y-3">
                    <label className="block text-xs font-semibold text-slate-600">
                        Nama Ruang
                        <input
                            type="text"
                            value={form.data.nama_ruang}
                            onChange={(e) => form.setData('nama_ruang', e.target.value)}
                            className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                        />
                        {form.errors.nama_ruang && <p className="mt-1 text-xs text-rose-600">{form.errors.nama_ruang}</p>}
                    </label>
                    <label className="block text-xs font-semibold text-slate-600">
                        Kode
                        <input
                            type="text"
                            value={form.data.kode}
                            onChange={(e) => form.setData('kode', e.target.value)}
                            className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                        />
                        {form.errors.kode && <p className="mt-1 text-xs text-rose-600">{form.errors.kode}</p>}
                    </label>
                    <div className="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            onClick={() => setModalOpen(false)}
                            className="rounded-xl px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            disabled={form.processing}
                            className="rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700 disabled:opacity-60"
                        >
                            Simpan
                        </button>
                    </div>
                </form>
            </Modal>

            <Modal isOpen={modalMassal} onClose={() => setModalMassal(false)} title="Tambah Banyak Ruang" maxWidth="lg">
                <div className="space-y-2">
                    {baris.map((row, index) => (
                        <div key={index} className="flex gap-2">
                            <input
                                value={row.nama_ruang}
                                onChange={(e) => ubahBaris(index, { nama_ruang: e.target.value })}
                                placeholder="Nama ruangan"
                                aria-label={`Nama ruangan baris ${index + 1}`}
                                className="min-w-0 flex-1 rounded-xl border border-slate-300 px-3 py-2 text-sm"
                            />
                            <input
                                value={row.kode}
                                onChange={(e) => ubahBaris(index, { kode: e.target.value })}
                                placeholder="Kode"
                                aria-label={`Kode baris ${index + 1}`}
                                className="w-28 rounded-xl border border-slate-300 px-3 py-2 text-sm"
                            />
                            {baris.length > 1 && (
                                <button
                                    type="button"
                                    onClick={() => setBaris((lama) => lama.filter((_, i) => i !== index))}
                                    aria-label={`Hapus baris ${index + 1}`}
                                    className="rounded-lg p-2 text-rose-500 hover:bg-rose-50"
                                >
                                    <Trash2 className="h-4 w-4" />
                                </button>
                            )}
                        </div>
                    ))}
                </div>

                <div className="mt-3 flex flex-wrap items-center justify-between gap-2">
                    <button
                        type="button"
                        onClick={() => setBaris((lama) => [...lama, { ...BARIS_KOSONG }])}
                        className="inline-flex items-center gap-1 text-xs font-bold text-blue-600 hover:underline"
                    >
                        <Plus className="h-3.5 w-3.5" /> Tambah Baris
                    </button>
                    <div className="flex gap-2">
                        <button
                            type="button"
                            onClick={() => setModalMassal(false)}
                            className="rounded-xl px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100"
                        >
                            Batal
                        </button>
                        <button
                            type="button"
                            onClick={simpanMassal}
                            className="rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700"
                        >
                            Simpan Semua
                        </button>
                    </div>
                </div>
            </Modal>

            <ConfirmDialog
                isOpen={hapusTarget !== null}
                onClose={() => setHapusTarget(null)}
                onConfirm={hapus}
                title="Hapus Ruang"
                message={`Unit aktif di ${hapusTarget?.nama_ruang ?? 'ruang ini'} kehilangan penempatan, tetapi riwayat mutasinya tetap tersimpan.`}
                confirmText="Hapus"
            />

            <ConfirmDialog
                isOpen={hapusSemua}
                onClose={() => setHapusSemua(false)}
                onConfirm={() => {
                    router.post('/master-data/ruang/hapus-semua', {}, {
                        preserveScroll: true,
                        onFinish: () => setHapusSemua(false),
                    });
                }}
                title="Hapus Semua Ruang"
                message="Seluruh ruang sekolah ini akan dihapus; unit yang menempati ruang kehilangan penempatan tetapi riwayat mutasi tetap tersimpan."
                confirmText="Hapus Semua"
            />
        </AppLayout>
    );
}
