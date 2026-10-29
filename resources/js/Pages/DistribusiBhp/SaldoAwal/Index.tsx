import { Head, router, useForm } from '@inertiajs/react';
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

    // Selalu lewat prefix sumber dana: URL flat membuat sidebar berhenti
    // menyorot grup BOS/BOPD setelah periode diganti.
    const prefix = `/distribusi-bhp/${sumberDana.toLowerCase()}`;

    const pindah = (patch: Record<string, string | number>) =>
        router.get(`${prefix}/saldo-awal`, { periode, ...patch }, { preserveState: false });

    const updateRow = (patch: Partial<(typeof form.data.details)[number]>) =>
        form.setData('details', [{ ...form.data.details[0], ...patch }]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(`${prefix}/saldo-awal`, {
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
                        <label htmlFor="periode-saldo" className="sr-only">Periode</label>
                        <select
                            id="periode-saldo"
                            value={periode}
                            onChange={(e) => pindah({ periode: Number(e.target.value) })}
                            className="min-h-10 px-3 border border-slate-300 rounded-xl text-sm"
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

                <form onSubmit={submit} className="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm space-y-4 sm:p-6">
                    <h2 className="text-sm font-bold text-slate-900">Tambah Saldo Awal</h2>
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-4">
                        <div className="sm:col-span-2">
                            <label htmlFor="saldo-barang" className="block text-xs font-semibold text-slate-500 mb-1">Barang</label>
                            <select
                                id="saldo-barang"
                                value={form.data.details[0].barang_id}
                                onChange={(e) => updateRow({ barang_id: e.target.value })}
                                className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm"
                            >
                                <option value="">Pilih barang...</option>
                                {barang.map((b) => (
                                    <option key={b.id} value={b.id}>{b.nama_barang} ({b.satuan})</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label htmlFor="saldo-jumlah" className="block text-xs font-semibold text-slate-500 mb-1">Jumlah</label>
                            <input
                                id="saldo-jumlah"
                                type="number"
                                min={1}
                                value={form.data.details[0].jumlah}
                                onChange={(e) => updateRow({ jumlah: Number(e.target.value) })}
                                className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm"
                            />
                        </div>
                        <div>
                            <label htmlFor="saldo-harga" className="block text-xs font-semibold text-slate-500 mb-1">Harga Satuan</label>
                            <input
                                id="saldo-harga"
                                type="number"
                                min={0}
                                value={form.data.details[0].harga_satuan}
                                onChange={(e) => updateRow({ harga_satuan: Number(e.target.value) })}
                                className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm"
                            />
                        </div>
                    </div>
                    {Object.keys(form.errors).length > 0 && (
                        <ul className="text-xs text-rose-600 list-disc list-inside space-y-1">
                            {Object.values(form.errors).map((pesan, i) => (
                                <li key={i}>{pesan}</li>
                            ))}
                        </ul>
                    )}
                    <button
                        type="submit"
                        disabled={form.processing}
                        className="min-h-11 w-full px-4 bg-blue-600 text-white rounded-xl text-sm font-bold disabled:opacity-50 sm:w-auto"
                    >
                        Simpan
                    </button>
                </form>

                <div className="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm space-y-4 sm:p-6">
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 className="text-sm font-bold text-slate-900">Import Saldo Awal</h2>
                            <p className="text-xs text-slate-500 mt-1">
                                Unggah berkas Excel sesuai template. Satu baris invalid membatalkan seluruh import.
                            </p>
                        </div>
                        <a
                            href={`${prefix}/saldo-awal/template`}
                            className="inline-flex min-h-10 shrink-0 items-center justify-center px-3 text-sm font-semibold text-blue-600 border border-blue-200 rounded-xl"
                        >
                            Unduh Template
                        </a>
                    </div>

                    <input
                        type="file"
                        accept=".xlsx,.xls"
                        aria-label="Berkas Excel saldo awal"
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
                            importForm.post(`${prefix}/saldo-awal/import`, {
                                forceFormData: true,
                                onSuccess: () => {
                                    setFile(null);
                                    importForm.reset();
                                },
                            });
                        }}
                        className="min-h-10 px-4 bg-emerald-600 text-white rounded-xl text-sm font-bold disabled:opacity-50"
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
                    {items.length === 0 ? (
                        <EmptyState title="Belum ada saldo awal" description={`Belum ada saldo awal ${sumberDana} periode ${periode}.`} />
                    ) : (
                        <>
                            <ul className="md:hidden divide-y divide-slate-100">
                                {items.map((item) => (
                                    <li key={item.id} className="p-4 space-y-2">
                                        <div>
                                            <div className="font-semibold text-slate-900 break-words">{item.nama_barang ?? '-'}</div>
                                            <div className="text-xs text-slate-500 font-mono">{item.kode_barang ?? '-'}</div>
                                        </div>
                                        <dl className="grid grid-cols-3 gap-2 text-xs">
                                            <div>
                                                <dt className="text-slate-500">Jumlah</dt>
                                                <dd className="font-semibold text-slate-800">{item.jumlah} {item.satuan ?? ''}</dd>
                                            </div>
                                            <div>
                                                <dt className="text-slate-500">Harga</dt>
                                                <dd className="text-slate-800">{rupiah(item.harga_satuan ?? 0)}</dd>
                                            </div>
                                            <div className="text-right">
                                                <dt className="text-slate-500">Nilai Total</dt>
                                                <dd className="font-semibold text-slate-800">{rupiah(item.nilai_total ?? 0)}</dd>
                                            </div>
                                        </dl>
                                        <button
                                            type="button"
                                            onClick={() => setHapus(item)}
                                            className="min-h-10 w-full rounded-xl text-sm font-semibold text-rose-600 border border-rose-200"
                                        >
                                            Hapus
                                        </button>
                                    </li>
                                ))}
                            </ul>

                            <div className="hidden md:block overflow-x-auto">
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
                                        {items.map((item) => (
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
                                                        className="inline-flex min-h-9 items-center px-3 text-rose-600 border border-rose-200 rounded-xl text-sm font-semibold"
                                                    >
                                                        Hapus
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </>
                    )}
                </div>
            </div>

            <ConfirmDialog
                isOpen={hapus !== null}
                onClose={() => setHapus(null)}
                onConfirm={() => {
                    if (hapus) router.delete(`${prefix}/saldo-awal/${hapus.id}`, { preserveScroll: true });
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
