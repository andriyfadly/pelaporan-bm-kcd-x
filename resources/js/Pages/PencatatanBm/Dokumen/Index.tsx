import { Head, router } from '@inertiajs/react';
import React, { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import EmptyState from '@/Components/EmptyState';
import { Download, FileText, Printer } from 'lucide-react';

interface DokumenItem {
    id: string;
    jenis: string;
    nomor: string | null;
    tanggal: string | null;
    created_at: string;
}

interface RuangRingkas {
    id: string;
    nama_ruang: string;
}

interface Props {
    dokumen: DokumenItem[];
    ruangList: RuangRingkas[];
    pegawaiList: { id: string; nama_lengkap: string }[];
}

const LABEL_JENIS: Record<string, string> = {
    bast: 'Berita Acara Penyaluran',
    fakta_integritas: 'Pakta Integritas',
    pengembalian: 'Berita Acara Pengembalian',
    surat_pengembalian: 'Surat Permohonan Pengembalian',
};

export default function Index({ dokumen, ruangList, pegawaiList }: Props) {
    const [ruangId, setRuangId] = useState(ruangList[0]?.id ?? '');
    const [tahun, setTahun] = useState(() => new Date().getFullYear());
    const [cariSensus, setCariSensus] = useState('');
    const [tanggalKir, setTanggalKir] = useState(() => new Date().toISOString().slice(0, 10));
    const [penandaTangan, setPenandaTangan] = useState<Record<'show_ks' | 'show_wks' | 'show_pb', boolean>>({
        show_ks: true,
        show_wks: true,
        show_pb: true,
    });
    const [showPj, setShowPj] = useState(false);
    const [penanggungJawab, setPenanggungJawab] = useState('');
    const [galatKir, setGalatKir] = useState<string | null>(null);
    const [tanggalBuku, setTanggalBuku] = useState(() => new Date().toISOString().slice(0, 10));
    const [semester, setSemester] = useState('');

    const jumlahPenandaTangan = (['show_ks', 'show_wks', 'show_pb'] as const).filter((k) => penandaTangan[k]).length
        + (showPj ? 1 : 0);

    const togglePenandaTangan = (kunci: 'show_ks' | 'show_wks' | 'show_pb', nilai: boolean) => {
        setGalatKir(null);
        const berikut = { ...penandaTangan, [kunci]: nilai };
        const total = (['show_ks', 'show_wks', 'show_pb'] as const).filter((k) => berikut[k]).length + (showPj ? 1 : 0);

        if (total > 3) {
            setGalatKir('Anda hanya dapat memilih maksimal 3 penanda tangan untuk laporan ini.');
            return;
        }

        setPenandaTangan(berikut);
    };

    const togglePj = (nilai: boolean) => {
        setGalatKir(null);
        if (nilai && jumlahPenandaTangan >= 3) {
            setGalatKir('Anda hanya dapat memilih maksimal 3 penanda tangan untuk laporan ini.');
            return;
        }
        setShowPj(nilai);
        if (!nilai) {
            setPenanggungJawab('');
        }
    };

    const bukaKir = () => {
        if (!ruangId) {
            setGalatKir('Silakan pilih lokasi ruangan terlebih dahulu.');
            return;
        }

        if (jumlahPenandaTangan < 2) {
            setGalatKir('Pilih minimal 2 penanda tangan untuk laporan ini.');
            return;
        }

        setGalatKir(null);
        const params = new URLSearchParams({ ruang: ruangId, tanggal: tanggalKir });
        (['show_ks', 'show_wks', 'show_pb'] as const).forEach((k) => {
            if (penandaTangan[k]) {
                params.set(k, '1');
            }
        });
        if (showPj) {
            params.set('show_pj', '1');
            if (penanggungJawab) {
                params.set('penanggung_jawab', penanggungJawab);
            }
        }

        window.open(`/pencatatan-bm/dokumen/kir?${params.toString()}`, '_blank');
    };

    return (
        <AppLayout>
            <Head title="Dokumen Inventaris" />

            <div className="space-y-5">
                <header>
                    <h1 className="text-xl font-bold text-slate-800">Dokumen Inventaris</h1>
                    <p className="text-sm text-slate-500">
                        Dokumen tersimpan membawa snapshot saat dibuat, jadi cetak ulang tidak berubah setelah koreksi data.
                    </p>
                </header>

                <section className="rounded-2xl border border-slate-200 bg-white p-5">
                    <h2 className="mb-1 flex items-center gap-1.5 font-bold text-slate-800">
                        <Printer className="w-4 h-4 text-slate-500" /> Cetak Langsung
                    </h2>
                    <p className="mb-3 text-xs text-slate-500">
                        Cetak langsung memakai data dan pejabat yang aktif saat ini, dan tidak tersimpan sebagai dokumen.
                    </p>

                    <div className="space-y-4">
                        <div className="rounded-xl border border-slate-100 p-3">
                            <p className="text-xs font-bold uppercase tracking-wide text-slate-500">Kartu Inventaris Ruangan</p>
                            <div className="mt-2 flex flex-wrap items-end gap-3">
                                <label className="block w-52 text-xs font-semibold text-slate-600">
                                    Ruang
                                    <select
                                        value={ruangId}
                                        onChange={(e) => setRuangId(e.target.value)}
                                        className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                                    >
                                        {ruangList.length === 0 && <option value="">Belum ada ruang</option>}
                                        {ruangList.map((ruang) => (
                                            <option key={ruang.id} value={ruang.id}>
                                                {ruang.nama_ruang}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <label className="block w-40 text-xs font-semibold text-slate-600">
                                    Tanggal Cetak
                                    <input
                                        type="date"
                                        value={tanggalKir}
                                        onChange={(e) => setTanggalKir(e.target.value)}
                                        className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                                    />
                                </label>
                                <div className="flex flex-wrap items-center gap-3 text-xs font-semibold text-slate-600">
                                    <label className="flex items-center gap-1.5">
                                        <input
                                            type="checkbox"
                                            checked={penandaTangan.show_ks}
                                            onChange={(e) => togglePenandaTangan('show_ks', e.target.checked)}
                                        />
                                        Kepala Sekolah
                                    </label>
                                    <label className="flex items-center gap-1.5">
                                        <input
                                            type="checkbox"
                                            checked={penandaTangan.show_wks}
                                            onChange={(e) => togglePenandaTangan('show_wks', e.target.checked)}
                                        />
                                        Wakasek Sarpras
                                    </label>
                                    <label className="flex items-center gap-1.5">
                                        <input
                                            type="checkbox"
                                            checked={penandaTangan.show_pb}
                                            onChange={(e) => togglePenandaTangan('show_pb', e.target.checked)}
                                        />
                                        Pengurus Barang
                                    </label>
                                    <label className="flex items-center gap-1.5">
                                        <input
                                            type="checkbox"
                                            checked={showPj}
                                            onChange={(e) => togglePj(e.target.checked)}
                                        />
                                        PJ Ruangan (Opsional)
                                    </label>
                                </div>
                                <label className="block w-60 text-xs font-semibold text-slate-600">
                                    Penanggung Jawab
                                    <select
                                        value={penanggungJawab}
                                        onChange={(e) => setPenanggungJawab(e.target.value)}
                                        disabled={!showPj}
                                        className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm disabled:bg-slate-50 disabled:text-slate-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                                    >
                                        <option value="">-- Pilih Penanggung Jawab (Opsional) --</option>
                                        {pegawaiList.map((pegawai) => (
                                            <option key={pegawai.id} value={pegawai.id}>{pegawai.nama_lengkap}</option>
                                        ))}
                                    </select>
                                </label>
                                <button
                                    type="button"
                                    onClick={bukaKir}
                                    className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50"
                                >
                                    <FileText className="w-3.5 h-3.5" /> Kartu Inventaris Ruangan
                                </button>
                            </div>
                            {galatKir && (
                                <p className="mt-2 rounded-lg bg-red-50 px-3 py-2 text-xs font-semibold text-red-700" role="alert">
                                    {galatKir}
                                </p>
                            )}
                        </div>

                        <div className="rounded-xl border border-slate-100 p-3">
                            <p className="text-xs font-bold uppercase tracking-wide text-slate-500">Buku Inventaris</p>
                            <div className="mt-2 flex flex-wrap items-end gap-3">
                                <label className="block w-28 text-xs font-semibold text-slate-600">
                                    Tahun
                                    <input
                                        type="number"
                                        value={tahun}
                                        onChange={(e) => setTahun(Number(e.target.value))}
                                        placeholder="Semua"
                                        className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                                    />
                                </label>
                                <label className="block w-40 text-xs font-semibold text-slate-600">
                                    Tanggal Laporan
                                    <input
                                        type="date"
                                        value={tanggalBuku}
                                        onChange={(e) => setTanggalBuku(e.target.value)}
                                        className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                                    />
                                </label>
                                <label className="block w-64 text-xs font-semibold text-slate-600">
                                    Semester
                                    <select
                                        value={semester}
                                        onChange={(e) => setSemester(e.target.value)}
                                        className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                                    >
                                        <option value="">-- Semua Semester (Jan - Des) --</option>
                                        <option value="1">Semester 1 (Januari - Juni)</option>
                                        <option value="2">Semester 2 (Juli - Desember)</option>
                                    </select>
                                </label>
                                <a
                                    href={`/pencatatan-bm/dokumen/buku-inventaris?${new URLSearchParams({
                                        tahun: String(tahun),
                                        tanggal: tanggalBuku,
                                        ...(semester ? { semester } : {}),
                                    }).toString()}`}
                                    className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 px-3 min-h-10 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                                >
                                    <FileText className="w-3.5 h-3.5" /> Buku Inventaris
                                </a>
                            </div>
                        </div>

                        <div className="flex flex-wrap items-end gap-3 rounded-xl border border-slate-100 p-3">
                            <p className="mr-auto text-xs font-bold uppercase tracking-wide text-slate-500">Sensus Aset</p>
                            <label className="block w-52 text-xs font-semibold text-slate-600">
                                Cari penerima / barang
                                <input
                                    value={cariSensus}
                                    onChange={(e) => setCariSensus(e.target.value)}
                                    placeholder="Cari penerima / barang / no reg..."
                                    className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                                />
                            </label>
                            <a
                                href={`/pencatatan-bm/dokumen/sensus?search=${encodeURIComponent(cariSensus)}`}
                                className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 px-3 min-h-10 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                            >
                                <FileText className="w-3.5 h-3.5" /> Sensus Aset
                            </a>
                            <a
                                href="/pencatatan-bm/label"
                                className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 px-3 min-h-10 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50"
                            >
                                <Printer className="w-3.5 h-3.5" /> Labeling
                            </a>
                        </div>
                    </div>
                </section>

                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    {dokumen.length === 0 ? (
                        <EmptyState
                            title="Belum ada dokumen"
                            description="Buat BAST atau fakta integritas dari halaman detail unit."
                            icon={<FileText className="w-12 h-12 text-slate-300" />}
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[640px] text-sm">
                                <thead>
                                    <tr className="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-500">
                                        <th className="px-4 py-3">Jenis</th>
                                        <th className="px-4 py-3">Nomor</th>
                                        <th className="px-4 py-3">Tanggal</th>
                                        <th className="px-4 py-3 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {dokumen.map((item) => (
                                        <tr key={item.id} className="border-b border-slate-50 last:border-0">
                                            <td className="px-4 py-3 font-semibold text-slate-700">
                                                {LABEL_JENIS[item.jenis] ?? item.jenis}
                                            </td>
                                            <td className="whitespace-nowrap px-4 py-3 text-slate-600">{item.nomor ?? '-'}</td>
                                            <td className="whitespace-nowrap px-4 py-3 text-slate-600">{item.tanggal?.slice(0, 10) ?? item.created_at}</td>
                                            <td className="px-4 py-3 text-right">
                                                <button
                                                    type="button"
                                                    onClick={() => router.get(`/pencatatan-bm/dokumen/${item.id}/pdf`)}
                                                    className="inline-flex items-center gap-1 rounded-lg min-h-9 px-3 py-1.5 text-xs font-bold text-blue-600 hover:bg-blue-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                                                >
                                                    <Download className="w-3.5 h-3.5" /> Unduh
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>

        </AppLayout>
    );
}
