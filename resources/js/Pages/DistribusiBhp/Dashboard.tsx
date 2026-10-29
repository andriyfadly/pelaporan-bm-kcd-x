import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import CardStat from '@/Components/CardStat';
import { Boxes, ClipboardList, PackageCheck, ArrowRight } from 'lucide-react';

interface StatDana {
    totalBarang: number;
    pengajuanSubmitted: number;
    pengajuanApproved: number;
    totalPenerimaan: number;
}

type PerDana = Record<'BOS' | 'BOPD', StatDana>;

const DANA: Array<'BOS' | 'BOPD'> = ['BOS', 'BOPD'];

export default function Dashboard({ perDana }: { perDana: PerDana }) {
    return (
        <AppLayout title="Dashboard BHP">
            <Head title="Distribusi BHP | Dashboard" />

            <div className="space-y-6">
                <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                    <h1 className="text-lg font-bold text-slate-900">Distribusi BHP</h1>
                    <p className="text-sm text-slate-500 mt-1">
                        Barang habis pakai, dipisah per sumber dana. Stok, dokumen, dan laporan BOS tidak
                        pernah bercampur dengan BOPD.
                    </p>
                </div>

                {DANA.map((dana) => {
                    const s = perDana[dana];
                    const prefix = `/distribusi-bhp/${dana.toLowerCase()}`;

                    return (
                        <section key={dana} className="space-y-3">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <h2 className="text-sm font-bold uppercase tracking-wider text-slate-500">
                                    Sumber Dana {dana}
                                </h2>
                                <div className="flex flex-wrap gap-2">
                                    <Link
                                        href={`${prefix}/pengajuan`}
                                        className="inline-flex min-h-10 items-center gap-1.5 px-3 rounded-xl text-xs font-bold border border-slate-300 text-slate-700 hover:bg-slate-50"
                                    >
                                        Pengajuan <ArrowRight className="w-3.5 h-3.5" />
                                    </Link>
                                    <Link
                                        href={`${prefix}/penerimaan`}
                                        className="inline-flex min-h-10 items-center gap-1.5 px-3 rounded-xl text-xs font-bold border border-slate-300 text-slate-700 hover:bg-slate-50"
                                    >
                                        Penerimaan <ArrowRight className="w-3.5 h-3.5" />
                                    </Link>
                                    <Link
                                        href={`${prefix}/laporan`}
                                        className="inline-flex min-h-10 items-center gap-1.5 px-3 rounded-xl text-xs font-bold bg-blue-600 text-white hover:bg-blue-700"
                                    >
                                        Laporan <ArrowRight className="w-3.5 h-3.5" />
                                    </Link>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                <CardStat label="Barang" value={s.totalBarang} icon={<Boxes className="w-5 h-5" />} color="blue" />
                                <CardStat label="Menunggu Review" value={s.pengajuanSubmitted} icon={<ClipboardList className="w-5 h-5" />} color="rose" />
                                <CardStat label="Disetujui" value={s.pengajuanApproved} icon={<PackageCheck className="w-5 h-5" />} color="emerald" />
                                <CardStat label="Penerimaan" value={s.totalPenerimaan} icon={<PackageCheck className="w-5 h-5" />} color="purple" />
                            </div>
                        </section>
                    );
                })}
            </div>
        </AppLayout>
    );
}
