import { Head, useForm, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useState } from 'react';

interface Pejabat {
    id: string;
    jabatan: string;
    jenis_jabatan: string;
    nama: string;
    pangkat_golongan: string | null;
    nip: string | null;
    mulai_menjabat: string;
    selesai_menjabat: string | null;
}

interface Props {
    pejabats: Pejabat[];
    jabatanList: string[];
    labelJabatan: Record<string, string>;
}

interface FormShape {
    id?: string;
    jabatan: string;
    jenis_jabatan: string;
    nama: string;
    pangkat_golongan: string;
    nip: string;
    mulai_menjabat: string;
    selesai_menjabat: string;
}

const kosong = (jabatan = 'kepala_sekolah'): FormShape => ({
    jabatan,
    jenis_jabatan: 'definitif',
    nama: '',
    pangkat_golongan: '',
    nip: '',
    mulai_menjabat: '',
    selesai_menjabat: '',
});

const labelJenis: Record<string, string> = {
    definitif: 'Definitif',
    plt: 'Plt.',
    plh: 'Plh.',
};

export default function Index({ pejabats, jabatanList, labelJabatan }: Props) {
    const [form, setForm] = useState<FormShape>(kosong());
    const [editing, setEditing] = useState(false);
    const { data, setData, post, put, processing, errors, clearErrors } = useForm<FormShape>(form);

    const simpan = (e: React.FormEvent) => {
        e.preventDefault();
        const url = editing && data.id ? `/master-data/pejabat/${data.id}` : '/master-data/pejabat';
        const method = editing && data.id ? put : post;
        method(url, {
            onSuccess: () => {
                setForm(kosong());
                setEditing(false);
                clearErrors();
            },
            preserveState: true,
        });
    };

    const mulaiEdit = (p: Pejabat) => {
        setEditing(true);
        setForm({
            id: p.id,
            jabatan: p.jabatan,
            jenis_jabatan: p.jenis_jabatan,
            nama: p.nama,
            pangkat_golongan: p.pangkat_golongan ?? '',
            nip: p.nip ?? '',
            mulai_menjabat: p.mulai_menjabat,
            selesai_menjabat: p.selesai_menjabat ?? '',
        });
        clearErrors();
    };

    const batal = () => {
        setEditing(false);
        setForm(kosong());
        clearErrors();
    };

    const akhiri = (p: Pejabat) => {
        const tanggal = window.prompt('Tanggal akhir masa jabatan (YYYY-MM-DD):');
        if (!tanggal) return;
        router.put(`/master-data/pejabat/${p.id}/akhiri`, { selesai_menjabat: tanggal });
    };

    const aktif = (p: Pejabat) => p.selesai_menjabat === null;
    const semuaJabatanTerisi = jabatanList.every((j) => pejabats.some((p) => p.jabatan === j && aktif(p)));

    return (
        <AppLayout title="Pejabat Sekolah">
            <Head title="Pejabat Sekolah" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-lg font-bold text-slate-900">Pejabat Sekolah</h1>
                    <p className="text-sm text-slate-500">
                        Penandatangan dokumen (dipakai modul Distribusi BHP). Approval pengajuan mensyaratkan ketiga jabatan terisi dan aktif.
                    </p>
                    <div className={`mt-2 inline-block px-3 py-1.5 rounded-xl text-xs font-bold ${semuaJabatanTerisi ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'}`}>
                        {semuaJabatanTerisi ? 'Ketiga jabatan terisi — approval siap.' : 'Belum semua jabatan terisi — approval akan ditolak.'}
                    </div>
                </div>

                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 text-slate-500 uppercase text-xs">
                            <tr>
                                <th className="text-left py-3 px-4">Jabatan</th>
                                <th className="text-left py-3 px-4">Nama</th>
                                <th className="text-left py-3 px-4">Pangkat/Gol</th>
                                <th className="text-left py-3 px-4">NIP</th>
                                <th className="text-left py-3 px-4">Masa Jabatan</th>
                                <th className="text-right py-3 px-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {pejabats.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="py-10 text-center text-slate-400 text-xs">
                                        Belum ada data pejabat.
                                    </td>
                                </tr>
                            ) : (
                                pejabats.map((p) => (
                                    <tr key={p.id} className={aktif(p) ? '' : 'opacity-60'}>
                                        <td className="py-3 px-4">
                                            <span className="font-semibold">{labelJabatan[p.jabatan] ?? p.jabatan}</span>
                                            <span className={`ml-2 px-2 py-0.5 rounded-lg text-[11px] font-bold ${aktif(p) ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'}`}>
                                                {aktif(p) ? 'Aktif' : 'Selesai'}
                                            </span>
                                            {p.jenis_jabatan !== 'definitif' && (
                                                <span className="ml-1 text-[11px] text-slate-400">{labelJenis[p.jenis_jabatan]}</span>
                                            )}
                                        </td>
                                        <td className="py-3 px-4">{p.nama}</td>
                                        <td className="py-3 px-4">{p.pangkat_golongan ?? '-'}</td>
                                        <td className="py-3 px-4 font-mono text-xs">{p.nip ?? '-'}</td>
                                        <td className="py-3 px-4 text-xs">
                                            {p.mulai_menjabat} s.d. {p.selesai_menjabat ?? 'sekarang'}
                                        </td>
                                        <td className="py-3 px-4 text-right space-x-2">
                                            {aktif(p) && (
                                                <>
                                                    <button onClick={() => mulaiEdit(p)} className="px-3 py-1.5 border border-slate-300 rounded-lg text-xs font-semibold">
                                                        Edit
                                                    </button>
                                                    <button onClick={() => akhiri(p)} className="px-3 py-1.5 bg-rose-50 text-rose-700 border border-rose-200 rounded-lg text-xs font-semibold">
                                                        Akhiri
                                                    </button>
                                                </>
                                            )}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                <form onSubmit={simpan} className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-4 max-w-3xl">
                    <h2 className="font-bold text-slate-900 text-sm">{editing ? 'Edit Pejabat' : 'Tambah Pejabat'}</h2>

                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Jabatan</label>
                            <select
                                value={data.jabatan}
                                onChange={(e) => setData('jabatan', e.target.value)}
                                className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm"
                            >
                                {jabatanList.map((j) => (
                                    <option key={j} value={j}>{labelJabatan[j] ?? j}</option>
                                ))}
                            </select>
                            {errors.jabatan && <p className="text-xs text-rose-600 mt-1">{errors.jabatan}</p>}
                        </div>
                        <div>
                            <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Jenis</label>
                            <select
                                value={data.jenis_jabatan}
                                onChange={(e) => setData('jenis_jabatan', e.target.value)}
                                className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm"
                            >
                                {Object.entries(labelJenis).map(([k, v]) => (
                                    <option key={k} value={k}>{v}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Nama</label>
                            <input
                                value={data.nama}
                                onChange={(e) => setData('nama', e.target.value)}
                                className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm"
                            />
                            {errors.nama && <p className="text-xs text-rose-600 mt-1">{errors.nama}</p>}
                        </div>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Pangkat / Golongan</label>
                            <input
                                value={data.pangkat_golongan}
                                onChange={(e) => setData('pangkat_golongan', e.target.value)}
                                placeholder="cth: Penata Tingkat I / III/b"
                                className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm"
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-bold uppercase text-slate-500 mb-1">NIP</label>
                            <input
                                value={data.nip}
                                onChange={(e) => setData('nip', e.target.value)}
                                className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm font-mono"
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Mulai Menjabat</label>
                            <input
                                type="date"
                                value={data.mulai_menjabat}
                                onChange={(e) => setData('mulai_menjabat', e.target.value)}
                                className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm"
                            />
                            {errors.mulai_menjabat && <p className="text-xs text-rose-600 mt-1">{errors.mulai_menjabat}</p>}
                        </div>
                        <div>
                            <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Selesai Menjabat (opsional)</label>
                            <input
                                type="date"
                                value={data.selesai_menjabat}
                                onChange={(e) => setData('selesai_menjabat', e.target.value)}
                                className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm"
                            />
                            {errors.selesai_menjabat && <p className="text-xs text-rose-600 mt-1">{errors.selesai_menjabat}</p>}
                        </div>
                    </div>

                    <div className="flex gap-2">
                        <button type="submit" disabled={processing} className="px-4 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-bold disabled:opacity-50">
                            {editing ? 'Simpan Perubahan' : 'Tambah Pejabat'}
                        </button>
                        {editing && (
                            <button type="button" onClick={batal} className="px-4 py-2.5 border border-slate-300 rounded-xl text-sm font-semibold">
                                Batal
                            </button>
                        )}
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
