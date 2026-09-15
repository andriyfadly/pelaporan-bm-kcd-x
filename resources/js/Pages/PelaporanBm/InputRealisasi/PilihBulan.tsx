import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import { ArrowRight, Calendar } from 'lucide-react';
import React, { useState } from 'react';

interface Props {
    bulanAwal?: number;
}

export default function PilihBulan({ bulanAwal }: Props) {
    const defaultBulan = bulanAwal || new Date().getMonth() + 1;
    const [selectedBulan, setSelectedBulan] = useState(defaultBulan);

    const bulanList = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.visit(`/pelaporan-bm/input-realisasi?bulan_realisasi=${selectedBulan}`);
    };

    return (
        <AppLayout title="Input Realisasi">
            <Head title="Periode Anggaran | Input Realisasi" />

            <div className="flex items-center justify-center min-h-[calc(100vh-220px)] p-4">
                <div className="w-full max-w-md">
                    <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                        <div className="flex items-center gap-2 text-slate-900 font-bold text-base mb-1">
                            <Calendar className="w-5 h-5 text-blue-600" />
                            <span>Periode Anggaran</span>
                        </div>
                        <p className="text-slate-500 text-xs leading-relaxed mb-6">
                            Silakan tentukan bulan perolehan realisasi belanja modal sekolah Anda.
                        </p>

                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div>
                                <label className="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">
                                    Pilih Bulan
                                </label>
                                <select
                                    value={selectedBulan}
                                    onChange={(e) => setSelectedBulan(Number(e.target.value))}
                                    className="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white"
                                    required
                                >
                                    {bulanList.map((nama, idx) => (
                                        <option key={idx + 1} value={idx + 1}>
                                            {nama}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <button
                                type="submit"
                                className="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-bold shadow-sm transition cursor-pointer"
                            >
                                <span>Buka Acuan</span>
                                <ArrowRight className="w-4 h-4" />
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
