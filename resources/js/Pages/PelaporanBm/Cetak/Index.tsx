import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';
import { AlertCircle, CheckCircle2, Download, FileSpreadsheet, Loader2 } from 'lucide-react';
import React, { useState } from 'react';

interface Props {
    years?: number[];
    defaultBulan?: number;
    defaultTahun?: number;
}

export default function Index({
    years = [new Date().getFullYear()],
    defaultBulan = new Date().getMonth() + 1,
    defaultTahun = new Date().getFullYear(),
}: Props) {
    const [bulan, setBulan] = useState<number>(defaultBulan);
    const [tahun, setTahun] = useState<number>(defaultTahun);
    const [isLoading, setIsLoading] = useState<boolean>(false);
    const [progress, setProgress] = useState<number>(0);
    const [rowCount, setRowCount] = useState<number>(0);
    const [emptyModal, setEmptyModal] = useState<boolean>(false);

    const bulanNames = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];

    const handleDownload = async () => {
        if (!bulan || !tahun) {
            alert('Silakan pilih Bulan dan Tahun terlebih dahulu!');
            return;
        }

        setIsLoading(true);
        setProgress(15);

        try {
            const token = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content;
            const res = await fetch('/pelaporan-bm/cetak/check', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token || '',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ bulan, tahun }),
            });

            const data = await res.json();
            const total = data.total_rows || 0;
            setRowCount(total);

            if (total === 0) {
                setIsLoading(false);
                setEmptyModal(true);
                return;
            }

            setProgress(60);
            await new Promise((r) => setTimeout(r, 400));
            setProgress(90);

            window.location.href = `/pelaporan-bm/cetak/unduh?bulan=${bulan}&tahun=${tahun}`;

            setTimeout(() => {
                setProgress(100);
                setTimeout(() => {
                    setIsLoading(false);
                    setProgress(0);
                }, 800);
            }, 600);
        } catch (err) {
            console.error(err);
            setIsLoading(false);
            window.location.href = `/pelaporan-bm/cetak/unduh?bulan=${bulan}&tahun=${tahun}`;
        }
    };

    return (
        <AppLayout>
            <Head title="Cetak Rekap Belanja Modal" />

            {/* Modal Overlay Progress Sesuai Legacy */}
            {isLoading && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/80 backdrop-blur-sm transition-all p-4">
                    <div className="bg-white rounded-2xl p-8 max-w-sm w-full text-center shadow-2xl border border-slate-100 animate-in fade-in zoom-in duration-200">
                        <div className="relative w-28 h-28 mx-auto mb-6 flex items-center justify-center">
                            <svg className="w-28 h-28 -rotate-90">
                                <circle
                                    cx="56"
                                    cy="56"
                                    r="45"
                                    className="stroke-slate-100"
                                    strokeWidth="8"
                                    fill="none"
                                />
                                <circle
                                    cx="56"
                                    cy="56"
                                    r="45"
                                    className="stroke-[#107c41] transition-all duration-300"
                                    strokeWidth="8"
                                    strokeDasharray={283}
                                    strokeDashoffset={283 - (283 * progress) / 100}
                                    strokeLinecap="round"
                                    fill="none"
                                />
                            </svg>
                            <div className="absolute inset-0 flex flex-col items-center justify-center text-center">
                                <span className="text-xl font-black text-slate-800">{progress}%</span>
                                <span className="text-[11px] font-semibold text-slate-500">
                                    {rowCount > 0 ? `${rowCount} Baris` : 'Memproses'}
                                </span>
                            </div>
                        </div>
                        <h4 className="text-base font-bold text-slate-900 mb-1">Sedang Memproses Data...</h4>
                        <p className="text-xs text-slate-500 leading-relaxed">
                            Server sedang membaca basis data dan menyusun baris laporan belanja modal ke format berkas Excel.
                        </p>
                    </div>
                </div>
            )}

            {/* Modal Notifikasi Data Kosong Sesuai Legacy */}
            {emptyModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/80 backdrop-blur-sm p-4">
                    <div className="bg-white rounded-2xl p-6 max-w-md w-full text-center shadow-2xl border border-rose-100 animate-in fade-in zoom-in duration-200">
                        <div className="w-12 h-12 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center mx-auto mb-4">
                            <AlertCircle className="w-6 h-6" />
                        </div>
                        <h3 className="text-base font-bold text-rose-600 uppercase tracking-wide mb-2">
                            Data Tidak Ditemukan
                        </h3>
                        <p className="text-sm text-slate-600 leading-relaxed mb-6">
                            Maaf, pada periode bulan <strong>{bulanNames[bulan - 1]} {tahun}</strong> tidak ditemukan adanya catatan data realisasi belanja modal.
                        </p>
                        <button
                            type="button"
                            onClick={() => setEmptyModal(false)}
                            className="w-full py-2.5 bg-slate-200 hover:bg-slate-300 text-slate-700 text-xs font-bold rounded-xl transition"
                        >
                            Tutup Notifikasi
                        </button>
                    </div>
                </div>
            )}

            <div className="p-4 sm:p-6 lg:p-8 max-w-5xl mx-auto space-y-6">
                {/* Excel Theme Card */}
                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    {/* Header Dark Gradient with Excel Green Accent */}
                    <div className="bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 text-white px-6 py-5 border-b-[3px] border-[#107c41] flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <div className="w-9 h-9 rounded-lg bg-[#107c41] flex items-center justify-center shadow">
                                <FileSpreadsheet className="w-5 h-5 text-white" />
                            </div>
                            <div>
                                <h1 className="text-base font-bold uppercase tracking-wider text-white">
                                    Ekspor Laporan Belanja Modal
                                </h1>
                                <p className="text-xs text-slate-400">
                                    Unduh rekapitulasi data belanja modal seluruh satuan pendidikan se-wilayah KCD X
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Form Controls */}
                    <div className="p-6 sm:p-8 space-y-6">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label className="block text-xs font-bold uppercase text-slate-600 mb-2">
                                    Pilih Bulan Realisasi <span className="text-rose-500">*</span>
                                </label>
                                <select
                                    value={bulan}
                                    onChange={(e) => setBulan(Number(e.target.value))}
                                    className="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#107c41] focus:bg-white transition"
                                >
                                    {bulanNames.map((name, idx) => (
                                        <option key={idx + 1} value={idx + 1}>
                                            {name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="block text-xs font-bold uppercase text-slate-600 mb-2">
                                    Pilih Tahun Anggaran <span className="text-rose-500">*</span>
                                </label>
                                <select
                                    value={tahun}
                                    onChange={(e) => setTahun(Number(e.target.value))}
                                    className="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#107c41] focus:bg-white transition"
                                >
                                    {years.map((y) => (
                                        <option key={y} value={y}>
                                            {y}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        {/* Action Footer */}
                        <div className="pt-6 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-4">
                            <span className="text-xs text-slate-500">
                                Berkas akan diunduh dalam format Excel (.xlsx) dengan 26 kolom standar KCD.
                            </span>
                            <button
                                type="button"
                                onClick={handleDownload}
                                disabled={isLoading}
                                className="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 bg-[#107c41] hover:bg-[#0f6f3a] text-white rounded-xl text-sm font-bold shadow-sm hover:shadow transition cursor-pointer disabled:opacity-50"
                            >
                                {isLoading ? (
                                    <Loader2 className="w-4 h-4 animate-spin" />
                                ) : (
                                    <Download className="w-4 h-4" />
                                )}
                                <span>Unduh Berkas Rekap</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
