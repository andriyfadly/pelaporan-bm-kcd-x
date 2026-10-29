import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import EmptyState from '@/Components/EmptyState';
import Modal from '@/Components/Modal';
import Pagination from '@/Components/Pagination';
import { useState } from 'react';
import { formatTanggalTabel } from '@/Utils/format';

interface Detail {
    id: string;
    jumlah_diminta: number;
    jumlah_direservasi: number;
    barang?: { nama_barang: string; satuan: string | null } | null;
}

interface PengajuanItem {
    id: string;
    tanggal: string | null;
    sumber_dana: string;
    pemohon?: { name: string | null } | null;
    details: Detail[];
}

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
}

interface Props {
    items: Paginated<PengajuanItem>;
    sumberDana: string;
    pejabatWarning: string | null;
}

export default function Index({ items, sumberDana, pejabatWarning }: Props) {
    const [active, setActive] = useState<PengajuanItem | null>(null);
    const [qty, setQty] = useState<Record<string, number>>({});
    const [alasan, setAlasan] = useState('');

    // Halaman ini hidup di bawah prefix sumber dana: route flat jatuh ke
    // BOS sehingga antrean BOPD akan menyetujui pengajuan dana yang salah.
    const prefix = `/distribusi-bhp/${sumberDana.toLowerCase()}`;

    const open = (item: PengajuanItem) => {
        setActive(item);
        setAlasan('');
        setQty(Object.fromEntries(item.details.map((d) => [d.id, d.jumlah_direservasi || d.jumlah_diminta])));
    };

    const approve = () => {
        if (!active) return;
        router.post(`${prefix}/review-pengajuan/${active.id}/approve`, { jumlah_disetujui: qty }, { onSuccess: () => setActive(null) });
    };

    const reject = () => {
        if (!active) return;
        router.post(`${prefix}/review-pengajuan/${active.id}/reject`, { rejection_reason: alasan }, { onSuccess: () => setActive(null) });
    };

    return (
        <AppLayout title="Review Pengajuan BHP">
            <Head title="Review Pengajuan BHP" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-lg font-bold text-slate-900">Review Pengajuan BHP — {sumberDana}</h1>
                    <p className="text-sm text-slate-500">Pengajuan yang sudah disubmit dan menunggu keputusan.</p>
                </div>

                {pejabatWarning && (
                    <div className="bg-amber-50 border border-amber-200 text-amber-800 rounded-2xl px-4 py-3 text-sm">
                        {pejabatWarning} — approval akan ditolak sampai data pejabat dilengkapi di menu <strong>Master Data → Penugasan Pegawai</strong>.
                    </div>
                )}

                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    {items.data.length === 0 ? (
                        <EmptyState
                            title="Tidak Ada Antrean Review"
                            description={`Belum ada pengajuan ${sumberDana} yang menunggu keputusan.`}
                        />
                    ) : (
                        <>
                            {/* Mobile: review butuh membaca barang + mengisi jumlah,
                                jadi tabel 5 kolom tidak berguna — pakai kartu. */}
                            <ul className="md:hidden divide-y divide-slate-100">
                                {items.data.map((item) => (
                                    <li key={item.id} className="p-4 space-y-2">
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <div className="font-semibold text-slate-800">
                                                    {item.pemohon?.name ?? '-'}
                                                </div>
                                                <div className="text-xs text-slate-500">
                                                    {formatTanggalTabel(item.tanggal)} · {item.sumber_dana} ·{' '}
                                                    {item.details.length} item
                                                </div>
                                            </div>
                                            <button
                                                onClick={() => open(item)}
                                                className="shrink-0 min-h-10 px-4 bg-blue-600 text-white rounded-xl text-sm font-bold"
                                            >
                                                Review
                                            </button>
                                        </div>
                                    </li>
                                ))}
                            </ul>

                            <div className="hidden md:block overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="bg-slate-50 text-slate-500 uppercase text-xs">
                                        <tr>
                                            <th className="text-left py-3 px-4">Tanggal</th>
                                            <th className="text-left py-3 px-4">Pemohon</th>
                                            <th className="text-left py-3 px-4">Sumber Dana</th>
                                            <th className="text-left py-3 px-4">Item</th>
                                            <th className="text-right py-3 px-4">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {items.data.map((item) => (
                                            <tr key={item.id}>
                                                <td className="py-3 px-4 whitespace-nowrap">{formatTanggalTabel(item.tanggal)}</td>
                                                <td className="py-3 px-4">{item.pemohon?.name ?? '-'}</td>
                                                <td className="py-3 px-4">{item.sumber_dana}</td>
                                                <td className="py-3 px-4">{item.details.length}</td>
                                                <td className="py-3 px-4 text-right">
                                                    <button
                                                        onClick={() => open(item)}
                                                        className="min-h-10 px-4 bg-blue-600 text-white rounded-xl text-sm font-bold"
                                                    >
                                                        Review
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </>
                    )}
                    <div className="border-t border-slate-100 px-4">
                        <Pagination links={items.links} total={items.total} />
                    </div>
                </div>
            </div>

            <Modal
                isOpen={active !== null}
                onClose={() => setActive(null)}
                title="Review Pengajuan Barang"
                maxWidth="2xl"
            >
                {active && (
                    <div className="space-y-4">
                        {active.details.map((d) => (
                            <div
                                key={d.id}
                                className="flex flex-col gap-3 border border-slate-200 rounded-xl p-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4"
                            >
                                <div className="min-w-0">
                                    <div className="font-semibold text-slate-800">{d.barang?.nama_barang}</div>
                                    <div className="text-xs text-slate-500">Diminta: {d.jumlah_diminta} {d.barang?.satuan}</div>
                                </div>
                                <div className="sm:shrink-0">
                                    <label htmlFor={`qty-${d.id}`} className="block text-xs font-semibold text-slate-500 mb-1 sm:text-right">
                                        Disetujui
                                    </label>
                                    <input
                                        id={`qty-${d.id}`}
                                        type="number"
                                        min={0}
                                        max={d.jumlah_diminta}
                                        value={qty[d.id] ?? 0}
                                        onChange={(e) => setQty({ ...qty, [d.id]: Number(e.target.value) })}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm sm:w-28"
                                    />
                                </div>
                            </div>
                        ))}

                        <div>
                            <label htmlFor="alasan-review" className="block text-xs font-bold uppercase text-slate-500 mb-1">
                                Catatan review / alasan penolakan
                            </label>
                            <textarea
                                id="alasan-review"
                                placeholder="Wajib diisi bila menolak"
                                value={alasan}
                                onChange={(e) => setAlasan(e.target.value)}
                                className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"
                                rows={3}
                            />
                        </div>

                        <div className="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
                            <button
                                onClick={() => setActive(null)}
                                className="min-h-10 px-4 border border-slate-300 rounded-xl text-sm font-semibold"
                            >
                                Batal
                            </button>
                            <button
                                onClick={reject}
                                className="min-h-10 px-4 bg-rose-600 text-white rounded-xl text-sm font-semibold"
                            >
                                Tolak
                            </button>
                            <button
                                onClick={approve}
                                className="min-h-10 px-4 bg-emerald-600 text-white rounded-xl text-sm font-semibold"
                            >
                                Setujui Pengajuan
                            </button>
                        </div>
                    </div>
                )}
            </Modal>
        </AppLayout>
    );
}
