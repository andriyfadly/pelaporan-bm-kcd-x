import { Head, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Modal from '@/Components/Modal';
import EmptyState from '@/Components/EmptyState';
import { bukaPdfDokumen } from '@/Utils/dokumen';
import { Download, FileText, Pencil, RotateCcw, Save } from 'lucide-react';

interface Baris {
    unit_ids: string[];
    kode_barang: string;
    nama_barang: string | null;
    merk_tipe: string | null;
    tanggal_perolehan: string | null;
    bulan_perolehan: number | null;
    tahun_perolehan: number;
    volume: number;
    volume_pengadaan: number;
    satuan: string | null;
    harga_satuan: number;
    nomor_register: string;
    ruang_id: string | null;
    pegawai_id: string | null;
    keterangan: string | null;
    sudah_disalurkan: boolean;
    sisa: number;
    sisa_unit_ids: string[];
}

interface Opsi {
    id: string;
    nama_ruang?: string;
    nama_lengkap?: string;
}

interface Props {
    baris: Baris[];
    ruangList: Opsi[];
    pegawaiList: Opsi[];
    bulanList: { value: number; label: string }[];
    tahunList: number[];
    filter: { search: string; bulan: string; tahun: string };
    asalList: string[];
    kondisiList: Record<string, string>;
    nomorBastBerikutnya: string | null;
}

const rupiah = (nilai: number) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(nilai);

export default function Index({ baris, ruangList, pegawaiList, bulanList, tahunList, filter, asalList, nomorBastBerikutnya }: Props) {
    const [draft, setDraft] = useState<Record<number, { ruang_id: string; pegawai_id: string; keterangan: string }>>({});
    const [vol, setVol] = useState<Record<number, number>>({});
    const [modalRekap, setModalRekap] = useState(false);
    const [rekap, setRekap] = useState({
        filter: 'semua',
        tahun: String(new Date().getFullYear()),
        asal: [] as string[],
        tahun_dari: '',
        semester_dari: '1',
        tahun_sampai: '',
        semester_sampai: '2',
        ruang: '',
        urutRuang: false,
    });
    const [perHalaman, setPerHalaman] = useState(25);
    const [halaman, setHalaman] = useState(1);
    const [tanggal, setTanggal] = useState(() => new Date().toISOString().slice(0, 10));
    const [proses, setProses] = useState(false);
    const [peringatan, setPeringatan] = useState<string | null>(null);
    const [modal, setModal] = useState<{ jenis: 'bast' | 'fakta' | 'edit'; index: number } | null>(null);
    const [editPenerima, setEditPenerima] = useState({ ruang_id: '', pegawai_id: '' });

    const nilaiBaris = (index: number) => ({
        ruang_id: draft[index]?.ruang_id ?? baris[index].ruang_id ?? '',
        pegawai_id: draft[index]?.pegawai_id ?? baris[index].pegawai_id ?? '',
        keterangan: draft[index]?.keterangan ?? baris[index].keterangan ?? '',
    });

    const nilaiVol = (index: number) => vol[index] ?? baris[index].sisa;

    const adaPerubahan = useMemo(() => Object.keys(draft).length > 0, [draft]);

    const totalHalaman = perHalaman === 0 ? 1 : Math.max(1, Math.ceil(baris.length / perHalaman));
    const mulai = perHalaman === 0 ? 0 : (halaman - 1) * perHalaman;
    const tampil = (perHalaman === 0
        ? baris.map((b, index) => ({ b, index }))
        : baris.slice(mulai, mulai + perHalaman).map((b, i) => ({ b, index: mulai + i })));

    useEffect(() => {
        setHalaman(1);
    }, [filter.search, filter.bulan, filter.tahun, perHalaman]);

    const setBaris = (index: number, patch: Partial<{ ruang_id: string; pegawai_id: string; keterangan: string }>) => {
        setDraft((lama) => ({ ...lama, [index]: { ...nilaiBaris(index), ...patch } }));
    };

    const cari = (patch: Record<string, string>) => {
        router.get('/pencatatan-bm/penyaluran', { ...filter, ...patch }, { preserveState: true, preserveScroll: true });
    };

    const simpan = () => {
        const terisi = Object.keys(draft)
            .map(Number)
            .filter((index) => nilaiBaris(index).ruang_id !== '');

        if (terisi.length === 0) {
            setPeringatan('Belum ada baris yang diisi lokasi/ruang.');
            return;
        }

        setProses(true);
        router.post(
            '/pencatatan-bm/penyaluran',
            {
                baris: terisi.map((index) => ({
                    unit_ids: baris[index].sisa_unit_ids,
                    vol: nilaiVol(index),
                    ruang_id: nilaiBaris(index).ruang_id,
                    pegawai_id: nilaiBaris(index).pegawai_id || null,
                    keterangan: nilaiBaris(index).keterangan || null,
                    tanggal,
                })),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setDraft({});
                    setVol({});
                    setPeringatan(null);
                },
                onError: (errors) => setPeringatan(Object.values(errors)[0] ?? 'Gagal menyimpan penyaluran.'),
                onFinish: () => setProses(false),
            },
        );
    };

    const bukaAksi = (jenis: 'bast' | 'fakta' | 'edit', index: number) => {
        if (!baris[index].sudah_disalurkan) {
            setPeringatan('Belum Disalurkan! Silahkan lakukan penyaluran dan "SIMPAN DATA" terlebih dahulu.');
            return;
        }

        if (jenis === 'edit') {
            setEditPenerima({ ruang_id: baris[index].ruang_id ?? '', pegawai_id: baris[index].pegawai_id ?? '' });
        }

        setModal({ jenis, index });
    };

    const simpanPenerima = () => {
        if (!modal) {
            return;
        }

        if (editPenerima.ruang_id === '') {
            setPeringatan('Pilih penerima dulu!');
            return;
        }

        router.post(
            `/pencatatan-bm/penyaluran/${baris[modal.index].unit_ids[0]}/penerima`,
            { ruang_id: editPenerima.ruang_id, pegawai_id: editPenerima.pegawai_id || null },
            { preserveScroll: true, onSuccess: () => setModal(null) },
        );
    };

    const restart = (index: number) => {
        router.post(`/pencatatan-bm/penyaluran/${baris[index].unit_ids[0]}/reset`, {}, { preserveScroll: true });
    };

    const cetakBast = (index: number) => {
        router.post(
            '/pencatatan-bm/dokumen/bast-baris',
            { unit_ids: baris[index].unit_ids, tanggal },
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    bukaPdfDokumen(page);
                    setModal(null);
                },
                onError: (errors) => {
                    setPeringatan(Object.values(errors)[0] ?? 'BAST gagal dibuat.');
                    setModal(null);
                },
            },
        );
    };

    const cetakFakta = (index: number) => {
        const pegawaiId = baris[index].pegawai_id;
        if (!pegawaiId) {
            setPeringatan('Baris ini belum punya penerima, Pakta Integritas tidak bisa dibuat.');
            return;
        }
        router.post(
            `/pencatatan-bm/dokumen/fakta-integritas/${pegawaiId}`,
            { tanggal },
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    bukaPdfDokumen(page);
                    setModal(null);
                },
                onError: (errors) => {
                    setPeringatan(Object.values(errors)[0] ?? 'Fakta Integritas gagal dibuat.');
                    setModal(null);
                },
            },
        );
    };

    return (
        <AppLayout>
            <Head title="Buku Penyaluran" />

            <div className="space-y-5">
                <header>
                    <h1 className="text-xl font-bold text-slate-800">Buku Penyaluran Belanja Modal</h1>
                    <p className="text-sm text-slate-500">Peralatan dan mesin — isi penerima lalu simpan seluruh baris.</p>
                </header>

                <section className="rounded-2xl border border-slate-200 bg-white p-4">
                    <div className="flex flex-wrap items-end gap-3">
                        <input
                            value={filter.search}
                            onChange={(e) => cari({ search: e.target.value })}
                            placeholder="Cari nama barang, merk/tipe, atau kode barang..."
                            className="w-72 rounded-xl border border-slate-300 px-3 py-2 text-sm"
                        />
                        <select
                            value={filter.bulan}
                            onChange={(e) => cari({ bulan: e.target.value })}
                            className="rounded-xl border border-slate-300 px-3 py-2 text-sm"
                        >
                            <option value="">Semua Bulan</option>
                            {bulanList.map((b) => (
                                <option key={b.value} value={b.value}>{b.label}</option>
                            ))}
                        </select>
                        <select
                            value={filter.tahun}
                            onChange={(e) => cari({ tahun: e.target.value })}
                            className="rounded-xl border border-slate-300 px-3 py-2 text-sm"
                        >
                            <option value="">Semua Tahun</option>
                            {tahunList.map((t) => (
                                <option key={t} value={t}>{t}</option>
                            ))}
                        </select>
                        <button
                            type="button"
                            onClick={() => cari({ search: '', bulan: '', tahun: '' })}
                            className="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-600"
                        >
                            Refresh Filter
                        </button>
                    </div>
                </section>

                {peringatan && (
                    <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        <p>{peringatan}</p>
                        <button type="button" onClick={() => setPeringatan(null)} className="mt-2 text-xs font-bold uppercase">
                            Mengerti
                        </button>
                    </div>
                )}

                <section className="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
                    {baris.length === 0 ? (
                        <EmptyState title="Belum ada data barang" description="Tambahkan barang lewat menu Inventaris terlebih dahulu." />
                    ) : (
                        <table className="w-full text-left text-xs">
                            <thead className="bg-slate-50 text-[11px] uppercase text-slate-500">
                                <tr>
                                    <th className="px-3 py-2">No</th>
                                    <th className="px-3 py-2">Nama Barang</th>
                                    <th className="px-3 py-2">Merk/Type</th>
                                    <th className="px-3 py-2">Kode Barang</th>
                                    <th className="px-3 py-2" colSpan={6}>Pengadaan</th>
                                    <th className="px-3 py-2" colSpan={3}>Penyaluran</th>
                                    <th className="px-3 py-2">No.Reg</th>
                                    <th className="px-3 py-2">Ket</th>
                                    <th className="px-3 py-2">Aksi</th>
                                </tr>
                                <tr className="bg-slate-50 text-[10px] uppercase text-slate-400">
                                    <th colSpan={4}></th>
                                    <th className="px-2 py-1">Tgl</th>
                                    <th className="px-2 py-1">Bln</th>
                                    <th className="px-2 py-1">Thn</th>
                                    <th className="px-2 py-1">Vol</th>
                                    <th className="px-2 py-1">Satuan</th>
                                    <th className="px-2 py-1">Harga Satuan</th>
                                    <th className="px-2 py-1">Nama Penerima</th>
                                    <th className="px-2 py-1">Ruang</th>
                                    <th className="px-2 py-1">Vol</th>
                                    <th colSpan={3}></th>
                                </tr>
                            </thead>
                            <tbody>
                                {tampil.map(({ b, index }) => {
                                    const nilai = nilaiBaris(index);
                                    return (
                                        <tr key={`${b.kode_barang}-${b.nomor_register}`} className={b.sudah_disalurkan ? 'text-slate-400' : 'text-slate-700'}>
                                            <td className="px-3 py-2">{index + 1}</td>
                                            <td className="px-3 py-2">{b.nama_barang ?? '-'}</td>
                                            <td className="px-3 py-2">{b.merk_tipe ?? '-'}</td>
                                            <td className="px-3 py-2 font-mono">{b.kode_barang}</td>
                                            <td className="px-2 py-2">{b.tanggal_perolehan ?? '-'}</td>
                                            <td className="px-2 py-2">{b.bulan_perolehan ?? '-'}</td>
                                            <td className="px-2 py-2">{b.tahun_perolehan}</td>
                                            <td className="px-2 py-2">{b.volume_pengadaan}</td>
                                            <td className="px-2 py-2">{b.satuan ?? '-'}</td>
                                            <td className="px-2 py-2 whitespace-nowrap">{rupiah(b.harga_satuan)}</td>
                                            <td className="px-2 py-2">
                                                <select
                                                    value={nilai.pegawai_id}
                                                    disabled={b.sudah_disalurkan}
                                                    onChange={(e) => setBaris(index, { pegawai_id: e.target.value })}
                                                    className="w-40 rounded-lg border border-slate-300 px-2 py-1 text-xs"
                                                >
                                                    <option value="">-- Pilih Penerima --</option>
                                                    {pegawaiList.map((p) => (
                                                        <option key={p.id} value={p.id}>{p.nama_lengkap}</option>
                                                    ))}
                                                </select>
                                            </td>
                                            <td className="px-2 py-2">
                                                <select
                                                    value={nilai.ruang_id}
                                                    disabled={b.sudah_disalurkan}
                                                    onChange={(e) => setBaris(index, { ruang_id: e.target.value })}
                                                    className="w-32 rounded-lg border border-slate-300 px-2 py-1 text-xs"
                                                >
                                                    <option value="">-- Pilih Lokasi --</option>
                                                    {ruangList.map((r) => (
                                                        <option key={r.id} value={r.id}>{r.nama_ruang}</option>
                                                    ))}
                                                </select>
                                            </td>
                                            <td className="px-2 py-2">
                                                <input
                                                    type="number"
                                                    min={1}
                                                    max={b.sisa}
                                                    value={nilaiVol(index)}
                                                    disabled={b.sisa === 0}
                                                    onChange={(e) => setVol((lama) => ({ ...lama, [index]: Number(e.target.value) }))}
                                                    aria-label={`Jumlah disalurkan baris ${index + 1}`}
                                                    className="w-16 rounded-lg border border-slate-300 px-2 py-1 text-xs"
                                                />
                                                <span className="mt-0.5 block text-[10px] text-slate-400">
                                                    Sisa {b.sisa} dari {b.volume}
                                                </span>
                                            </td>
                                            <td className="px-2 py-2 font-mono">{b.nomor_register}</td>
                                            <td className="px-2 py-2">
                                                <input
                                                    value={nilai.keterangan}
                                                    disabled={b.sudah_disalurkan}
                                                    onChange={(e) => setBaris(index, { keterangan: e.target.value })}
                                                    className="w-32 rounded-lg border border-slate-300 px-2 py-1 text-xs"
                                                />
                                            </td>
                                            <td className="px-2 py-2">
                                                <div className="flex items-center gap-1">
                                                    <button type="button" title="BAST" onClick={() => bukaAksi('bast', index)} className="rounded p-1 hover:bg-slate-100">
                                                        <FileText className="h-4 w-4" />
                                                    </button>
                                                    <button type="button" title="FAKTA" onClick={() => bukaAksi('fakta', index)} className="rounded p-1 hover:bg-slate-100">
                                                        <Download className="h-4 w-4" />
                                                    </button>
                                                    <button type="button" title="Edit" onClick={() => bukaAksi('edit', index)} className="rounded p-1 hover:bg-slate-100">
                                                        <Pencil className="h-4 w-4" />
                                                    </button>
                                                    <button type="button" title="Restart" onClick={() => restart(index)} className="rounded p-1 hover:bg-slate-100">
                                                        <RotateCcw className="h-4 w-4" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    )}

                    {baris.length > 0 && (
                        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 text-xs text-slate-600">
                            <label className="flex items-center gap-2">
                                Tampilkan
                                <select
                                    value={perHalaman}
                                    onChange={(e) => setPerHalaman(Number(e.target.value))}
                                    className="rounded-lg border border-slate-300 px-2 py-1"
                                >
                                    <option value={10}>10</option>
                                    <option value={25}>25</option>
                                    <option value={50}>50</option>
                                    <option value={100}>100</option>
                                    <option value={0}>Semua</option>
                                </select>
                                data per halaman
                            </label>
                            <div className="flex items-center gap-2">
                                <button
                                    type="button"
                                    disabled={halaman <= 1}
                                    onClick={() => setHalaman((n) => Math.max(1, n - 1))}
                                    className="rounded-lg border border-slate-300 px-2 py-1 font-bold disabled:opacity-40"
                                >
                                    &lsaquo;
                                </button>
                                <span>
                                    Halaman {halaman} / {totalHalaman}
                                </span>
                                <button
                                    type="button"
                                    disabled={halaman >= totalHalaman}
                                    onClick={() => setHalaman((n) => Math.min(totalHalaman, n + 1))}
                                    className="rounded-lg border border-slate-300 px-2 py-1 font-bold disabled:opacity-40"
                                >
                                    &rsaquo;
                                </button>
                            </div>
                        </div>
                    )}
                </section>

                <section className="flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-white p-4">
                    <label className="text-xs font-semibold text-slate-600">
                        Tanggal Penyaluran
                        <input
                            type="date"
                            value={tanggal}
                            onChange={(e) => setTanggal(e.target.value)}
                            className="mt-1 block rounded-xl border border-slate-300 px-3 py-2 text-sm"
                        />
                    </label>
                    <button
                        type="button"
                        onClick={simpan}
                        disabled={proses || !adaPerubahan}
                        className="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50"
                    >
                        <Save className="h-4 w-4" /> SIMPAN DATA
                    </button>
                    <button
                        type="button"
                        onClick={() => setModalRekap(true)}
                        className="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-700"
                    >
                        <Download className="h-4 w-4" /> REKAP PENYALURAN PDF
                    </button>
                </section>
            </div>

            <Modal isOpen={modalRekap} onClose={() => setModalRekap(false)} title="REKAP PENYALURAN PDF" maxWidth="md">
                <div className="space-y-3 p-5">
                    <p className="text-sm text-slate-500">Pilih cakupan data yang akan dicetak.</p>

                    <div className="space-y-2">
                        <label className="flex items-center gap-2 text-sm text-slate-700">
                            <input
                                type="radio"
                                name="rekap_filter"
                                checked={rekap.filter === 'semua'}
                                onChange={() => setRekap((r) => ({ ...r, filter: 'semua' }))}
                            />
                            Semua Data
                        </label>
                        <label className="flex items-center gap-2 text-sm text-slate-700">
                            <input
                                type="radio"
                                name="rekap_filter"
                                checked={rekap.filter === 'tahun_sumber'}
                                onChange={() => setRekap((r) => ({ ...r, filter: 'tahun_sumber' }))}
                            />
                            Berdasarkan Tahun &amp; Sumber Perolehan
                        </label>
                        <label className="flex items-center gap-2 text-sm text-slate-700">
                            <input
                                type="radio"
                                name="rekap_filter"
                                checked={rekap.filter === 'rentang'}
                                onChange={() => setRekap((r) => ({ ...r, filter: 'rentang' }))}
                            />
                            Berdasarkan Rentang Tahun &amp; Semester
                        </label>
                        <label className="flex items-center gap-2 text-sm text-slate-700">
                            <input
                                type="radio"
                                name="rekap_filter"
                                checked={rekap.filter === 'ruang'}
                                onChange={() => setRekap((r) => ({ ...r, filter: 'ruang' }))}
                            />
                            Berdasarkan Ruangan
                        </label>
                    </div>

                    <label className="flex items-center gap-2 text-sm text-slate-700">
                        <input
                            type="checkbox"
                            checked={rekap.urutRuang}
                            onChange={(e) => setRekap((r) => ({ ...r, urutRuang: e.target.checked }))}
                        />
                        Urutkan Berdasarkan Ruang (A&ndash;Z)
                    </label>

                    {rekap.filter === 'tahun_sumber' && (
                        <div className="space-y-2 rounded-xl border border-slate-100 p-3">
                            <label className="block text-xs font-semibold text-slate-600">
                                Tahun
                                <input
                                    type="number"
                                    value={rekap.tahun}
                                    onChange={(e) => setRekap((r) => ({ ...r, tahun: e.target.value }))}
                                    className="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
                                />
                            </label>
                            <fieldset>
                                <legend className="text-xs font-semibold text-slate-600">Sumber Perolehan</legend>
                                {asalList.map((asal) => (
                                    <label key={asal} className="mt-1 flex items-center gap-2 text-sm text-slate-700">
                                        <input
                                            type="checkbox"
                                            checked={rekap.asal.includes(asal)}
                                            onChange={(e) => setRekap((r) => ({
                                                ...r,
                                                asal: e.target.checked ? [...r.asal, asal] : r.asal.filter((a) => a !== asal),
                                            }))}
                                        />
                                        {asal}
                                    </label>
                                ))}
                            </fieldset>
                        </div>
                    )}

                    {rekap.filter === 'rentang' && (
                        <div className="grid grid-cols-2 gap-3 rounded-xl border border-slate-100 p-3">
                            <label className="block text-xs font-semibold text-slate-600">
                                Tahun Dari
                                <input
                                    type="number"
                                    value={rekap.tahun_dari}
                                    onChange={(e) => setRekap((r) => ({ ...r, tahun_dari: e.target.value }))}
                                    className="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
                                />
                            </label>
                            <label className="block text-xs font-semibold text-slate-600">
                                Tahun Sampai
                                <input
                                    type="number"
                                    value={rekap.tahun_sampai}
                                    onChange={(e) => setRekap((r) => ({ ...r, tahun_sampai: e.target.value }))}
                                    className="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
                                />
                            </label>
                            <label className="block text-xs font-semibold text-slate-600">
                                Semester Dari
                                <select
                                    value={rekap.semester_dari}
                                    onChange={(e) => setRekap((r) => ({ ...r, semester_dari: e.target.value }))}
                                    className="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
                                >
                                    <option value="1">Semester 1 (Jan - Jun)</option>
                                    <option value="2">Semester 2 (Jul - Des)</option>
                                </select>
                            </label>
                            <label className="block text-xs font-semibold text-slate-600">
                                Semester Sampai
                                <select
                                    value={rekap.semester_sampai}
                                    onChange={(e) => setRekap((r) => ({ ...r, semester_sampai: e.target.value }))}
                                    className="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
                                >
                                    <option value="1">Semester 1 (Jan - Jun)</option>
                                    <option value="2">Semester 2 (Jul - Des)</option>
                                </select>
                            </label>
                        </div>
                    )}

                    {rekap.filter === 'ruang' && (
                        <label className="block text-xs font-semibold text-slate-600">
                            Ruang
                            <select
                                value={rekap.ruang}
                                onChange={(e) => setRekap((r) => ({ ...r, ruang: e.target.value }))}
                                className="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
                            >
                                <option value="">-- Pilih Ruangan --</option>
                                {ruangList.map((r) => (
                                    <option key={r.id} value={r.id}>{r.nama_ruang}</option>
                                ))}
                            </select>
                        </label>
                    )}

                    <div className="flex justify-end gap-2">
                        <button type="button" onClick={() => setModalRekap(false)} className="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold">
                            Batal
                        </button>
                        <button
                            type="button"
                            onClick={() => {
                                if (rekap.filter === 'rentang') {
                                    if (rekap.tahun_dari === '' || rekap.tahun_sampai === '') {
                                        setPeringatan('Harap pilih Tahun Dari dan Tahun Sampai terlebih dahulu!');
                                        return;
                                    }
                                    if (Number(rekap.tahun_sampai) < Number(rekap.tahun_dari)) {
                                        setPeringatan('Tahun Akhir tidak boleh lebih kecil dari Tahun Awal!');
                                        return;
                                    }
                                    const skorAwal = Number(rekap.tahun_dari) * 10 + (rekap.semester_dari === '2' ? 2 : 1);
                                    const skorAkhir = Number(rekap.tahun_sampai) * 10 + (rekap.semester_sampai === '1' ? 1 : 2);
                                    if (skorAkhir < skorAwal) {
                                        setPeringatan('Rentang Tahun/Semester Akhir tidak boleh lebih kecil dari Tahun/Semester Awal!');
                                        return;
                                    }
                                }

                                const params = new URLSearchParams({ filter: rekap.filter });
                                if (rekap.urutRuang) {
                                    params.set('berdasarkan_ruang', 'ya');
                                }
                                if (rekap.filter === 'tahun_sumber') {
                                    params.set('tahun', rekap.tahun);
                                    rekap.asal.forEach((a) => params.append('asal[]', a));
                                }
                                if (rekap.filter === 'rentang') {
                                    params.set('tahun_dari', rekap.tahun_dari);
                                    params.set('semester_dari', rekap.semester_dari);
                                    params.set('tahun_sampai', rekap.tahun_sampai);
                                    params.set('semester_sampai', rekap.semester_sampai);
                                }
                                if (rekap.filter === 'ruang') {
                                    params.set('ruang', rekap.ruang);
                                }
                                window.open(`/pencatatan-bm/rekap-penyaluran?${params.toString()}`, '_blank');
                            }}
                            className="rounded-xl bg-blue-600 px-4 py-2 text-sm font-bold text-white"
                        >
                            Generate PDF
                        </button>
                    </div>
                </div>
            </Modal>

            <Modal isOpen={modal !== null} onClose={() => setModal(null)} title={modal?.jenis === 'edit' ? 'Edit Penerima' : modal?.jenis === 'fakta' ? 'Generate FAKTA INTEGRITAS' : 'Generate BAST'} maxWidth="md">
                {modal?.jenis === 'bast' && (
                    <div className="space-y-3 p-5">
                        <h2 className="text-lg font-bold text-slate-800">Generate BAST</h2>
                        <p className="text-sm text-slate-500">
                            Nomor surat diisi otomatis oleh sistem berdasarkan kode klasifikasi sekolah.
                        </p>
                        <label className="block text-xs font-semibold text-slate-600">
                            Nomor Surat (Otomatis)
                            <input
                                readOnly
                                value={nomorBastBerikutnya ?? '.......'}
                                className="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-sm text-slate-500"
                            />
                        </label>
                        <label className="block text-xs font-semibold text-slate-600">
                            Tanggal
                            <input
                                type="date"
                                value={tanggal}
                                onChange={(e) => setTanggal(e.target.value)}
                                className="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
                            />
                        </label>
                        <div className="flex justify-end gap-2">
                            <button type="button" onClick={() => setModal(null)} className="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold">
                                Tutup
                            </button>
                            <button type="button" onClick={() => cetakBast(modal.index)} className="rounded-xl bg-blue-600 px-4 py-2 text-sm font-bold text-white">
                                Generate PDF
                            </button>
                        </div>
                    </div>
                )}

                {modal?.jenis === 'fakta' && (
                    <div className="space-y-3 p-5">
                        <h2 className="text-lg font-bold text-slate-800">Generate FAKTA INTEGRITAS</h2>
                        <p className="text-sm text-slate-500">Tanggal otomatis mengikuti waktu generate.</p>
                        <div className="flex justify-end gap-2">
                            <button type="button" onClick={() => setModal(null)} className="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold">
                                Tutup
                            </button>
                            <button type="button" onClick={() => cetakFakta(modal.index)} className="rounded-xl bg-blue-600 px-4 py-2 text-sm font-bold text-white">
                                Generate PDF
                            </button>
                        </div>
                    </div>
                )}

                {modal?.jenis === 'edit' && (
                    <div className="space-y-3 p-5">
                        <h2 className="text-lg font-bold text-slate-800">Edit Penerima</h2>
                        <label className="block text-xs font-semibold text-slate-600">
                            NAMA PENERIMA
                            <select
                                value={editPenerima.pegawai_id}
                                onChange={(e) => setEditPenerima((lama) => ({ ...lama, pegawai_id: e.target.value }))}
                                className="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
                            >
                                <option value="">-- Pilih Penerima --</option>
                                {pegawaiList.map((p) => (
                                    <option key={p.id} value={p.id}>{p.nama_lengkap}</option>
                                ))}
                            </select>
                        </label>
                        <label className="block text-xs font-semibold text-slate-600">
                            RUANG
                            <select
                                value={editPenerima.ruang_id}
                                onChange={(e) => setEditPenerima((lama) => ({ ...lama, ruang_id: e.target.value }))}
                                className="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
                            >
                                <option value="">-- Pilih Lokasi --</option>
                                {ruangList.map((r) => (
                                    <option key={r.id} value={r.id}>{r.nama_ruang}</option>
                                ))}
                            </select>
                        </label>
                        <div className="flex justify-end gap-2">
                            <button type="button" onClick={() => setModal(null)} className="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold">
                                Batal
                            </button>
                            <button type="button" onClick={simpanPenerima} className="rounded-xl bg-blue-600 px-4 py-2 text-sm font-bold text-white">
                                Simpan
                            </button>
                        </div>
                    </div>
                )}
            </Modal>
        </AppLayout>
    );
}
