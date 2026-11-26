import { Head, router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import EmptyState from '@/Components/EmptyState';
import Modal from '@/Components/Modal';
import { FileText, Users } from 'lucide-react';
import { tanggalHariIni } from '@/Utils/format';

interface Item {
    id: string;
    nomor_register: string;
    kode_barang: string;
    nama_barang: string | null;
    merk_tipe: string | null;
    ruang: string | null;
    tahun_perolehan: number;
    tanggal_perolehan: string | null;
    volume: number;
    satuan: string | null;
    tanggal_bast: string | null;
    keterangan: string | null;
}

interface Kelompok {
    pegawai: { id: string; nama: string | null; nip: string | null };
    ruang_dominan: string | null;
    jumlah_unit: number;
    items: Item[];
}

interface Props {
    kelompok: Kelompok[];
    filter: { search: string };
    canCetak: boolean;
}

const BULAN_SINGKAT = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

const tanggalIndonesia = (nilai: string | null): string => {
    if (!nilai) {
        return '-';
    }
    const [tahun, bulan, hari] = nilai.slice(0, 10).split('-');
    return `${Number(hari)} ${BULAN_SINGKAT[Number(bulan) - 1]} ${tahun}`;
};

export default function Index({ kelompok, filter, canCetak }: Props) {
    const [cari, setCari] = useState(filter.search);
    const [surat, setSurat] = useState<{ pegawai: Kelompok['pegawai'] } | null>(null);
    // Form dikirim native ke window baru (respons berupa berkas PDF, bukan redirect Inertia).
    const token = useRef((document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '');

    const terapkan = (nilai: string) => {
        setCari(nilai);
        router.get('/pencatatan-bm/pengguna', { search: nilai || undefined }, { preserveState: true, preserveScroll: true, replace: true });
    };

    return (
        <AppLayout>
            <Head title="Data Pengguna Barang" />

            <div className="space-y-5">
                <header>
                    <h1 className="text-xl font-bold text-slate-800">Data Kepemilikan Barang per Pegawai</h1>
                    <p className="text-sm text-slate-500">Menampilkan daftar aset kelompok pegawai berdasarkan penanggung jawab.</p>
                </header>

                <div className="flex flex-wrap items-center gap-2">
                    <input
                        value={cari}
                        onChange={(e) => terapkan(e.target.value)}
                        placeholder="Cari nama / barang / no reg..."
                        className="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm sm:w-80"
                    />
                    <a
                        href={`/pencatatan-bm/dokumen/sensus${cari ? `?search=${encodeURIComponent(cari)}` : ''}`}
                        className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700"
                    >
                        <FileText className="h-3.5 w-3.5" /> Cetak PDF Sensus
                    </a>
                </div>

                {kelompok.length === 0 ? (
                    <EmptyState
                        title="Belum ada data kepemilikan barang"
                        description="Aset muncul di sini setelah disalurkan ke pegawai lewat Buku Penyaluran."
                        icon={<Users className="h-12 w-12 text-slate-300" />}
                    />
                ) : (
                    <div className="space-y-4">
                        {kelompok.map((grup) => (
                            <section key={grup.pegawai.id} className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                                <div className="flex flex-wrap items-center gap-3 border-b border-slate-100 p-4">
                                    <div className="mr-auto">
                                        <span className="inline-block rounded-full bg-blue-50 px-2.5 py-1 text-xs font-bold uppercase text-blue-700">
                                            Memegang {grup.jumlah_unit} Item Aset
                                        </span>
                                        <h2 className="mt-1 font-bold text-slate-800">{grup.pegawai.nama ?? '-'}</h2>
                                        <p className="text-xs text-slate-500">
                                            Pegawai / Pengguna Aset Sekolah{grup.pegawai.nip ? ` · NIP ${grup.pegawai.nip}` : ''}
                                        </p>
                                    </div>
                                    {canCetak && (
                                        <button
                                            type="button"
                                            onClick={() => setSurat({ pegawai: grup.pegawai })}
                                            className="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700"
                                        >
                                            <FileText className="h-3.5 w-3.5" /> Permohonan Pengembalian
                                        </button>
                                    )}
                                </div>

                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[900px] text-sm">
                                        <thead>
                                            <tr className="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-500">
                                                <th className="px-4 py-3">No</th>
                                                <th className="px-4 py-3">No. Registrasi</th>
                                                <th className="px-4 py-3">Identitas Barang / Aset</th>
                                                <th className="px-4 py-3">Lokasi / Ruangan</th>
                                                <th className="px-4 py-3">Volume Salur</th>
                                                <th className="px-4 py-3">Waktu Pengadaan</th>
                                                <th className="px-4 py-3">Waktu Penyaluran</th>
                                                <th className="px-4 py-3">Keterangan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {grup.items.map((item, index) => (
                                                <tr key={item.id} className="border-b border-slate-50 last:border-0">
                                                    <td className="px-4 py-3 text-slate-500">{index + 1}</td>
                                                    <td className="px-4 py-3 font-mono text-xs text-slate-700">{item.nomor_register}</td>
                                                    <td className="px-4 py-3">
                                                        <p className="font-semibold text-slate-700">{item.nama_barang ?? '-'}</p>
                                                        <p className="text-xs text-slate-500">{item.merk_tipe ?? '-'} · {item.kode_barang}</p>
                                                    </td>
                                                    <td className="px-4 py-3 text-slate-600">{item.ruang ?? '-'}</td>
                                                    <td className="px-4 py-3 text-slate-600">{item.volume} {item.satuan ?? 'Unit'}</td>
                                                    <td className="px-4 py-3 text-slate-600">
                                                        {item.tanggal_perolehan ? item.tanggal_perolehan.split('-').reverse().join(' / ') : '-'}
                                                    </td>
                                                    <td className="px-4 py-3 text-slate-600">{tanggalIndonesia(item.tanggal_bast)}</td>
                                                    <td className="px-4 py-3 text-slate-600">{item.keterangan ?? '-'}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        ))}
                    </div>
                )}
            </div>

            {surat && (
                <Modal isOpen onClose={() => setSurat(null)} title="Pilih Dokumen & Tanggal Surat">
                    <div className="space-y-3">
                        <p className="text-sm text-slate-500">
                            Permohonan pengembalian untuk <strong>{surat.pegawai.nama ?? '-'}</strong>.
                        </p>

                        <form
                            method="post"
                            target="_blank"
                            action={`/pencatatan-bm/dokumen/surat-pengembalian-pegawai/${surat.pegawai.id}`}
                            className="space-y-3"
                        >
                            <input type="hidden" name="_token" value={token.current} />

                            <label className="block text-xs font-semibold text-slate-600">
                                Nomor Surat
                                <input
                                    name="nomor"
                                    required
                                    placeholder="Contoh: 002/AR.03.05-CADISDIK.WIL-X"
                                    className="mt-1 min-h-10 w-full rounded-xl border border-slate-300 px-3 text-sm"
                                />
                            </label>

                            <label className="block text-xs font-semibold text-slate-600">
                                Tanggal Surat
                                <input
                                    type="date"
                                    name="tanggal"
                                    defaultValue={tanggalHariIni()}
                                    className="mt-1 min-h-10 w-full rounded-xl border border-slate-300 px-3 text-sm"
                                />
                            </label>

                            <div className="flex justify-end gap-2 pt-2">
                                <button
                                    type="button"
                                    onClick={() => setSurat(null)}
                                    className="min-h-10 rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-600"
                                >
                                    Batal
                                </button>
                                <button type="submit" className="inline-flex min-h-10 items-center gap-1.5 rounded-xl bg-blue-600 px-4 text-sm font-bold text-white">
                                    <FileText className="h-4 w-4" /> Cetak Dokumen
                                </button>
                            </div>
                        </form>
                    </div>
                </Modal>
            )}
        </AppLayout>
    );
}
