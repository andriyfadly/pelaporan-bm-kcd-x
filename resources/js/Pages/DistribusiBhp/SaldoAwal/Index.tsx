import { Head, Link, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import CardStat from '@/Components/CardStat';
import ConfirmDialog from '@/Components/ConfirmDialog';
import EmptyState from '@/Components/EmptyState';
import { useState } from 'react';
import { Boxes, Package, Wallet } from 'lucide-react';

interface SaldoAwalItem {
    id: string;
    penerimaan_id: string;
    barang_id: string;
    nama_barang: string | null;
    kode_barang: string | null;
    satuan: string | null;
    jumlah: number;
    harga_satuan: number;
    nilai_total: number;
}

const rupiah = (value: number) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);

export default function Index({
    items,
    stats,
    sumberDana,
    periode,
    availablePeriods,
    barang,
}: {
    items: SaldoAwalItem[];
    stats: { total_barang: number; total_unit: number; total_nilai: number };
    sumberDana: string;
    periode: number;
    availablePeriods: number[];
    barang: { id: string; kode_barang: string | null; nama_barang: string; satuan: string | null }[];
}) {
    const tahunIni = new Date().getFullYear();
    const periods = Array.from(new Set([...availablePeriods, tahunIni, tahunIni + 1])).sort((a, b) => b - a);

    const [hapus, setHapus] = useState<SaldoAwalItem | null>(null);
    const [file, setFile] = useState<File | null>(null);
    const form = useForm({
        sumber_dana: sumberDana,
        periode_tahun: periode,
        details: [{ barang_id: '', jumlah: 1, harga_satuan: 0 }],
    });
    const importForm = useForm<{ file: File | null; sumber_dana: string; periode_tahun: number }>({
        file: null,
        sumber_dana: sumberDana,
        periode_tahun: periode,
    });

    const pindah = (patch: Record<string, string | number>) =>
        router.get('/distribusi-bhp/saldo-awal', { sumber_dana: sumberDana, periode, ...patch }, { preserveState: false });

    const updateRow = (patch: Partial<(typeof form.data.details)[number]>) =>
        form.setData('details', [{ ...form.data.details[0], ...patch }]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/distribusi-bhp/saldo-awal', {
            onSuccess: () => form.reset('details'),
        });
    };

    return (
        <AppLayout title="Saldo Awal BHP">
            <Head title="Saldo Awal BHP" />

            <div className="space-y-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-lg font-bold text-slate-900">Saldo Awal BHP — {sumberDana}</h1>
                        <p className="text-xs text-slate-500 mt-1">Saldo awal memengaruhi stok fisik dan perhitungan tersedia.</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {['BOS', 'BOPD'].map((dana) => (
                            <Link
                                key={dana}
                                href={`/distribusi-bhp/saldo-awal?sumber_dana=${dana}&periode=${periode}`}
                                className={`px-3 py-2 rounded-xl text-sm font-semibold border ${dana === sumberDana ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-700 border-slate-300'}`}
                            >
                                {dana}
                            </Link>
                        ))}
                        <select
                            value={periode}
                            onChange={(e) => pindah({ periode: Number(e.target.value) })}
                            className="px-3 py-2 border border-slate-300 rounded-xl text-sm"
                        >
                            {periods.map((p) => (
                                <option key={p} value={p}>Periode {p}</option>
                            ))}
                        </select>
                    </div>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <CardStat label="Jumlah Barang" value={stats.total_barang} icon={<Package className="w-5 h-5" />} color="blue" />
                    <CardStat label="Total Unit" value={stats.total_unit} icon={<Boxes className="w-5 h-5" />} color="amber" />
                    <CardStat label="Total Nilai Saldo" value={rupiah(stats.total_nilai)} icon={<Wallet className="w-5 h-5" />} color="emerald" />
                </div>

                <form onSubmit={submit} className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-4">
                    <h2 className="text-sm font-bold text-slate-900">Tambah Saldo Awal</h2>
                    <div className="grid grid-cols-1 sm:grid-cols-4 gap-3">
                        <select
                            value={form.data.details[0].barang_id}
                            onChange={(e) => updateRow({ barang_id: e.target.value })}
                            className="px-3 py-2 border border-slate-300 rounded-xl text-sm"
                        >
                            <option value="">Pilih barang...</option>
                            {barang.map((b) => (
                                <option key={b.id} value={b.id}>{b.nama_barang} ({b.satuan})</option>
                            ))}
                        </select>
                        <input
                            type="number"
                            min={1}
                            value={form.data.details[0].jumlah}
                            onChange={(e) => updateRow({ jumlah: Number(e.target.value) })}
                            placeholder="Jumlah"
                            className="px-3 py-2 border border-slate-300 rounded-xl text-sm"
                        />
                        <input
                            type="number"
                            min={0}
                            value={form.data.details[0].harga_satuan}
                            onChange={(e) => updateRow({ harga_satuan: Number(e.target.value) })}
                            placeholder="Harga Satuan"
                            className="px-3 py-2 border border-slate-300 rounded-xl text-sm"
                        />
                        <button
                            type="submit"
                            disabled={form.processing}
                            className="px-4 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-bold disabled:opacity-50"
                        >
                            Simpan
                        </button>
                    </div>
                    {Object.keys(form.errors).length > 0 && (
                        <ul className="text-xs text-rose-600 list-disc list-inside space-y-1">
                            {Object.values(form.errors).map((pesan, i) => (
                                <li key={i}>{pesan}</li>
                            ))}
                        </ul>
                    )}
                </form>

                <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-4">
                    <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 className="text-sm font-bold text-slate-900">Import Saldo Awal</h2>
                            <p className="text-xs text-slate-500 mt-1">
                                Unggah berkas Excel sesuai template. Satu baris invalid membatalkan seluruh import.
                            </p>
                        </div>
                        <a
                            href={`/distribusi-bhp/saldo-awal/template?sumber_dana=${sumberDana}`}
                            className="px-3 py-2 text-sm font-semibold text-blue-600 border border-blue-200 rounded-xl"
                        >
                            Unduh Template
                        </a>
                    </div>

                    <input
                        type="file"
                        accept=".xlsx,.xls"
                        onChange={(e) => setFile(e.target.files?.[0] ?? null)}
                        className="block w-full text-sm text-slate-600 file:mr-3 file:px-3 file:py-2 file:rounded-xl file:border-0 file:bg-slate-100 file:text-sm file:font-semibold"
                    />

                    <button
                        type="button"
                        disabled={!file || importForm.processing}
                        onClick={() => {
                            if (!file) return;
                            importForm.transform(() => ({
                                file,
                                sumber_dana: sumberDana,
                                periode_tahun: periode,
                            }));
                            importForm.post('/distribusi-bhp/saldo-awal/import', {
                                forceFormData: true,
                                onSuccess: () => {
                                    setFile(null);
                                    importForm.reset();
                                },
                            });
                        }}
                        className="px-4 py-2.5 bg-emerald-600 text-white rounded-xl text-sm font-bold disabled:opacity-50"
                    >
                        Import
                    </button>

                    {Object.keys(importForm.errors).length > 0 && (
                        <ul className="text-xs text-rose-600 list-disc list-inside space-y-1">
                            {Object.values(importForm.errors).map((pesan, i) => (
                                <li key={i}>{pesan}</li>
                            ))}
                        </ul>
                    )}
                </div>

                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-slate-50 text-slate-500 uppercase text-xs">
                                <tr>
                                    <th className="text-left py-3 px-4">Kode</th>
                                    <th className="text-left py-3 px-4">Nama Barang</th>
                                    <th className="text-right py-3 px-4">Jumlah</th>
                                    <th className="text-right py-3 px-4">Harga Satuan</th>
                                    <th className="text-right py-3 px-4">Nilai Total</th>
                                    <th className="text-left py-3 px-4">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {items.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="py-6">
                                            <EmptyState title="Belum ada saldo awal" description={`Belum ada saldo awal ${sumberDana} periode ${periode}.`} />
                                        </td>
                                    </tr>
                                ) : (
                                    items.map((item) => (
                                        <tr key={item.id}>
                                            <td className="py-3 px-4 text-slate-500">{item.kode_barang ?? '-'}</td>
                                            <td className="py-3 px-4 font-medium text-slate-900">{item.nama_barang ?? '-'}</td>
                                            <td className="py-3 px-4 text-right">{item.jumlah} {item.satuan ?? ''}</td>
                                            <td className="py-3 px-4 text-right">{rupiah(item.harga_satuan ?? 0)}</td>
                                            <td className="py-3 px-4 text-right font-semibold">{rupiah(item.nilai_total ?? 0)}</td>
                                            <td className="py-3 px-4">
                                                <button
                                                    type="button"
                                                    onClick={() => setHapus(item)}
                                                    className="px-3 py-1.5 text-rose-600 border border-rose-200 rounded-xl text-xs font-semibold"
                                                >
                                                    Hapus
                                                </button>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <ConfirmDialog
                isOpen={hapus !== null}
                onClose={() => setHapus(null)}
                onConfirm={() => {
                    if (hapus) router.delete(`/distribusi-bhp/saldo-awal/${hapus.id}`, { preserveScroll: true });
                }}
                title="Hapus Saldo Awal"
                message={
                    hapus
                        ? `Hapus saldo awal ${hapus.nama_barang ?? ''}? Stok fisik akan berkurang ${hapus.jumlah} ${hapus.satuan ?? ''}. Tindakan ini tidak dapat dibatalkan.`
                        : ''
                }
                confirmText="Hapus"
            />
        </AppLayout>
    );
}
