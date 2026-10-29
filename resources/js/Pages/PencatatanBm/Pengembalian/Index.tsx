import { Head, router } from '@inertiajs/react';
import React, { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import ConfirmDialog from '@/Components/ConfirmDialog';
import EmptyState from '@/Components/EmptyState';
import { FileText, Lock, PackageCheck, Undo2 } from 'lucide-react';

interface Unit {
    id: string;
    nomor_register: string;
    kode_barang: string;
    nama_barang: string | null;
    punya_bast?: boolean;
    ruang?: { nama_ruang: string } | null;
    pegawai?: { nama_lengkap: string } | null;
}

interface Riwayat {
    id: string;
    inventaris_unit_id: string;
    tanggal: string;
    tujuan_kondisi: string | null;
    keterangan: string | null;
    unit?: { nomor_register: string; nama_barang: string | null } | null;
    asal_ruang?: { nama_ruang: string } | null;
    asal_pegawai?: { nama_lengkap: string } | null;
    user?: { name: string } | null;
}

interface Props {
    dipinjam: Unit[];
    riwayat: Riwayat[];
    kondisiList: Record<string, string>;
}

export default function Index({ dipinjam, riwayat, kondisiList }: Props) {
    const [target, setTarget] = useState<Unit | null>(null);
    const [kondisi, setKondisi] = useState('B');

    const kembalikan = () => {
        if (!target) {
            return;
        }

        router.post(
            `/pencatatan-bm/pengembalian/${target.id}`,
            { kondisi, tanggal: new Date().toISOString().slice(0, 10) },
            {
                preserveScroll: true,
                onSuccess: () => setTarget(null),
            },
        );
    };

    const buatSurat = (item: Riwayat) => {
        router.post(`/pencatatan-bm/dokumen/surat-pengembalian/${item.inventaris_unit_id}`, {
            tanggal: item.tanggal.slice(0, 10),
        });
    };

    // Riwayat sudah urut terbaru dulu; surat membaca pengembalian terbaru per
    // unit, jadi tombolnya hanya untuk baris pertama tiap unit.
    const barisTerbaru = new Set(
        riwayat
            .filter((item, index) => riwayat.findIndex((lain) => lain.inventaris_unit_id === item.inventaris_unit_id) === index)
            .map((item) => item.id),
    );

    return (
        <AppLayout>
            <Head title="Pengembalian Inventaris" />

            <div className="space-y-5">
                <header>
                    <h1 className="text-xl font-bold text-slate-800">Pengembalian</h1>
                    <p className="text-sm text-slate-500">
                        Unit yang sedang disalurkan/dipinjam. Pengembalian menutup penyaluran dan mencatat kondisi akhir.
                    </p>
                </header>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    <div className="flex items-center gap-2 border-b border-slate-100 px-5 py-4">
                        <PackageCheck className="w-4 h-4 text-slate-400" />
                        <h2 className="font-bold text-slate-800">Sedang Disalurkan</h2>
                    </div>

                    {dipinjam.length === 0 ? (
                        <EmptyState
                            title="Tidak ada unit disalurkan"
                            description="Semua unit inventaris sedang berada di gudang atau belum disalurkan."
                        />
                    ) : (
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-400">
                                    <th className="px-4 py-3">Register</th>
                                    <th className="px-4 py-3">Barang</th>
                                    <th className="px-4 py-3">Ruang</th>
                                    <th className="px-4 py-3">Penanggung Jawab</th>
                                    <th className="px-4 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {dipinjam.map((unit) => (
                                    <tr key={unit.id} className="border-b border-slate-50 last:border-0">
                                        <td className="px-4 py-3 font-mono text-xs font-bold text-slate-700">{unit.nomor_register}</td>
                                        <td className="px-4 py-3 text-slate-700">{unit.nama_barang ?? unit.kode_barang}</td>
                                        <td className="px-4 py-3 text-slate-600">{unit.ruang?.nama_ruang ?? '-'}</td>
                                        <td className="px-4 py-3 text-slate-600">{unit.pegawai?.nama_lengkap ?? '-'}</td>
                                        <td className="px-4 py-3 text-right">
                                            {unit.punya_bast ? (
                                                <button
                                                    type="button"
                                                    onClick={() => setTarget(unit)}
                                                    className="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-bold text-amber-700 hover:bg-amber-50"
                                                >
                                                    <Undo2 className="w-3.5 h-3.5" /> Kembalikan
                                                </button>
                                            ) : (
                                                <span
                                                    title="Nomor BAST belum diisi di Rekapan BA"
                                                    className="inline-flex cursor-not-allowed items-center gap-1 rounded-lg px-2 py-1 text-xs font-bold text-slate-400"
                                                >
                                                    <Lock className="w-3.5 h-3.5" /> Isi Nomor BA
                                                </span>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </section>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    <div className="border-b border-slate-100 px-5 py-4">
                        <h2 className="font-bold text-slate-800">Riwayat Pengembalian</h2>
                    </div>
                    {riwayat.length === 0 ? (
                        <EmptyState title="Belum ada pengembalian" description="Riwayat muncul setelah unit dikembalikan." />
                    ) : (
                        <ul className="divide-y divide-slate-50">
                            {riwayat.map((item) => (
                                <li key={item.id} className="px-5 py-3 text-sm">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <span className="font-semibold text-slate-700">
                                            {item.unit?.nomor_register ?? '-'} · {item.unit?.nama_barang ?? '-'}
                                        </span>
                                        <span className="text-xs text-slate-400">{item.tanggal.slice(0, 10)}</span>
                                    </div>
                                    <p className="mt-1 text-xs text-slate-500">
                                        Dari {item.asal_ruang?.nama_ruang ?? '-'} / {item.asal_pegawai?.nama_lengkap ?? '-'} · kondisi akhir{' '}
                                        {kondisiList[item.tujuan_kondisi ?? ''] ?? item.tujuan_kondisi ?? '-'}
                                    </p>
                                    {/* Surat pengembalian selalu membaca pengembalian terbaru unit,
                                        jadi tombolnya hanya untuk baris terbaru per unit. */}
                                    {barisTerbaru.has(item.id) && (
                                        <div className="mt-2">
                                            <button
                                                type="button"
                                                onClick={() => buatSurat(item)}
                                                className="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-bold text-blue-600 hover:bg-blue-50"
                                            >
                                                <FileText className="w-3.5 h-3.5" /> Buat Surat Pengembalian
                                            </button>
                                        </div>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>

            <ConfirmDialog
                isOpen={target !== null}
                onClose={() => setTarget(null)}
                onConfirm={kembalikan}
                isDestructive={false}
                title="Kembalikan Unit"
                message={`Unit ${target?.nomor_register ?? ''} akan ditutup penyalurannya dan kondisi akhir dicatat sebagai ${kondisiList[kondisi]}.`}
                confirmText="Kembalikan"
            />

            {target && (
                <div className="fixed bottom-6 left-1/2 z-40 -translate-x-1/2 rounded-xl border border-slate-200 bg-white px-4 py-2 shadow-lg">
                    <label className="text-xs font-semibold text-slate-600">
                        Kondisi akhir
                        <select
                            value={kondisi}
                            onChange={(e) => setKondisi(e.target.value)}
                            className="ml-2 rounded-lg border border-slate-200 px-2 py-1 text-xs"
                        >
                            {Object.entries(kondisiList).map(([kode, label]) => (
                                <option key={kode} value={kode}>
                                    {label}
                                </option>
                            ))}
                        </select>
                    </label>
                </div>
            )}
        </AppLayout>
    );
}
