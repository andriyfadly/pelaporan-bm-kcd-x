import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import EmptyState from '@/Components/EmptyState';
import Modal from '@/Components/Modal';
import { Ban, Download, Pencil } from 'lucide-react';

interface BarisBa {
    id: string;
    nomor: string | null;
    no_urut: string;
    tanggal: string | null;
    dibatalkan: boolean;
    penerima: string;
    nama_barang: string;
    no_reg: string;
    kondisi: string;
}

interface Props {
    daftar: BarisBa[];
    kodeBast: string;
    total: number;
}

export default function Index({ daftar, kodeBast, total }: Props) {
    const [editTarget, setEditTarget] = useState<BarisBa | null>(null);
    const [urut, setUrut] = useState('');
    const [batalTarget, setBatalTarget] = useState<BarisBa | null>(null);
    const [alasan, setAlasan] = useState('');
    const [proses, setProses] = useState(false);
    const [cari, setCari] = useState('');

    const daftarTampil = daftar.filter((item) => item.penerima.toLowerCase().includes(cari.toLowerCase()));

    const bukaEdit = (item: BarisBa) => {
        setEditTarget(item);
        setUrut(item.no_urut);
    };

    const simpanNomor = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editTarget) {
            return;
        }

        setProses(true);
        router.put(
            `/pencatatan-bm/berita-acara/${editTarget.id}`,
            { nomor: kodeBast ? `${urut}/${kodeBast}` : urut },
            {
                preserveScroll: true,
                onSuccess: () => setEditTarget(null),
                onFinish: () => setProses(false),
            },
        );
    };

    const batalkan = () => {
        if (!batalTarget) {
            return;
        }

        router.delete(`/pencatatan-bm/berita-acara/${batalTarget.id}`, {
            data: { alasan },
            preserveScroll: true,
            onSuccess: () => setBatalTarget(null),
        });
    };

    return (
        <AppLayout>
            <Head title="Rekapan Berita Acara" />

            <div className="space-y-5">
                <header>
                    <h1 className="text-xl font-bold text-slate-800">Rekapan Berita Acara (BAST)</h1>
                    <p className="text-sm text-slate-500">Daftar rekapan nomor surat BAST otomatis dan unduh laporan Excel.</p>
                </header>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    <div className="flex flex-wrap items-center gap-2 border-b border-slate-100 p-4">
                        <span className="rounded-full bg-blue-50 px-2.5 py-1 text-[11px] font-bold uppercase text-blue-700">
                            {total} Total BA
                        </span>
                        <input
                            value={cari}
                            onChange={(e) => setCari(e.target.value)}
                            placeholder="Cari nama penerima..."
                            className="ml-auto w-full rounded-xl border border-slate-300 px-3 py-2 text-sm sm:w-72"
                        />
                        <a
                            href="/pencatatan-bm/berita-acara/export"
                            className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700"
                        >
                            <Download className="h-3.5 w-3.5" /> Export ke Excel (.xlsx)
                        </a>
                    </div>

                    {daftarTampil.length === 0 ? (
                        <EmptyState
                            title={daftar.length === 0 ? 'Belum ada data history BAST terekam.' : 'Nama penerima tidak ditemukan dalam rekapan.'}
                            description="BAST dibuat dari Buku Penyaluran."
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[1000px] text-sm">
                                <thead>
                                    <tr className="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-500">
                                        <th className="px-4 py-3">No</th>
                                        <th className="px-4 py-3">No Urut</th>
                                        <th className="px-4 py-3">Nomor Surat Lengkap</th>
                                        <th className="px-4 py-3">Nama Barang</th>
                                        <th className="px-4 py-3">No. Reg</th>
                                        <th className="px-4 py-3">Kondisi Barang</th>
                                        <th className="px-4 py-3">Penerima</th>
                                        <th className="px-4 py-3">Tanggal BA</th>
                                        <th className="px-4 py-3 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {daftarTampil.map((item, index) => (
                                        <tr key={item.id} className="border-b border-slate-50 last:border-0">
                                            <td className="px-4 py-3 text-slate-500">{index + 1}</td>
                                            <td className="px-4 py-3 font-mono text-xs text-slate-700">{item.no_urut || '-'}</td>
                                            <td className="px-4 py-3 font-mono text-xs text-slate-700">{item.nomor ?? '-'}</td>
                                            <td className="px-4 py-3 text-slate-700">{item.nama_barang}</td>
                                            <td className="px-4 py-3 font-mono text-xs text-slate-600">{item.no_reg}</td>
                                            <td className="px-4 py-3 text-slate-600">{item.kondisi}</td>
                                            <td className="px-4 py-3 text-slate-700">{item.penerima}</td>
                                            <td className="px-4 py-3 text-slate-600">{item.tanggal ?? '-'}</td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center justify-end gap-1">
                                                    {item.dibatalkan ? (
                                                        <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-500">Selesai</span>
                                                    ) : (
                                                        <button
                                                            type="button"
                                                            onClick={() => bukaEdit(item)}
                                                            className="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-700"
                                                        >
                                                            <Pencil className="h-3.5 w-3.5" /> {item.nomor ? 'Edit No' : 'Input No'}
                                                        </button>
                                                    )}
                                                    {item.nomor && (
                                                        <button
                                                            type="button"
                                                            onClick={() => setBatalTarget(item)}
                                                            title="Batalkan nomor"
                                                            aria-label="Batalkan nomor"
                                                            className="rounded-lg p-1.5 text-rose-500 hover:bg-rose-50"
                                                        >
                                                            <Ban className="h-3.5 w-3.5" />
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>

            <Modal isOpen={editTarget !== null} onClose={() => setEditTarget(null)} title={editTarget?.nomor ? 'Edit Nomor Urut BA' : 'Input Nomor Urut BA'} maxWidth="md">
                <p className="text-sm text-slate-500">
                    Lengkapi urutan nomor dokumen Berita Acara untuk barang: <strong>{editTarget?.nama_barang}</strong>
                </p>

                <form onSubmit={simpanNomor} className="mt-4 space-y-3">
                    <label className="block text-xs font-semibold text-slate-600">
                        Nomor Urut Surat
                        <input
                            value={urut}
                            onChange={(e) => setUrut(e.target.value)}
                            placeholder="Contoh: 94"
                            required
                            className="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
                        />
                    </label>

                    <label className="block text-xs font-semibold text-slate-600">
                        Kode Klasifikasi Internal (Readonly)
                        <input
                            readOnly
                            value={kodeBast}
                            className="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-sm text-slate-500"
                        />
                    </label>

                    <p className="text-[11px] italic text-slate-400">
                        *Sistem otomatis menggabungkan kedua kolom di atas menjadi satu kesatuan nomor surat utuh.
                    </p>

                    <div className="flex justify-end gap-2 pt-2">
                        <button type="button" onClick={() => setEditTarget(null)} className="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600">
                            Batal
                        </button>
                        <button type="submit" disabled={proses} className="rounded-xl bg-blue-600 px-4 py-2 text-sm font-bold text-white disabled:opacity-60">
                            Simpan Nomor
                        </button>
                    </div>
                </form>
            </Modal>

            <Modal isOpen={batalTarget !== null} onClose={() => setBatalTarget(null)} title="Batalkan Nomor Urut" maxWidth="md">
                <p className="text-sm text-slate-600">
                    Apakah Anda yakin ingin membatalkan dan mengosongkan kembali nomor urut dokumen ini?
                </p>
                <label className="mt-3 block text-xs font-semibold text-slate-600">
                    Alasan pembatalan
                    <input
                        value={alasan}
                        onChange={(e) => setAlasan(e.target.value)}
                        placeholder="Alasan pembatalan"
                        required
                        className="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
                    />
                </label>
                <div className="mt-4 flex justify-end gap-2">
                    <button type="button" onClick={() => setBatalTarget(null)} className="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600">
                        Batal
                    </button>
                    <button type="button" onClick={batalkan} className="rounded-xl bg-rose-600 px-4 py-2 text-sm font-bold text-white">
                        Kosongkan Nomor
                    </button>
                </div>
            </Modal>
        </AppLayout>
    );
}
