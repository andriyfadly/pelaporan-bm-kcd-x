import { Head } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import CardStat from '@/Components/CardStat';
import EmptyState from '@/Components/EmptyState';
import { formatRupiah } from '@/Utils/format';
import { Boxes, Building2, Coins, RefreshCw, Users, Wrench } from 'lucide-react';

interface Ringkasan {
    totalUnit: number;
    totalNilai: number;
    kondisi: Record<string, number>;
    jenisBarang: number;
    ruang: number;
    pegawai: number;
}

interface BarisKodeBarang {
    kode_barang: string;
    nama_barang: string | null;
    jumlah: number;
    nilai: number;
}

interface Sinkronisasi {
    status: string;
    dibuat: number;
    surplus: number;
    perlu_review: number;
    created_at: string;
}

interface Props {
    ringkasan: Ringkasan;
    perKodeBarang: BarisKodeBarang[];
    sinkronisasiTerakhir: Sinkronisasi | null;
}

const LABEL_KONDISI: Record<string, string> = {
    B: 'Baik',
    RR: 'Rusak Ringan',
    RB: 'Rusak Berat',
};

export default function Dashboard({ ringkasan, perKodeBarang, sinkronisasiTerakhir }: Props) {
    return (
        <AppLayout>
            <Head title="Pencatatan BM" />

            <div className="space-y-6">
                <header>
                    <h1 className="text-xl font-bold text-slate-800">Pencatatan BM</h1>
                    <p className="text-sm text-slate-500">
                        Inventaris aset berasal dari realisasi yang laporannya sudah disetujui, tambah manual, atau impor.
                    </p>
                </header>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <CardStat label="Total Unit" value={ringkasan.totalUnit} icon={<Boxes className="w-5 h-5" />} />
                    <CardStat
                        label="Nilai Perolehan"
                        value={formatRupiah(ringkasan.totalNilai)}
                        icon={<Coins className="w-5 h-5" />}
                        color="emerald"
                    />
                    <CardStat label="Jenis Barang" value={ringkasan.jenisBarang} icon={<Wrench className="w-5 h-5" />} color="purple" />
                    <CardStat
                        label="Ruang / Pegawai"
                        value={`${ringkasan.ruang} / ${ringkasan.pegawai}`}
                        icon={<Building2 className="w-5 h-5" />}
                        color="amber"
                    />
                </div>

                <section className="rounded-2xl border border-slate-200 bg-white">
                    <div className="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                        <h2 className="font-bold text-slate-800">Kondisi Unit</h2>
                    </div>
                    <div className="grid grid-cols-1 divide-y divide-slate-100 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                        {Object.entries(ringkasan.kondisi).map(([kode, jumlah]) => (
                            <div key={kode} className="px-5 py-4">
                                <p className="text-xs font-semibold text-slate-500">{LABEL_KONDISI[kode] ?? kode}</p>
                                <p className="mt-1 text-lg font-bold text-slate-800">{jumlah}</p>
                            </div>
                        ))}
                    </div>
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white">
                    <div className="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                        <h2 className="font-bold text-slate-800">Unit per Kode Barang</h2>
                        <span className="text-xs text-slate-500">10 terbesar</span>
                    </div>
                    {perKodeBarang.length === 0 ? (
                        <EmptyState
                            title="Belum ada unit inventaris"
                            description="Unit muncul dari realisasi yang disetujui, tambah manual, atau impor Excel/CSV."
                            icon={<Boxes className="w-12 h-12 text-slate-300" />}
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[720px] text-sm">
                                <thead>
                                    <tr className="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-500">
                                        <th className="px-5 py-3">Kode Barang</th>
                                        <th className="px-5 py-3">Nama Barang</th>
                                        <th className="px-5 py-3 text-right">Unit</th>
                                        <th className="px-5 py-3 text-right">Nilai</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {perKodeBarang.map((baris) => (
                                        <tr key={baris.kode_barang} className="border-b border-slate-50 last:border-0">
                                            <td className="whitespace-nowrap px-5 py-3 font-mono text-xs text-slate-600">{baris.kode_barang}</td>
                                            <td className="px-5 py-3 text-slate-700">{baris.nama_barang ?? '-'}</td>
                                            <td className="px-5 py-3 text-right font-semibold text-slate-800">{baris.jumlah}</td>
                                            <td className="whitespace-nowrap px-5 py-3 text-right text-slate-600">{formatRupiah(baris.nilai)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                {sinkronisasiTerakhir && (
                    <p className="flex items-center gap-2 text-xs text-slate-500">
                        <RefreshCw className="w-3.5 h-3.5" />
                        Sinkronisasi terakhir: {sinkronisasiTerakhir.status} · {sinkronisasiTerakhir.dibuat} dibuat ·{' '}
                        {sinkronisasiTerakhir.surplus} surplus · {sinkronisasiTerakhir.perlu_review} perlu review
                    </p>
                )}
            </div>
        </AppLayout>
    );
}
