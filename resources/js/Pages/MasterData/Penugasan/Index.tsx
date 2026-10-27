import { Head, useForm } from '@inertiajs/react';
import React, { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Modal from '@/Components/Modal';
import EmptyState from '@/Components/EmptyState';
import { CalendarClock, ArrowRightLeft, Plus, UserRoundCheck } from 'lucide-react';

interface PegawaiRingkas {
    id: string;
    nama_lengkap: string;
    nip: string | null;
    pangkat_golongan: string | null;
}

interface JabatanRingkas {
    id: string;
    kode: string;
    nama: string;
}

interface Penugasan {
    id: string;
    pegawai_id: string;
    jabatan_id: string;
    jenis_penugasan: string;
    mulai_menjabat: string;
    selesai_menjabat: string | null;
    pegawai: PegawaiRingkas | null;
    jabatan: JabatanRingkas | null;
}

interface Props {
    penugasan: Penugasan[];
    jabatanList: JabatanRingkas[];
    jenisList: string[];
    labelJenis: Record<string, string>;
}

interface FormData {
    nama: string;
    nip: string;
    pangkat_golongan: string;
    jabatan: string;
    jenis_penugasan: string;
    mulai_menjabat: string;
    selesai_menjabat: string;
}

const FORM_KOSONG: FormData = {
    nama: '',
    nip: '',
    pangkat_golongan: '',
    jabatan: '',
    jenis_penugasan: 'definitif',
    mulai_menjabat: '',
    selesai_menjabat: '',
};

export default function Index({ penugasan, jabatanList, jenisList, labelJenis }: Props) {
    const [formOpen, setFormOpen] = useState(false);
    const [editTarget, setEditTarget] = useState<Penugasan | null>(null);
    const [akhiriTarget, setAkhiriTarget] = useState<Penugasan | null>(null);
    const [mutasiTarget, setMutasiTarget] = useState<Penugasan | null>(null);

    const form = useForm<FormData>({ ...FORM_KOSONG });
    const akhiriForm = useForm({ selesai_menjabat: '' });
    const mutasiForm = useForm({ jabatan: '', jenis_penugasan: 'definitif', mulai_menjabat: '' });

    const buka = (item: Penugasan | null) => {
        setEditTarget(item);
        form.setData(
            item
                ? {
                      nama: item.pegawai?.nama_lengkap ?? '',
                      nip: item.pegawai?.nip ?? '',
                      pangkat_golongan: item.pegawai?.pangkat_golongan ?? '',
                      jabatan: item.jabatan?.kode ?? '',
                      jenis_penugasan: item.jenis_penugasan,
                      mulai_menjabat: item.mulai_menjabat.slice(0, 10),
                      selesai_menjabat: item.selesai_menjabat?.slice(0, 10) ?? '',
                  }
                : { ...FORM_KOSONG, jabatan: jabatanList[0]?.kode ?? '' },
        );
        form.clearErrors();
        setFormOpen(true);
    };

    const simpan = (e: React.FormEvent) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setFormOpen(false);
                form.reset();
            },
        };

        if (editTarget) {
            form.put(`/master-data/penugasan/${editTarget.id}`, options);
            return;
        }

        form.post('/master-data/penugasan', options);
    };

    const submitAkhiri = (e: React.FormEvent) => {
        e.preventDefault();
        if (!akhiriTarget) {
            return;
        }

        akhiriForm.put(`/master-data/penugasan/${akhiriTarget.id}/akhiri`, {
            preserveScroll: true,
            onSuccess: () => {
                setAkhiriTarget(null);
                akhiriForm.reset();
            },
        });
    };

    const bukaMutasi = (item: Penugasan) => {
        setMutasiTarget(item);
        mutasiForm.setData({
            jabatan: jabatanList.find((j) => j.kode !== item.jabatan?.kode)?.kode ?? '',
            jenis_penugasan: 'definitif',
            mulai_menjabat: '',
        });
        mutasiForm.clearErrors();
    };

    const submitMutasi = (e: React.FormEvent) => {
        e.preventDefault();
        if (!mutasiTarget) {
            return;
        }

        mutasiForm.put(`/master-data/penugasan/${mutasiTarget.id}/mutasi`, {
            preserveScroll: true,
            onSuccess: () => setMutasiTarget(null),
        });
    };

    const labelJabatan = (item: Penugasan) => `${labelJenis[item.jenis_penugasan] ?? ''}${item.jabatan?.nama ?? '-'}`;

    return (
        <AppLayout>
            <Head title="Riwayat Penugasan Pegawai" />

            <div className="space-y-5">
                <header className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-bold text-slate-800">Riwayat Penugasan Pegawai</h1>
                        <p className="text-sm text-slate-500">
                            Satu jabatan hanya punya satu pemegang pada satu waktu. Penugasan baru menutup periode lama, bukan
                            menimpanya, sehingga dokumen bertanggal lampau tetap memakai pejabat saat itu.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={() => buka(null)}
                        className="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700"
                    >
                        <Plus className="w-4 h-4" /> Tambah Penugasan
                    </button>
                </header>

                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    {penugasan.length === 0 ? (
                        <EmptyState
                            title="Belum ada penugasan"
                            description="Tetapkan pegawai pada jabatan agar dokumen punya penandatangan."
                            icon={<UserRoundCheck className="w-12 h-12 text-slate-300" />}
                        />
                    ) : (
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-400">
                                    <th className="px-4 py-3">Pegawai</th>
                                    <th className="px-4 py-3">Jabatan</th>
                                    <th className="px-4 py-3">Periode</th>
                                    <th className="px-4 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {penugasan.map((item) => (
                                    <tr key={item.id} className="border-b border-slate-50 last:border-0">
                                        <td className="px-4 py-3">
                                            <p className="font-semibold text-slate-700">{item.pegawai?.nama_lengkap ?? '-'}</p>
                                            <p className="font-mono text-[11px] text-slate-400">{item.pegawai?.nip ?? '-'}</p>
                                        </td>
                                        <td className="px-4 py-3 text-slate-600">{labelJabatan(item)}</td>
                                        <td className="px-4 py-3 text-xs text-slate-500">
                                            {item.mulai_menjabat.slice(0, 10)} &rarr;{' '}
                                            {item.selesai_menjabat?.slice(0, 10) ?? <span className="font-bold text-emerald-600">sekarang</span>}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-end gap-1">
                                                {item.selesai_menjabat === null && (
                                                    <>
                                                        <button
                                                            type="button"
                                                            onClick={() => {
                                                                setAkhiriTarget(item);
                                                                akhiriForm.setData('selesai_menjabat', '');
                                                                akhiriForm.clearErrors();
                                                            }}
                                                            aria-label={`Akhiri penugasan ${item.pegawai?.nama_lengkap ?? ''}`}
                                                            className="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100"
                                                        >
                                                            <CalendarClock className="w-4 h-4" />
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() => bukaMutasi(item)}
                                                            aria-label={`Mutasi ${item.pegawai?.nama_lengkap ?? ''}`}
                                                            className="rounded-lg p-1.5 text-blue-600 hover:bg-blue-50"
                                                        >
                                                            <ArrowRightLeft className="w-4 h-4" />
                                                        </button>
                                                    </>
                                                )}
                                                <button
                                                    type="button"
                                                    onClick={() => buka(item)}
                                                    className="rounded-lg px-2 py-1 text-[11px] font-bold text-slate-500 hover:bg-slate-100"
                                                >
                                                    Ubah
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

            <Modal isOpen={formOpen} onClose={() => setFormOpen(false)} title={editTarget ? 'Ubah Penugasan' : 'Tambah Penugasan'}>
                <form onSubmit={simpan} className="space-y-3">
                    <Field label="Nama Pegawai" error={form.errors.nama}>
                        <input
                            type="text"
                            value={form.data.nama}
                            onChange={(e) => form.setData('nama', e.target.value)}
                            className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                        />
                    </Field>
                    <Field label="NIP" error={form.errors.nip}>
                        <input
                            type="text"
                            value={form.data.nip}
                            onChange={(e) => form.setData('nip', e.target.value)}
                            className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 font-mono text-sm"
                        />
                    </Field>
                    <Field label="Pangkat / Golongan" error={form.errors.pangkat_golongan}>
                        <input
                            type="text"
                            value={form.data.pangkat_golongan}
                            onChange={(e) => form.setData('pangkat_golongan', e.target.value)}
                            className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                        />
                    </Field>
                    <Field label="Jabatan" error={form.errors.jabatan}>
                        <select
                            value={form.data.jabatan}
                            onChange={(e) => form.setData('jabatan', e.target.value)}
                            className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                        >
                            <option value="">Pilih jabatan</option>
                            {jabatanList.map((jabatan) => (
                                <option key={jabatan.id} value={jabatan.kode}>
                                    {jabatan.nama}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Jenis Penugasan" error={form.errors.jenis_penugasan}>
                        <select
                            value={form.data.jenis_penugasan}
                            onChange={(e) => form.setData('jenis_penugasan', e.target.value)}
                            className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                        >
                            {jenisList.map((jenis) => (
                                <option key={jenis} value={jenis}>
                                    {jenis === 'definitif' ? 'Definitif' : jenis.toUpperCase()}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Mulai Menjabat" error={form.errors.mulai_menjabat}>
                        <input
                            type="date"
                            value={form.data.mulai_menjabat}
                            onChange={(e) => form.setData('mulai_menjabat', e.target.value)}
                            className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                        />
                    </Field>
                    <Field label="Selesai Menjabat (kosongkan bila masih menjabat)" error={form.errors.selesai_menjabat}>
                        <input
                            type="date"
                            value={form.data.selesai_menjabat}
                            onChange={(e) => form.setData('selesai_menjabat', e.target.value)}
                            className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                        />
                    </Field>
                    <FormActions processing={form.processing} onCancel={() => setFormOpen(false)} />
                </form>
            </Modal>

            <Modal isOpen={akhiriTarget !== null} onClose={() => setAkhiriTarget(null)} title="Akhiri Masa Jabatan">
                <form onSubmit={submitAkhiri} className="space-y-3">
                    <p className="text-xs text-slate-500">
                        {akhiriTarget?.pegawai?.nama_lengkap} — {akhiriTarget ? labelJabatan(akhiriTarget) : ''}. Riwayat tetap
                        tersimpan dan dokumen lama tidak berubah.
                    </p>
                    <Field label="Tanggal Selesai" error={akhiriForm.errors.selesai_menjabat}>
                        <input
                            type="date"
                            value={akhiriForm.data.selesai_menjabat}
                            onChange={(e) => akhiriForm.setData('selesai_menjabat', e.target.value)}
                            className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                        />
                    </Field>
                    <FormActions processing={akhiriForm.processing} onCancel={() => setAkhiriTarget(null)} />
                </form>
            </Modal>

            <Modal isOpen={mutasiTarget !== null} onClose={() => setMutasiTarget(null)} title="Mutasi Pegawai ke Jabatan Lain">
                <form onSubmit={submitMutasi} className="space-y-3">
                    <p className="text-xs text-slate-500">
                        {mutasiTarget?.pegawai?.nama_lengkap} pindah dari {mutasiTarget ? labelJabatan(mutasiTarget) : ''}.
                        Penugasan lama ditutup sehari sebelum tanggal mulai yang baru.
                    </p>
                    <Field label="Jabatan Baru" error={mutasiForm.errors.jabatan}>
                        <select
                            value={mutasiForm.data.jabatan}
                            onChange={(e) => mutasiForm.setData('jabatan', e.target.value)}
                            className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                        >
                            <option value="">Pilih jabatan</option>
                            {jabatanList.map((jabatan) => (
                                <option key={jabatan.id} value={jabatan.kode}>
                                    {jabatan.nama}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Jenis Penugasan" error={mutasiForm.errors.jenis_penugasan}>
                        <select
                            value={mutasiForm.data.jenis_penugasan}
                            onChange={(e) => mutasiForm.setData('jenis_penugasan', e.target.value)}
                            className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                        >
                            {jenisList.map((jenis) => (
                                <option key={jenis} value={jenis}>
                                    {jenis === 'definitif' ? 'Definitif' : jenis.toUpperCase()}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Mulai Menjabat" error={mutasiForm.errors.mulai_menjabat}>
                        <input
                            type="date"
                            value={mutasiForm.data.mulai_menjabat}
                            onChange={(e) => mutasiForm.setData('mulai_menjabat', e.target.value)}
                            className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                        />
                    </Field>
                    <FormActions processing={mutasiForm.processing} onCancel={() => setMutasiTarget(null)} />
                </form>
            </Modal>
        </AppLayout>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return (
        <label className="block text-xs font-semibold text-slate-600">
            {label}
            {children}
            {error && <p className="mt-1 text-xs text-rose-600">{error}</p>}
        </label>
    );
}

function FormActions({ processing, onCancel }: { processing: boolean; onCancel: () => void }) {
    return (
        <div className="flex justify-end gap-2 pt-2">
            <button
                type="button"
                onClick={onCancel}
                className="rounded-xl px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100"
            >
                Batal
            </button>
            <button
                type="submit"
                disabled={processing}
                className="rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700 disabled:opacity-60"
            >
                Simpan
            </button>
        </div>
    );
}
