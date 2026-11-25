import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Modal from '@/Components/Modal';
import { AlertCircle, Boxes, CheckCircle2, PackageX, Wrench } from 'lucide-react';

interface Ringkasan {
    totalUnit: number;
    kondisi: Record<string, number>;
}

interface Props {
    ringkasan: Ringkasan;
}

interface Rincian {
    nama_barang: string | null;
    merk_tipe: string | null;
    nomor_register: string;
    ruang: string | null;
    pegawai: string | null;
}

const KARTU: { kode: 'total' | keyof Ringkasan['kondisi']; label: string; ikon: typeof Boxes; kelas: string }[] = [
    { kode: 'total', label: 'Total Barang (Unit)', ikon: Boxes, kelas: 'text-slate-700 bg-slate-100' },
    { kode: 'B', label: 'Kondisi Baik', ikon: CheckCircle2, kelas: 'text-emerald-700 bg-emerald-50' },
    { kode: 'RR', label: 'Rusak Ringan', ikon: Wrench, kelas: 'text-amber-700 bg-amber-50' },
    { kode: 'RB', label: 'Rusak Berat', ikon: AlertCircle, kelas: 'text-rose-700 bg-rose-50' },
    { kode: 'H', label: 'Barang Hilang', ikon: PackageX, kelas: 'text-slate-600 bg-slate-100' },
];

export default function Dashboard({ ringkasan }: Props) {
    const [angka, setAngka] = useState<Ringkasan>(ringkasan);
    const [kode, setKode] = useState<string | null>(null);
    const [rincian, setRincian] = useState<Rincian[] | null>(null);
    const [memuat, setMemuat] = useState(false);

    const buka = (next: string) => {
        setKode(next);
        setRincian(null);
        setMemuat(true);

        fetch(`/pencatatan-bm/kondisi/${next}`, { headers: { Accept: 'application/json' } })
            .then((r) => (r.ok ? r.json() : Promise.reject(new Error('gagal'))))
            .then((data: Rincian[]) => setRincian(data))
            .catch(() => setRincian([]))
            .finally(() => setMemuat(false));
    };

    useEffect(() => {
        setAngka(ringkasan);
    }, [ringkasan]);

    // Acuan menyegarkan kartu tiap 3 detik; lewati saat tab tidak terlihat.
    useEffect(() => {
        const segarkan = () => {
            if (document.visibilityState === 'hidden') {
                return;
            }

            fetch('/pencatatan-bm/statistik', { headers: { Accept: 'application/json' } })
                .then((r) => (r.ok ? r.json() : Promise.reject(new Error('gagal'))))
                .then((data: { ringkasan: Ringkasan }) => setAngka(data.ringkasan))
                .catch(() => undefined);
        };

        const timer = window.setInterval(segarkan, 3000);

        return () => window.clearInterval(timer);
    }, []);

    const labelAktif = KARTU.find((k) => k.kode === kode)?.label ?? '';

    return (
        <AppLayout>
            <Head title="Dashboard Inventaris" />

            <div className="space-y-5">
                <header>
                    <h1 className="text-xl font-bold text-slate-800">Dashboard Inventaris</h1>
                    <p className="text-sm text-slate-500">Ringkasan kondisi barang milik daerah sekolah.</p>
                </header>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    {KARTU.map((kartu) => {
                        const Ikon = kartu.ikon;
                        const bisaDiklik = kartu.kode !== 'total';

                        return (
                            <button
                                key={kartu.kode}
                                type="button"
                                disabled={!bisaDiklik}
                                onClick={() => bisaDiklik && buka(String(kartu.kode))}
                                className={`rounded-2xl border border-slate-200 bg-white p-4 text-left ${bisaDiklik ? 'hover:border-blue-300' : 'cursor-default'}`}
                            >
                                <div className={`inline-flex rounded-xl p-2 ${kartu.kelas}`}>
                                    <Ikon className="h-5 w-5" />
                                </div>
                                <p className="mt-3 text-xs font-semibold uppercase tracking-wide text-slate-500">{kartu.label}</p>
                                <p className="mt-1 text-2xl font-bold text-slate-800">
                                    {kartu.kode === 'total' ? angka.totalUnit : (angka.kondisi[kartu.kode] ?? 0)}
                                </p>
                            </button>
                        );
                    })}
                </div>
            </div>

            <Modal isOpen={kode !== null} onClose={() => setKode(null)} title={`Rincian Barang - ${labelAktif}`} maxWidth="3xl">
                <p className="text-sm text-slate-500">Daftar barang beserta no register dan lokasi penyalurannya.</p>

                <div className="mt-3 overflow-x-auto">
                    {memuat ? (
                        <p className="py-8 text-center text-sm text-slate-400">Memuat rincian...</p>
                    ) : rincian && rincian.length > 0 ? (
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-500">
                                    <th className="px-3 py-2">No</th>
                                    <th className="px-3 py-2">Nama Barang</th>
                                    <th className="px-3 py-2">Merek/Tipe</th>
                                    <th className="px-3 py-2">No Reg</th>
                                    <th className="px-3 py-2">Ruang/Lokasi (Terakhir)</th>
                                    <th className="px-3 py-2">Penerima (Terakhir)</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rincian.map((item, index) => (
                                    <tr key={`${item.nomor_register}-${index}`} className="border-b border-slate-50 last:border-0">
                                        <td className="px-3 py-2 text-slate-500">{index + 1}</td>
                                        <td className="px-3 py-2 text-slate-700">{item.nama_barang ?? '-'}</td>
                                        <td className="px-3 py-2 text-slate-600">{item.merk_tipe ?? '-'}</td>
                                        <td className="px-3 py-2 font-mono text-xs text-slate-700">{item.nomor_register}</td>
                                        <td className="px-3 py-2 text-slate-600">
                                            {item.ruang ?? <span className="italic text-slate-400">Belum disalurkan</span>}
                                        </td>
                                        <td className="px-3 py-2 text-slate-600">
                                            {item.pegawai ?? <span className="italic text-slate-400">Belum disalurkan</span>}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    ) : (
                        <div className="py-8 text-center">
                            <p className="font-bold text-slate-700">Data Kosong</p>
                            <p className="text-sm text-slate-500">Tidak ada rincian barang untuk kondisi ini.</p>
                        </div>
                    )}
                </div>

                <div className="mt-4 flex justify-end">
                    <button
                        type="button"
                        onClick={() => setKode(null)}
                        className="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600"
                    >
                        Tutup
                    </button>
                </div>
            </Modal>
        </AppLayout>
    );
}
