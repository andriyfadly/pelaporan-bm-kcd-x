import { Head, useForm, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useState } from 'react';
import Modal from '@/Components/Modal';
import { UserPlus, Pencil, CircleOff, UserCheck, UserX } from 'lucide-react';

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

const labelJenis: Record<string, string> = {
    definitif: 'Definitif',
    plt: 'Plt.',
    plh: 'Plh.',
};

export default function Index({ pejabats, jabatanList, labelJabatan }: Props) {
    const [formOpen, setFormOpen] = useState(false);
    const [editTarget, setEditTarget] = useState<Pejabat | null>(null);
    const [akhiriTarget, setAkhiriTarget] = useState<Pejabat | null>(null);

    const { data, setData, post, put, processing, errors, clearErrors, reset } = useForm({
        jabatan: 'kepala_sekolah',
        jenis_jabatan: 'definitif',
        nama: '',
        pangkat_golongan: '',
        nip: '',
        mulai_menjabat: '',
        selesai_menjabat: '',
    });

    const openForm = (jabatan: string, p?: Pejabat) => {
        if (p) {
            setEditTarget(p);
            setData({
                jabatan: p.jabatan,
                jenis_jabatan: p.jenis_jabatan,
                nama: p.nama,
                pangkat_golongan: p.pangkat_golongan ?? '',
                nip: p.nip ?? '',
                mulai_menjabat: p.mulai_menjabat,
                selesai_menjabat: p.selesai_menjabat ?? '',
            });
        } else {
            setEditTarget(null);
            setData('jabatan', jabatan);
            reset('jabatan', 'nama', 'pangkat_golongan', 'nip', 'mulai_menjabat', 'selesai_menjabat');
            setData('jabatan', jabatan);
        }
        setFormOpen(true);
        clearErrors();
    };

    const closeForm = () => {
        setFormOpen(false);
        setEditTarget(null);
        reset();
        clearErrors();
    };

    const simpan = (e: React.FormEvent) => {
        e.preventDefault();
        const url = editTarget ? `/master-data/pejabat/${editTarget.id}` : '/master-data/pejabat';
        const method = editTarget ? put : post;
        method(url, {
            onSuccess: closeForm,
            preserveState: true,
        });
    };

    const { data: akhiriData, setData: setAkhiriData, put: akhiriPut, processing: akhiriProcessing, errors: akhiriErrors } = useForm({ selesai_menjabat: '' });

    const submitAkhiri = (e: React.FormEvent) => {
        e.preventDefault();
        if (!akhiriTarget) return;
        akhiriPut(`/master-data/pejabat/${akhiriTarget.id}/akhiri`, {
            onSuccess: () => {
                setAkhiriTarget(null);
                setAkhiriData('selesai_menjabat', '');
            },
        });
    };

    const pejabatByJabatan = (jabatan: string) => pejabats.filter((p) => p.jabatan === jabatan);

    return (
        <AppLayout title="Pejabat Sekolah">
            <Head title="Pejabat Sekolah" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-lg font-bold text-slate-900">Pejabat Sekolah</h1>
                    <p className="text-sm text-slate-500 mt-1">
                        Penandatangan dokumen (dipakai modul Distribusi BHP). Approval mensyaratkan ketiga jabatan terisi dan aktif.
                    </p>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {jabatanList.map((jabatan) => {
                        const list = pejabatByJabatan(jabatan);
                        const aktif = list.find((p) => p.selesai_menjabat === null);
                        const riwayat = list.filter((p) => p.selesai_menjabat !== null);
                        const label = labelJabatan[jabatan] ?? jabatan;

                        return (
                            <div key={jabatan} className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                                <div className="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                                    <div>
                                        <h2 className="text-sm font-bold text-slate-900">{label}</h2>
                                        <p className="text-[11px] text-slate-400 mt-0.5">{jabatan}</p>
                                    </div>
                                    <span className={`px-2.5 py-1 rounded-full text-[11px] font-bold ${
                                        aktif ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'
                                    }`}>
                                        {aktif ? 'Aktif' : 'Kosong'}
                                    </span>
                                </div>

                                <div className="p-5">
                                    {aktif ? (
                                        <div className="space-y-3">
                                            <div>
                                                <div className="text-sm font-semibold text-slate-800">{aktif.nama}</div>
                                                {aktif.nip && <div className="text-xs font-mono text-slate-500 mt-0.5">NIP: {aktif.nip}</div>}
                                                {aktif.pangkat_golongan && <div className="text-xs text-slate-500">{aktif.pangkat_golongan}</div>}
                                                <div className="text-xs text-slate-400 mt-1">
                                                    <span className="inline-flex items-center gap-1">
                                                        {aktif.jenis_jabatan !== 'definitif' && <span className="font-semibold text-amber-600">{labelJenis[aktif.jenis_jabatan]}</span>}
                                                        {aktif.mulai_menjabat} s.d. sekarang
                                                    </span>
                                                </div>
                                            </div>
                                            <div className="flex gap-2">
                                                <button onClick={() => openForm(jabatan, aktif)} className="inline-flex items-center gap-1.5 px-3 py-2 border border-slate-300 rounded-xl text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                                    <Pencil className="w-3.5 h-3.5" /> Edit
                                                </button>
                                                <button onClick={() => { setAkhiriData('selesai_menjabat', ''); setAkhiriTarget(aktif); }} className="inline-flex items-center gap-1.5 px-3 py-2 bg-rose-50 text-rose-700 border border-rose-200 rounded-xl text-xs font-semibold hover:bg-rose-100">
                                                    <CircleOff className="w-3.5 h-3.5" /> Akhiri
                                                </button>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="text-center py-6">
                                            <UserX className="w-8 h-8 text-slate-300 mx-auto mb-2" />
                                            <p className="text-xs text-slate-400 mb-3">Belum ada pejabat aktif</p>
                                            <button onClick={() => openForm(jabatan)} className="inline-flex items-center gap-1.5 px-3 py-2 bg-blue-600 text-white rounded-xl text-xs font-bold hover:bg-blue-700">
                                                <UserPlus className="w-3.5 h-3.5" /> Tambah {label}
                                            </button>
                                        </div>
                                    )}

                                    {riwayat.length > 0 && (
                                        <div className="mt-4 pt-3 border-t border-slate-100">
                                            <p className="text-[11px] font-bold text-slate-400 uppercase mb-2">Riwayat</p>
                                            <div className="space-y-2">
                                                {riwayat.map((r) => (
                                                    <div key={r.id} className="text-xs text-slate-500 bg-slate-50 rounded-lg px-3 py-2">
                                                        <div className="font-medium">{r.nama}</div>
                                                        <div>{r.mulai_menjabat} s.d. {r.selesai_menjabat}</div>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>

            <Modal isOpen={formOpen} onClose={closeForm} title={editTarget ? 'Edit Pejabat' : 'Tambah Pejabat'} maxWidth="lg">
                <form onSubmit={simpan} className="space-y-4">
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Jabatan</label>
                            <select value={data.jabatan} onChange={(e) => setData('jabatan', e.target.value)}
                                className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm"
                                disabled={!editTarget}>
                                {jabatanList.map((j) => (
                                    <option key={j} value={j}>{labelJabatan[j] ?? j}</option>
                                ))}
                            </select>
                            {!editTarget && <p className="text-[11px] text-slate-400 mt-1">Jabatan sudah ditentukan dari card yang dipilih.</p>}
                            {errors.jabatan && <p className="text-xs text-rose-600 mt-1">{errors.jabatan}</p>}
                        </div>
                        <div>
                            <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Jenis</label>
                            <select value={data.jenis_jabatan} onChange={(e) => setData('jenis_jabatan', e.target.value)} className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm">
                                {Object.entries(labelJenis).map(([k, v]) => (
                                    <option key={k} value={k}>{v}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Nama</label>
                            <input value={data.nama} onChange={(e) => setData('nama', e.target.value)} className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm" />
                            {errors.nama && <p className="text-xs text-rose-600 mt-1">{errors.nama}</p>}
                        </div>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Pangkat / Golongan</label>
                            <input value={data.pangkat_golongan} onChange={(e) => setData('pangkat_golongan', e.target.value)} placeholder="cth: Penata Tingkat I / III/b" className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm" />
                        </div>
                        <div>
                            <label className="block text-xs font-bold uppercase text-slate-500 mb-1">NIP</label>
                            <input value={data.nip} onChange={(e) => setData('nip', e.target.value)} className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm font-mono" />
                        </div>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Mulai Menjabat</label>
                            <input type="date" value={data.mulai_menjabat} onChange={(e) => setData('mulai_menjabat', e.target.value)} className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm" />
                            {errors.mulai_menjabat && <p className="text-xs text-rose-600 mt-1">{errors.mulai_menjabat}</p>}
                        </div>
                        <div>
                            <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Selesai Menjabat <span className="text-slate-400 normal-case font-normal">(kosongkan jika masih aktif)</span></label>
                            <input type="date" value={data.selesai_menjabat} onChange={(e) => setData('selesai_menjabat', e.target.value)} className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm" />
                            {errors.selesai_menjabat && <p className="text-xs text-rose-600 mt-1">{errors.selesai_menjabat}</p>}
                        </div>
                    </div>

                    <div className="flex justify-end gap-2">
                        <button type="button" onClick={closeForm} className="px-4 py-2 border border-slate-300 rounded-xl text-sm font-semibold">Batal</button>
                        <button type="submit" disabled={processing} className="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-bold disabled:opacity-50">
                            {editTarget ? 'Simpan Perubahan' : 'Tambah Pejabat'}
                        </button>
                    </div>
                </form>
            </Modal>

            <Modal isOpen={akhiriTarget !== null} onClose={() => setAkhiriTarget(null)} title="Akhiri Masa Jabatan" maxWidth="sm">
                <form onSubmit={submitAkhiri} className="space-y-4">
                    <p className="text-sm text-slate-600">
                        Akhiri masa jabatan <span className="font-semibold">{akhiriTarget?.nama}</span> ({akhiriTarget ? labelJabatan[akhiriTarget.jabatan] ?? akhiriTarget.jabatan : ''}).
                    </p>
                    <div>
                        <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Tanggal Selesai</label>
                        <input type="date" value={akhiriData.selesai_menjabat} onChange={(e) => setAkhiriData('selesai_menjabat', e.target.value)} className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm" required />
                        {akhiriErrors.selesai_menjabat && <p className="text-xs text-rose-600 mt-1">{akhiriErrors.selesai_menjabat}</p>}
                    </div>
                    <div className="flex justify-end gap-2">
                        <button type="button" onClick={() => setAkhiriTarget(null)} className="px-4 py-2 border border-slate-300 rounded-xl text-sm font-semibold">Batal</button>
                        <button type="submit" disabled={akhiriProcessing} className="px-4 py-2 bg-rose-600 text-white rounded-xl text-sm font-bold disabled:opacity-50">Akhiri</button>
                    </div>
                </form>
            </Modal>
        </AppLayout>
    );
}