import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import EmptyState from '@/Components/EmptyState';

interface Sekolah {
    id: string;
    nama_sekolah: string;
    npsn: string | null;
    kota_kab: string;
    alamat: string | null;
    telepon: string | null;
    website: string | null;
    email: string | null;
}

interface Props {
    sekolah: Sekolah | null;
    sekolahOptions: { id: string; nama_sekolah: string }[];
    sekolahTerpilih: string | null;
}

export default function Edit({ sekolah, sekolahOptions, sekolahTerpilih }: Props) {
    const form = useForm({
        sekolah_id: sekolahTerpilih ?? '',
        alamat: sekolah?.alamat ?? '',
        telepon: sekolah?.telepon ?? '',
        website: sekolah?.website ?? '',
        email: sekolah?.email ?? '',
    });

    const pilihSekolah = (id: string) => {
        form.setData('sekolah_id', id);
        router.get('/master-data/profil-sekolah', id ? { sekolah_id: id } : {}, { preserveState: false });
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put('/master-data/profil-sekolah');
    };

    // Admin KCD tanpa sekolah_id: pilih sekolah dulu sebelum bisa mengisi profil.
    if (!sekolah) {
        return (
            <AppLayout title="Profil Sekolah">
                <Head title="Profil Sekolah" />

                <div className="space-y-6">
                    <div>
                        <h1 className="text-lg font-bold text-slate-900">Profil Sekolah</h1>
                        <p className="text-sm text-slate-500">Pilih sekolah terlebih dahulu untuk mengelola profilnya.</p>
                    </div>

                    <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-4">
                        {sekolahOptions.length === 0 ? (
                            <EmptyState
                                title="Belum ada sekolah"
                                description="Tambahkan data sekolah terlebih dahulu sebelum mengelola profil."
                            />
                        ) : (
                            <div>
                                <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Sekolah</label>
                                <select
                                    value={form.data.sekolah_id}
                                    onChange={(e) => pilihSekolah(e.target.value)}
                                    className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm"
                                >
                                    <option value="">Pilih sekolah...</option>
                                    {sekolahOptions.map((s) => (
                                        <option key={s.id} value={s.id}>{s.nama_sekolah}</option>
                                    ))}
                                </select>
                                {form.errors.sekolah_id && (
                                    <p className="text-xs text-rose-600 mt-1">{form.errors.sekolah_id}</p>
                                )}
                            </div>
                        )}
                    </div>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout title="Profil Sekolah">
            <Head title="Profil Sekolah" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-lg font-bold text-slate-900">Profil Sekolah</h1>
                    <p className="text-sm text-slate-500">Data kontak sekolah untuk kop dan pengirim dokumen.</p>
                </div>

                <form onSubmit={submit} className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-5">
                    <input type="hidden" value={form.data.sekolah_id} readOnly />
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Nama Sekolah</label>
                            <input value={sekolah.nama_sekolah} disabled className="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm bg-slate-50 text-slate-500" />
                        </div>
                        <div>
                            <label className="block text-xs font-bold uppercase text-slate-500 mb-1">NPSN</label>
                            <input value={sekolah.npsn ?? '-'} disabled className="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm bg-slate-50 text-slate-500" />
                        </div>
                        <div>
                            <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Kota/Kabupaten</label>
                            <input value={sekolah.kota_kab} disabled className="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm bg-slate-50 text-slate-500" />
                        </div>
                    </div>

                    <p className="text-xs text-slate-400">Nama, NPSN, dan kota/kabupaten dikelola admin. Anda dapat mengisi data kontak di bawah.</p>

                    <div>
                        <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Alamat</label>
                        <textarea
                            value={form.data.alamat}
                            onChange={(e) => form.setData('alamat', e.target.value)}
                            rows={3}
                            className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm"
                        />
                        {form.errors.alamat && <p className="text-xs text-rose-600 mt-1">{form.errors.alamat}</p>}
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Nomor Telepon</label>
                            <input
                                value={form.data.telepon}
                                onChange={(e) => form.setData('telepon', e.target.value)}
                                className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm"
                            />
                            {form.errors.telepon && <p className="text-xs text-rose-600 mt-1">{form.errors.telepon}</p>}
                        </div>
                        <div>
                            <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Website</label>
                            <input
                                value={form.data.website}
                                onChange={(e) => form.setData('website', e.target.value)}
                                placeholder="https://"
                                className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm"
                            />
                            {form.errors.website && <p className="text-xs text-rose-600 mt-1">{form.errors.website}</p>}
                        </div>
                        <div>
                            <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Email</label>
                            <input
                                type="email"
                                value={form.data.email}
                                onChange={(e) => form.setData('email', e.target.value)}
                                className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm"
                            />
                            {form.errors.email && <p className="text-xs text-rose-600 mt-1">{form.errors.email}</p>}
                        </div>
                    </div>

                    <button type="submit" disabled={form.processing} className="px-4 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-bold disabled:opacity-50">
                        Simpan Profil
                    </button>
                </form>
            </div>
        </AppLayout>
    );
}
