import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Boxes, ClipboardList, PackageCheck, Wallet } from 'lucide-react';

interface Stats {
    totalBarangBos: number;
    totalBarangBopd: number;
    pengajuanDraft: number;
    pengajuanSubmitted: number;
    pengajuanApproved: number;
    totalPenerimaan: number;
}

export default function Dashboard({ stats }: { stats: Stats }) {
    const cards = [
        { label: 'Barang BOS', value: stats.totalBarangBos, icon: Boxes, color: 'text-blue-600 bg-blue-50' },
        { label: 'Barang BOPD', value: stats.totalBarangBopd, icon: Wallet, color: 'text-indigo-600 bg-indigo-50' },
        { label: 'Pengajuan Draft', value: stats.pengajuanDraft, icon: ClipboardList, color: 'text-amber-600 bg-amber-50' },
        { label: 'Menunggu Review', value: stats.pengajuanSubmitted, icon: ClipboardList, color: 'text-orange-600 bg-orange-50' },
        { label: 'Pengajuan Disetujui', value: stats.pengajuanApproved, icon: PackageCheck, color: 'text-emerald-600 bg-emerald-50' },
        { label: 'Total Penerimaan', value: stats.totalPenerimaan, icon: PackageCheck, color: 'text-slate-600 bg-slate-100' },
    ];

    return (
        <AppLayout title="Dashboard BHP">
            <Head title="Distribusi BHP | Dashboard" />

            <div className="space-y-6">
                <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                    <h1 className="text-lg font-bold text-slate-900">Distribusi BHP</h1>
                    <p className="text-sm text-slate-500 mt-1">
                        Domain barang habis pakai. Terpisah dari Pelaporan Belanja Modal.
                    </p>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    {cards.map((card) => (
                        <div key={card.label} className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm flex items-center justify-between">
                            <div>
                                <div className="text-xs font-bold uppercase text-slate-500 tracking-wider">{card.label}</div>
                                <div className="text-3xl font-black text-slate-900 mt-2">{card.value}</div>
                            </div>
                            <div className={`w-11 h-11 rounded-xl flex items-center justify-center ${card.color}`}>
                                <card.icon className="w-5 h-5" />
                            </div>
                        </div>
                    ))}
                </div>

                <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                    <Link
                        href="/pelaporan-bm/spj/pilih-bulan"
                        className="text-sm font-semibold text-blue-600 hover:text-blue-700"
                    >
                        Buka Pelaporan Belanja Modal →
                    </Link>
                </div>
            </div>
        </AppLayout>
    );
}
