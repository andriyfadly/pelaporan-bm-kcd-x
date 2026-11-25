import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import EmptyState from '@/Components/EmptyState';
import SearchInput from '@/Components/SearchInput';
import { Printer } from 'lucide-react';

interface UnitLabel {
    id: string;
    kode_barang: string;
    nama_barang: string | null;
    merk_tipe: string | null;
    nomor_register: string;
    tahun_perolehan: number;
    ruang: string | null;
}

interface Props {
    units: UnitLabel[];
    tahunList: number[];
    filter: { search: string; tahun: string };
}

export default function Index({ units, tahunList, filter }: Props) {
    const [terpilih, setTerpilih] = useState<string[]>([]);
    const [mengirim, setMengirim] = useState(false);
    const [galat, setGalat] = useState<string | null>(null);

    const terapkan = (patch: Partial<{ search: string; tahun: string }>) => {
        router.get(
            '/pencatatan-bm/label',
            { ...filter, ...patch } as Record<string, string>,
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const semuaTerpilih = units.length > 0 && units.every((u) => terpilih.includes(u.id));

    const toggle = (id: string) => {
        setTerpilih((sebelum) => (sebelum.includes(id) ? sebelum.filter((x) => x !== id) : [...sebelum, id]));
    };

    const toggleSemua = () => {
        setTerpilih(semuaTerpilih ? [] : units.map((u) => u.id));
    };

    // PDF diunduh lewat fetch: POST Inertia tidak bisa memicu unduhan berkas.
    const cetak = async () => {
        if (terpilih.length === 0 || mengirim) {
            return;
        }

        setMengirim(true);
        setGalat(null);

        try {
            const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
            const res = await fetch('/pencatatan-bm/label-cetak', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    Accept: 'application/pdf',
                },
                body: JSON.stringify({ unit_ids: terpilih }),
            });

            const contentType = res.headers.get('content-type') ?? '';

            if (!res.ok || !contentType.includes('pdf')) {
                setGalat('Gagal mencetak label. Silakan pilih ulang barang dan coba lagi.');
                return;
            }

            const url = URL.createObjectURL(await res.blob());
            const tautan = document.createElement('a');
            tautan.href = url;
            tautan.download = 'label-aset.pdf';
            tautan.click();
            URL.revokeObjectURL(url);
        } catch {
            setGalat('Gagal mencetak label. Periksa koneksi lalu coba lagi.');
        } finally {
            setMengirim(false);
        }
    };

    return (
        <AppLayout>
            <Head title="Labeling Aset" />

            <div className="space-y-5">
                <header>
                    <h1 className="text-xl font-bold text-slate-800">Labeling Aset</h1>
                    <p className="text-sm text-slate-500">Pilih barang yang akan dicetak labelnya (2 label per halaman).</p>
                </header>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    <div className="flex flex-wrap items-center gap-2 border-b border-slate-100 p-4">
                        <h2 className="mr-auto font-bold text-slate-800">Daftar Barang</h2>
                        <SearchInput
                            value={filter.search}
                            onChange={(value) => terapkan({ search: value })}
                            placeholder="Cari nama, merk, kode, atau no reg..."
                            ariaLabel="Cari barang untuk label"
                            className="w-full sm:w-72"
                        />
                        <select
                            value={filter.tahun}
                            onChange={(e) => terapkan({ tahun: e.target.value })}
                            aria-label="Filter tahun perolehan"
                            className="min-h-10 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700"
                        >
                            <option value="">Semua Tahun</option>
                            {tahunList.map((t) => (
                                <option key={t} value={t}>{t}</option>
                            ))}
                        </select>
                    </div>

                    {terpilih.length > 0 && (
                        <div className="flex flex-wrap items-center gap-2 border-b border-blue-100 bg-blue-50 px-4 py-2.5">
                            <span className="mr-auto text-xs font-bold text-blue-800">{terpilih.length} label dipilih</span>
                            <button
                                type="button"
                                onClick={() => setTerpilih([])}
                                className="rounded-lg border border-blue-200 px-3 py-1.5 text-xs font-bold text-blue-700"
                            >
                                Batal
                            </button>
                        </div>
                    )}

                    {units.length === 0 ? (
                        <EmptyState title="Tidak ada barang untuk dicetak." description="Ubah kata kunci atau tambahkan barang lewat Inventaris." />
                    ) : (
                        <div className="divide-y divide-slate-50">
                            <div className="flex items-center gap-3 bg-slate-50/60 px-4 py-2">
                                <input
                                    type="checkbox"
                                    checked={semuaTerpilih}
                                    onChange={toggleSemua}
                                    aria-label="Pilih Semua yang Tampil"
                                />
                                <span className="text-xs font-bold text-slate-600">Pilih Semua yang Tampil</span>
                            </div>

                            {units.map((unit) => (
                                <label
                                    key={unit.id}
                                    className="flex cursor-pointer items-center gap-3 px-4 py-2.5 hover:bg-slate-50"
                                >
                                    <input
                                        type="checkbox"
                                        checked={terpilih.includes(unit.id)}
                                        onChange={() => toggle(unit.id)}
                                        aria-label={`Pilih ${unit.nama_barang ?? unit.kode_barang} reg ${unit.nomor_register}`}
                                    />
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-semibold text-slate-700">
                                            {unit.nama_barang ?? '-'}
                                            {unit.merk_tipe ? <span className="font-normal text-slate-500"> · {unit.merk_tipe}</span> : null}
                                        </p>
                                        <p className="text-xs text-slate-500">
                                            Reg {unit.nomor_register} · {unit.tahun_perolehan}
                                            <span className="font-mono"> · {unit.kode_barang}</span>
                                            {unit.ruang ? ` · ${unit.ruang}` : ''}
                                        </p>
                                    </div>
                                </label>
                            ))}
                        </div>
                    )}
                </section>

                <div className="flex flex-wrap items-center justify-end gap-2">
                    {galat && (
                        <p className="mr-auto rounded-lg bg-red-50 px-3 py-2 text-xs font-semibold text-red-700" role="alert">
                            {galat}
                        </p>
                    )}
                    <button
                        type="button"
                        onClick={cetak}
                        disabled={terpilih.length === 0 || mengirim}
                        className="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50"
                    >
                        <Printer className="h-4 w-4" /> {mengirim ? 'Menyiapkan...' : `Cetak Label Terpilih (${terpilih.length})`}
                    </button>
                </div>
            </div>
        </AppLayout>
    );
}
