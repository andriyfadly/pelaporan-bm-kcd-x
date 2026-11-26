import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';
import SearchInput from '@/Components/SearchInput';
import EmptyState from '@/Components/EmptyState';
import Modal from '@/Components/Modal';
import ConfirmDialog from '@/Components/ConfirmDialog';
import { formatRupiah, tanggalHariIni } from '@/Utils/format';
import { Copy, Download, Eye, Pencil, Plus, Trash2, Upload } from 'lucide-react';

interface UnitItem {
    id: string;
    nomor_register: string;
    kode_barang: string;
    nama_barang: string | null;
    merk_tipe: string | null;
    bahan: string | null;
    foto_path: string | null;
    satuan: string | null;
    tanggal_perolehan: string | null;
    tahun_perolehan: number;
    asal_perolehan: string | null;
    harga_satuan: string;
    nilai_perolehan_unit: string;
    no_sertifikat: string | null;
    keterangan: string | null;
    kondisi: string;
    status: string;
    ruang?: { id: string; nama_ruang: string } | null;
    pegawai?: { id: string; nama_lengkap: string } | null;
    foto_terbaru: { id: string } | null;
}

interface Props {
    units: {
        data: UnitItem[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    filter: { search: string; tahun: string; asal: string };
    tahunList: number[];
    asalList: string[];
    kondisiList: Record<string, string>;
    canKelola: boolean;
    canCetak: boolean;
}

const BADGE_KONDISI: Record<string, string> = {
    B: 'bg-emerald-50 text-emerald-700 border-emerald-100',
    RR: 'bg-amber-50 text-amber-700 border-amber-100',
    RB: 'bg-rose-50 text-rose-700 border-rose-100',
    H: 'bg-slate-100 text-slate-600 border-slate-200',
};

const KOSONG = {
    kode_barang: '',
    nama_barang: '',
    merk_tipe: '',
    bahan: '',
    satuan: '',
    tanggal_perolehan: tanggalHariIni(),
    volume: 1,
    harga_satuan: 0,
    asal_perolehan: '',
    no_sertifikat: '',
    kondisi: 'B',
    keterangan: '',
};

export default function Index({ units, filter, tahunList, asalList, kondisiList, canKelola, canCetak }: Props) {
    const { errors: galatHalaman } = usePage<{ errors: Record<string, string> }>().props;
    const galatImpor = Object.entries(galatHalaman ?? {}).filter(([kunci]) => kunci.startsWith('baris.'));

    const [modalInput, setModalInput] = useState(false);
    const [modalEdit, setModalEdit] = useState<UnitItem | null>(null);
    const [hapus, setHapus] = useState<UnitItem | null>(null);
    const [hapusSemua, setHapusSemua] = useState(false);
    const [berkasImpor, setBerkasImpor] = useState<File | null>(null);
    const [registerPratinjau, setRegisterPratinjau] = useState('');
    const [terpilih, setTerpilih] = useState<string[]>([]);
    const [modalKondisi, setModalKondisi] = useState(false);
    const [kondisiMassal, setKondisiMassal] = useState<Record<string, string>>({});
    const [kondisiSemua, setKondisiSemua] = useState('');
    const [galatKondisi, setGalatKondisi] = useState('');
    const [fotoProses, setFotoProses] = useState<string | null>(null);
    const [galatFoto, setGalatFoto] = useState<Record<string, string>>({});

    const [copySource, setCopySource] = useState<UnitItem | null>(null);
    const [copyTargets, setCopyTargets] = useState<string[]>([]);
    const [copyFields, setCopyFields] = useState<Record<string, boolean>>({
        merk_tipe: true,
        bahan: true,
        satuan: true,
        asal_perolehan: true,
        no_sertifikat: true,
        keterangan: false,
        kondisi: false,
    });
    const [copyProcessing, setCopyProcessing] = useState(false);

    const unggahFoto = (unitId: string, berkas: File | null, input: HTMLInputElement | null) => {
        if (!berkas) {
            return;
        }

        setFotoProses(unitId);
        setGalatFoto((sebelum) => ({ ...sebelum, [unitId]: '' }));

        router.post(`/pencatatan-bm/inventaris/${unitId}/foto`, { foto: berkas }, {
            forceFormData: true,
            preserveScroll: true,
            onError: () => {
                setGalatFoto((sebelum) => ({ ...sebelum, [unitId]: 'Gagal mengunggah foto. Coba lagi.' }));
            },
            onFinish: () => {
                setFotoProses(null);
                if (input) {
                    input.value = '';
                }
            },
        });
    };

    const form = useForm({ ...KOSONG });
    const edit = useForm({
        kode_barang: '',
        nama_barang: '',
        merk_tipe: '',
        bahan: '',
        satuan: '',
        tanggal_perolehan: '',
        harga_satuan: 0,
        nilai_perolehan_unit: 0,
        asal_perolehan: '',
        no_sertifikat: '',
        kondisi: 'B',
        keterangan: '',
    });

    const terapkan = (patch: Partial<{ search: string; tahun: string; asal: string }>) => {
        router.get(
            '/pencatatan-bm/inventaris',
            { ...filter, ...patch } as Record<string, string>,
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const pratinjauRegister = (kode: string, tanggal: string) => {
        if (kode.trim() === '') {
            setRegisterPratinjau('');
            return;
        }

        const tahun = tanggal ? new Date(tanggal).getFullYear() : new Date().getFullYear();
        fetch(`/pencatatan-bm/inventaris/register-berikutnya?kode_barang=${encodeURIComponent(kode)}&tahun=${tahun}`, {
            headers: { Accept: 'application/json' },
        })
            .then((r) => (r.ok ? r.json() : Promise.reject(new Error('gagal'))))
            .then((data: { register: string }) => setRegisterPratinjau(data.register))
            .catch(() => setRegisterPratinjau(''));
    };

    const simpanInput = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/pencatatan-bm/inventaris', {
            preserveScroll: true,
            onSuccess: () => tutupInput(),
        });
    };

    const simpanEdit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!modalEdit) {
            return;
        }

        edit.put(`/pencatatan-bm/inventaris/${modalEdit.id}`, {
            preserveScroll: true,
            onSuccess: () => tutupEdit(),
        });
    };

    const bukaCopyModal = (unit: UnitItem) => {
        setCopySource(unit);
        // Daftar target: semua unit data kecuali source
        const targets = units.data
            .filter((u) => u.id !== unit.id)
            .map((u) => u.id);
        setCopyTargets(targets);
        // Reset fields selection
        setCopyFields({
            merk_tipe: true,
            bahan: true,
            satuan: true,
            asal_perolehan: true,
            no_sertifikat: true,
            keterangan: false,
            kondisi: false,
        });
    };

    const konfirmasiHapus = () => {
        if (!hapus) {
            return;
        }

        router.delete(`/pencatatan-bm/inventaris/${hapus.id}`, {
            preserveScroll: true,
            onSuccess: () => setHapus(null),
        });
    };

    const konfirmasiHapusSemua = () => {
        router.post(
            '/pencatatan-bm/inventaris-hapus-semua',
            { konfirmasi: 'HAPUS SEMUA' },
            { preserveScroll: true, onSuccess: () => setHapusSemua(false) },
        );
    };

    const simpanCopy = async () => {
        if (!copySource || copyTargets.length === 0) {
            return;
        }

        setCopyProcessing(true);

        for (const targetId of copyTargets) {
            const targetUnit = units.data.find((u) => u.id === targetId);

            if (!targetUnit) {
                continue;
            }

            // Merge: start from target's current values, override with selected fields from source
            const payload: Record<string, any> = {
                kode_barang: targetUnit.kode_barang,
                nama_barang: targetUnit.nama_barang ?? '',
                merk_tipe: targetUnit.merk_tipe ?? '',
                bahan: targetUnit.bahan ?? '',
                satuan: targetUnit.satuan ?? '',
                tanggal_perolehan: targetUnit.tanggal_perolehan ?? '',
                harga_satuan: Number(targetUnit.harga_satuan) || 0,
                asal_perolehan: targetUnit.asal_perolehan ?? '',
                no_sertifikat: targetUnit.no_sertifikat ?? '',
                keterangan: targetUnit.keterangan ?? '',
                kondisi: targetUnit.kondisi,
            };

            // Override with selected fields from copySource
            if (copyFields.merk_tipe && copySource.merk_tipe !== undefined) {
                payload.merk_tipe = copySource.merk_tipe ?? '';
            }
            if (copyFields.bahan && copySource.bahan !== undefined) {
                payload.bahan = copySource.bahan ?? '';
            }
            if (copyFields.satuan && copySource.satuan !== undefined) {
                payload.satuan = copySource.satuan ?? '';
            }
            if (copyFields.asal_perolehan && copySource.asal_perolehan !== undefined) {
                payload.asal_perolehan = copySource.asal_perolehan ?? '';
            }
            if (copyFields.no_sertifikat && copySource.no_sertifikat !== undefined) {
                payload.no_sertifikat = copySource.no_sertifikat ?? '';
            }
            if (copyFields.keterangan) {
                payload.keterangan = copySource.keterangan ?? '';
            }
            if (copyFields.kondisi) {
                payload.kondisi = copySource.kondisi;
            }

            await router.put(`/pencatatan-bm/inventaris/${targetId}`, payload, {
                preserveScroll: true,
                onSuccess: () => {},
                onError: () => {},
            });
        }

        setCopyProcessing(false);
        setCopySource(null);
        setCopyTargets([]);
        router.reload({ only: ['units'] });
    };

    const impor = (e: React.FormEvent) => {
        e.preventDefault();
        if (!berkasImpor) {
            return;
        }

        router.post(
            '/pencatatan-bm/inventaris-import',
            { file: berkasImpor },
            { preserveScroll: true, forceFormData: true, onSuccess: () => setBerkasImpor(null) },
        );
    };

    const tutupInput = () => {
        setModalInput(false);
        form.reset();
        form.clearErrors();
        setRegisterPratinjau('');
    };

    const bukaEdit = (unit: UnitItem) => {
        edit.setData({
            kode_barang: unit.kode_barang,
            nama_barang: unit.nama_barang ?? '',
            merk_tipe: unit.merk_tipe ?? '',
            bahan: unit.bahan ?? '',
            satuan: unit.satuan ?? '',
            tanggal_perolehan: unit.tanggal_perolehan ?? '',
            harga_satuan: Number(unit.harga_satuan),
            nilai_perolehan_unit: Number(unit.nilai_perolehan_unit),
            asal_perolehan: unit.asal_perolehan ?? '',
            no_sertifikat: unit.no_sertifikat ?? '',
            // Sentinel '-' bukan pilihan form; tampilkan sebagai "belum ditentukan".
            kondisi: unit.kondisi === '-' ? '' : unit.kondisi,
            keterangan: unit.keterangan ?? '',
        });
        setModalEdit(unit);
    };

    const tutupEdit = () => {
        setModalEdit(null);
        edit.reset();
        edit.clearErrors();
    };

    const togglePilih = (id: string) => {
        setTerpilih((sebelum) => (sebelum.includes(id) ? sebelum.filter((x) => x !== id) : [...sebelum, id]));
    };

    const semuaTerpilih = units.data.length > 0 && units.data.every((u) => terpilih.includes(u.id));

    const togglePilihSemua = () => {
        setTerpilih(semuaTerpilih ? [] : units.data.map((u) => u.id));
    };

    const bukaModalKondisi = () => {
        const awal: Record<string, string> = {};
        units.data.filter((u) => terpilih.includes(u.id)).forEach((u) => {
            awal[u.id] = u.kondisi === '-' ? '' : u.kondisi;
        });
        setKondisiMassal(awal);
        setKondisiSemua('');
        setGalatKondisi('');
        setModalKondisi(true);
    };

    const pilihKondisiSemua = (kode: string) => {
        setKondisiSemua(kode);
        if (kode === '') {
            return;
        }
        const baru: Record<string, string> = {};
        Object.keys(kondisiMassal).forEach((id) => {
            baru[id] = kode;
        });
        setKondisiMassal(baru);
    };

    const simpanKondisiMassal = (e: React.FormEvent) => {
        e.preventDefault();

        const belumDipilih = terpilih.some((id) => !(kondisiMassal[id] ?? ''));
        if (belumDipilih) {
            setGalatKondisi('Pilih kondisi untuk semua unit terlebih dahulu.');
            return;
        }

        setGalatKondisi('');
        router.put(
            '/pencatatan-bm/inventaris/kondisi-massal',
            { unit_ids: terpilih, kondisi: kondisiMassal },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setModalKondisi(false);
                    setTerpilih([]);
                    setGalatKondisi('');
                },
            },
        );
    };

    return (
        <AppLayout>
            <Head title="Inventaris BM" />

            <div className="space-y-5">
                <header>
                    <p className="text-xs font-bold uppercase tracking-wider text-slate-500">Sistem Inventaris</p>
                    <h1 className="text-xl font-bold text-slate-800">Daftar Barang Belanja Modal</h1>
                </header>

                {canKelola && (
                    <section className="rounded-2xl border border-slate-200 bg-white p-4">
                        <h2 className="font-bold text-slate-800">Input &amp; Opsi Data</h2>
                        <p className="text-xs text-slate-500">Tambah data barang inventaris atau hapus seluruh data.</p>
                        <div className="mt-3 flex flex-wrap gap-2">
                            <button
                                type="button"
                                onClick={() => setModalInput(true)}
                                className="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-3 py-2 text-xs font-bold text-white"
                            >
                                <Plus className="h-3.5 w-3.5" /> Input Manual
                            </button>
                            <button
                                type="button"
                                onClick={() => setHapusSemua(true)}
                                className="inline-flex items-center gap-1.5 rounded-xl border border-rose-200 px-3 py-2 text-xs font-bold text-rose-600"
                            >
                                <Trash2 className="h-3.5 w-3.5" /> Hapus Semua Data
                            </button>
                        </div>
                    </section>
                )}

                {canKelola && (
                    <section className="rounded-2xl border border-slate-200 bg-white p-4">
                        <h2 className="font-bold text-slate-800">Import Excel</h2>
                        <p className="text-xs text-slate-500">Format file: .xlsx</p>
                        <form onSubmit={impor} className="mt-3 flex flex-wrap items-center gap-2">
                            <input
                                type="file"
                                accept=".xlsx,.xls,.csv"
                                onChange={(e) => setBerkasImpor(e.target.files?.[0] ?? null)}
                                aria-label="Pilih file Excel"
                                className="min-h-10 flex-1 text-xs text-slate-600 file:mr-2 file:cursor-pointer file:rounded-lg file:border file:border-slate-200 file:bg-white file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-slate-700"
                            />
                            <button
                                type="submit"
                                disabled={!berkasImpor}
                                className="inline-flex items-center gap-1.5 rounded-xl bg-slate-800 px-3 py-2 text-xs font-bold text-white disabled:opacity-50"
                            >
                                <Upload className="h-3.5 w-3.5" /> Import Data
                            </button>
                            <a href="/pencatatan-bm/inventaris-template" className="inline-flex min-h-9 items-center text-xs font-bold text-blue-600 hover:underline">
                                Download Template
                            </a>
                        </form>

                        {galatImpor.length > 0 && (
                            <ul className="mt-3 space-y-1 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                                {galatImpor.map(([kunci, pesan]) => (
                                    <li key={kunci}>{pesan}</li>
                                ))}
                            </ul>
                        )}
                    </section>
                )}

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    <div className="flex flex-wrap items-center gap-2 border-b border-slate-100 p-4">
                        <h2 className="mr-auto font-bold text-slate-800">Daftar Barang</h2>
                        <SearchInput
                            value={filter.search}
                            onChange={(value) => terapkan({ search: value })}
                            placeholder="Cari nama, kode, atau merk..."
                            ariaLabel="Cari barang inventaris"
                            className="w-full sm:w-72"
                        />
                        <select
                            value={filter.tahun}
                            onChange={(e) => terapkan({ tahun: e.target.value })}
                            aria-label="Filter tahun"
                            className="min-h-10 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700"
                        >
                            <option value="">Semua Tahun</option>
                            {tahunList.map((t) => (
                                <option key={t} value={t}>{t}</option>
                            ))}
                        </select>
                        <select
                            value={filter.asal}
                            onChange={(e) => terapkan({ asal: e.target.value })}
                            aria-label="Filter sumber perolehan"
                            className="min-h-10 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700"
                        >
                            <option value="">Semua Sumber Perolehan</option>
                            {asalList.map((a) => (
                                <option key={a} value={a}>{a}</option>
                            ))}
                        </select>
                    </div>

                    {canKelola && terpilih.length > 0 && (
                        <div className="flex flex-wrap items-center gap-2 border-b border-blue-100 bg-blue-50 px-4 py-2.5">
                            <span className="mr-auto text-xs font-bold text-blue-800">{terpilih.length} unit dipilih</span>
                            <button
                                type="button"
                                onClick={bukaModalKondisi}
                                className="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-bold text-white"
                            >
                                Ubah Kondisi
                            </button>
                            <button
                                type="button"
                                onClick={() => setTerpilih([])}
                                className="rounded-lg border border-blue-200 px-3 py-1.5 text-xs font-bold text-blue-700"
                            >
                                Batal
                            </button>
                        </div>
                    )}

                    {units.data.length === 0 ? (
                        <EmptyState title="Data barang tidak ditemukan..." description="Ubah filter atau tambahkan barang baru." />
                    ) : (
                        <>
                        <ul className="divide-y divide-slate-100 md:hidden">
                            {units.data.map((unit, index) => (
                                <li key={unit.id} className="space-y-3 p-4">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-bold text-slate-800">{unit.nama_barang ?? '-'}</p>
                                            <p className="text-xs text-slate-500">{unit.merk_tipe ?? '-'}</p>
                                        </div>
                                        <span className="shrink-0 rounded-full border border-slate-200 px-2 py-0.5 font-mono text-xs text-slate-600">
                                            {unit.nomor_register}
                                        </span>
                                    </div>

                                    {unit.foto_terbaru && (
                                        <img
                                            src={`/pencatatan-bm/foto/${unit.foto_terbaru.id}`}
                                            alt={`Foto ${unit.nama_barang ?? unit.kode_barang}`}
                                            className="h-20 w-28 rounded-lg border border-slate-200 object-cover"
                                        />
                                    )}

                                    <dl className="grid grid-cols-2 gap-x-3 gap-y-1 text-xs">
                                        <dt className="text-slate-500">Kode</dt>
                                        <dd className="text-right font-mono text-slate-700">{unit.kode_barang}</dd>
                                        <dt className="text-slate-500">Tgl Perolehan</dt>
                                        <dd className="text-right text-slate-700">{unit.tanggal_perolehan ? unit.tanggal_perolehan.split('-').reverse().join('/') : '-'}</dd>
                                        <dt className="text-slate-500">Sumber</dt>
                                        <dd className="text-right text-slate-700">{unit.asal_perolehan ?? '-'}</dd>
                                        <dt className="text-slate-500">Volume</dt>
                                        <dd className="text-right text-slate-700">1 {unit.satuan ?? 'Unit'}</dd>
                                        <dt className="text-slate-500">Harga Satuan</dt>
                                        <dd className="text-right text-slate-700">{formatRupiah(unit.harga_satuan)}</dd>
                                        <dt className="text-slate-500">Kondisi</dt>
                                        <dd className="text-right">
                                            <span className={`rounded-full border px-2 py-0.5 text-xs font-bold ${BADGE_KONDISI[unit.kondisi] ?? ''}`}>
                                                {kondisiList[unit.kondisi] ?? unit.kondisi}
                                            </span>
                                        </dd>
                                    </dl>

                                    {canKelola && (
                                        <label className="flex min-h-9 items-center gap-2 text-xs font-bold text-slate-600">
                                            <input
                                                type="checkbox"
                                                checked={terpilih.includes(unit.id)}
                                                onChange={() => togglePilih(unit.id)}
                                                aria-label={`Pilih unit ${unit.nomor_register}`}
                                            />
                                            Pilih unit #{index + 1}
                                        </label>
                                    )}

                                    <div className="flex flex-wrap items-center gap-1">
                                        <Link
                                            href={`/pencatatan-bm/inventaris/${unit.id}`}
                                            aria-label={`Detail unit ${unit.nomor_register}`}
                                            className="inline-flex min-h-9 items-center gap-1 rounded-lg border border-slate-200 px-2 text-xs font-bold text-slate-700"
                                        >
                                            <Eye className="h-4 w-4" /> Detail
                                        </Link>
                                        {canKelola && (
                                            <>
                                                <button
                                                    type="button"
                                                    onClick={() => bukaEdit(unit)}
                                                    aria-label={`Edit unit ${unit.nomor_register}`}
                                                    className="inline-flex min-h-9 items-center gap-1 rounded-lg border border-slate-200 px-2 text-xs font-bold text-slate-700"
                                                >
                                                    <Pencil className="h-4 w-4" /> Edit
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => bukaCopyModal(unit)}
                                                    aria-label={`Salin unit ${unit.nomor_register}`}
                                                    className="inline-flex min-h-9 items-center gap-1 rounded-lg border border-slate-200 px-2 text-xs font-bold text-slate-700"
                                                >
                                                    <Copy className="h-4 w-4" /> Salin
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => setHapus(unit)}
                                                    aria-label={`Hapus unit ${unit.nomor_register}`}
                                                    className="inline-flex min-h-9 items-center gap-1 rounded-lg border border-rose-200 px-2 text-xs font-bold text-rose-600"
                                                >
                                                    <Trash2 className="h-4 w-4" /> Hapus
                                                </button>
                                                <label className="inline-flex min-h-9 items-center gap-1 rounded-lg border border-slate-200 px-2 text-xs font-bold text-blue-700">
                                                    {fotoProses === unit.id ? 'Mengunggah...' : 'Foto'}
                                                    <input
                                                        type="file"
                                                        accept="image/jpeg,image/png,image/webp"
                                                        className="hidden"
                                                        disabled={fotoProses === unit.id}
                                                        onChange={(e) => {
                                                            const input = e.currentTarget;
                                                            unggahFoto(unit.id, input.files?.[0] ?? null, input);
                                                        }}
                                                    />
                                                </label>
                                            </>
                                        )}
                                    </div>
                                    {galatFoto[unit.id] && (
                                        <p className="text-xs text-rose-600">{galatFoto[unit.id]}</p>
                                    )}
                                </li>
                            ))}
                        </ul>

                        <div className="hidden md:block overflow-x-auto">
                            <table className="w-full min-w-[1080px] text-sm">
                                <thead>
                                    <tr className="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-500">
                                        {canKelola && (
                                            <th className="w-10 px-4 py-3">
                                                <input
                                                    type="checkbox"
                                                    checked={semuaTerpilih}
                                                    onChange={togglePilihSemua}
                                                    aria-label="Pilih semua baris"
                                                />
                                            </th>
                                        )}
                                        <th className="px-4 py-3">No</th>
                                        <th className="px-4 py-3">No. Reg</th>
                                        <th className="px-4 py-3">Foto Barang</th>
                                        <th className="px-4 py-3">Nama Barang / Merk</th>
                                        <th className="px-4 py-3">Tgl Perolehan</th>
                                        <th className="px-4 py-3">Sumber Perolehan</th>
                                        <th className="px-4 py-3">Vol</th>
                                        <th className="px-4 py-3 text-right">Harga Satuan</th>
                                        <th className="px-4 py-3">Kondisi</th>
                                        <th className="px-4 py-3 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {units.data.map((unit, index) => (
                                        <tr key={unit.id} className="border-b border-slate-50 last:border-0 hover:bg-slate-50/60">
                                            {canKelola && (
                                                <td className="px-4 py-3">
                                                    <input
                                                        type="checkbox"
                                                        checked={terpilih.includes(unit.id)}
                                                        onChange={() => togglePilih(unit.id)}
                                                        aria-label={`Pilih unit ${unit.nomor_register}`}
                                                    />
                                                </td>
                                            )}
                                            <td className="px-4 py-3 text-slate-500">{index + 1}</td>
                                            <td className="whitespace-nowrap px-4 py-3 font-mono text-xs font-bold text-slate-700">{unit.nomor_register}</td>
                                            <td className="px-4 py-3">
                                                {unit.foto_terbaru ? (
                                                    <img
                                                        src={`/pencatatan-bm/foto/${unit.foto_terbaru.id}`}
                                                        alt={`Foto ${unit.nama_barang ?? unit.kode_barang}`}
                                                        className="h-16 w-20 rounded-lg border border-slate-200 object-cover"
                                                    />
                                                ) : (
                                                    <span className="text-xs text-slate-500">Belum ada foto</span>
                                                )}
                                                {canKelola && (
                                                    <label className="mt-1 block cursor-pointer text-xs font-bold text-blue-600 hover:underline">
                                                        {fotoProses === unit.id ? 'Mengunggah...' : 'Pilih Foto'}
                                                        <input
                                                            type="file"
                                                            accept="image/jpeg,image/png,image/webp"
                                                            className="hidden"
                                                            disabled={fotoProses === unit.id}
                                                            onChange={(e) => {
                                                                const input = e.currentTarget;
                                                                unggahFoto(unit.id, input.files?.[0] ?? null, input);
                                                            }}
                                                        />
                                                    </label>
                                                )}
                                                {galatFoto[unit.id] && (
                                                    <p className="mt-1 text-xs text-rose-600">{galatFoto[unit.id]}</p>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <p className="font-semibold text-slate-700">{unit.nama_barang ?? '-'}</p>
                                                <p className="text-xs text-slate-500">{unit.merk_tipe ?? '-'}</p>
                                            </td>
                                            <td className="whitespace-nowrap px-4 py-3 text-slate-600">
                                                {unit.tanggal_perolehan ? unit.tanggal_perolehan.split('-').reverse().join('/') : '-'}
                                            </td>
                                            <td className="px-4 py-3 text-slate-600">{unit.asal_perolehan ?? '-'}</td>
                                            <td className="whitespace-nowrap px-4 py-3 text-slate-600">1 {unit.satuan ?? 'Unit'}</td>
                                            <td className="whitespace-nowrap px-4 py-3 text-right text-slate-600">{formatRupiah(unit.harga_satuan)}</td>
                                            <td className="px-4 py-3">
                                                <span className={`rounded-full border px-2 py-0.5 text-xs font-bold ${BADGE_KONDISI[unit.kondisi] ?? ''}`}>
                                                    {kondisiList[unit.kondisi] ?? unit.kondisi}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center justify-end gap-1">
                                                    <Link
                                                        href={`/pencatatan-bm/inventaris/${unit.id}`}
                                                        title="Detail Kondisi Unit"
                                                        aria-label="Detail Kondisi Unit"
                                                        className="inline-flex min-h-9 min-w-9 items-center justify-center rounded-lg p-1.5 text-slate-500 hover:bg-slate-100"
                                                    >
                                                        <Eye className="h-4 w-4" />
                                                    </Link>
                                                    {canKelola && (
                                                        <>
                                                            <button
                                                                type="button"
                                                                title="Edit Barang"
                                                                aria-label="Edit Barang"
                                                                onClick={() => bukaEdit(unit)}
                                                                className="inline-flex min-h-9 min-w-9 items-center justify-center rounded-lg p-1.5 text-slate-500 hover:bg-slate-100"
                                                            >
                                                                <Pencil className="h-4 w-4" />
                                                            </button>
                                                            <button
                                                                type="button"
                                                                title="Salin ke unit lain"
                                                                aria-label="Salin ke unit lain"
                                                                onClick={() => bukaCopyModal(unit)}
                                                                className="inline-flex min-h-9 min-w-9 items-center justify-center rounded-lg p-1.5 text-slate-500 hover:bg-slate-100"
                                                            >
                                                                <Copy className="h-4 w-4" />
                                                            </button>
                                                            <button
                                                                type="button"
                                                                title="Hapus Barang"
                                                                aria-label="Hapus Barang"
                                                                onClick={() => setHapus(unit)}
                                                                className="inline-flex min-h-9 min-w-9 items-center justify-center rounded-lg p-1.5 text-rose-500 hover:bg-rose-50"
                                                            >
                                                                <Trash2 className="h-4 w-4" />
                                                            </button>
                                                        </>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        </>
                    )}

                    {units.data.length > 0 && (
                        <div className="px-4 pb-4">
                            <Pagination links={units.links} total={units.total} />
                        </div>
                    )}
                </section>

                {canCetak && (
                    <div className="flex justify-end">
                        <a
                            href="/pencatatan-bm/inventaris-export"
                            className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50"
                        >
                            <Download className="h-3.5 w-3.5" /> Ekspor Excel
                        </a>
                    </div>
                )}
            </div>

            <Modal isOpen={modalInput} onClose={tutupInput} title="INPUT DATA INVENTARIS MANUAL">
                <form onSubmit={simpanInput} className="space-y-3">
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <label htmlFor="input-register-pratinjau" className="block text-xs font-semibold text-slate-600">
                            No. Register (Otomatis)
                            <input
                                id="input-register-pratinjau"
                                readOnly
                                value={registerPratinjau || '.......'}
                                className="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-sm text-slate-500"
                            />
                        </label>

                        <label htmlFor="input-kode-barang" className="block text-xs font-semibold text-slate-600">
                            Kode Barang / Cari Nama Barang *
                            <input
                                id="input-kode-barang"
                                type="text"
                                value={form.data.kode_barang}
                                onChange={(e) => {
                                    form.setData('kode_barang', e.target.value);
                                    pratinjauRegister(e.target.value, form.data.tanggal_perolehan);
                                }}
                                placeholder="Contoh: 1.02.01 atau ketik nama barang Laptop..."
                                className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                            />
                            {form.errors.kode_barang && <p className="mt-1 text-xs text-rose-600">{form.errors.kode_barang}</p>}
                        </label>

                        <label htmlFor="input-nama-barang" className="block text-xs font-semibold text-slate-600">
                            Nama Jenis Barang (Otomatis dari Kode Barang)
                            <input
                                id="input-nama-barang"
                                type="text"
                                value={form.data.nama_barang}
                                onChange={(e) => form.setData('nama_barang', e.target.value)}
                                placeholder="Terisi otomatis setelah Kode Aset dipilih"
                                className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                            />
                        </label>

                        <label htmlFor="input-merk-tipe" className="block text-xs font-semibold text-slate-600">
                            Merek / Type
                            <input
                                id="input-merk-tipe"
                                type="text"
                                value={form.data.merk_tipe}
                                onChange={(e) => form.setData('merk_tipe', e.target.value)}
                                className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                            />
                        </label>

                        <label htmlFor="input-bahan" className="block text-xs font-semibold text-slate-600">
                            Bahan / Material
                            <input
                                id="input-bahan"
                                type="text"
                                value={form.data.bahan}
                                onChange={(e) => form.setData('bahan', e.target.value)}
                                className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                            />
                        </label>

                        <label htmlFor="input-volume" className="block text-xs font-semibold text-slate-600">
                            Volume *
                            <input
                                id="input-volume"
                                type="number"
                                min={1}
                                max={500}
                                value={form.data.volume}
                                onChange={(e) => form.setData('volume', Number(e.target.value))}
                                className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                            />
                            {form.errors.volume && <p className="mt-1 text-xs text-rose-600">{form.errors.volume}</p>}
                        </label>

                        <label htmlFor="input-satuan" className="block text-xs font-semibold text-slate-600">
                            Satuan
                            <input
                                id="input-satuan"
                                type="text"
                                value={form.data.satuan}
                                onChange={(e) => form.setData('satuan', e.target.value)}
                                placeholder="Unit"
                                className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                            />
                        </label>

                        <label htmlFor="input-kondisi" className="block text-xs font-semibold text-slate-600">
                            Keadaan Barang
                            <select
                                id="input-kondisi"
                                value={form.data.kondisi}
                                onChange={(e) => form.setData('kondisi', e.target.value)}
                                className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                            >
                                {Object.entries(kondisiList).map(([kode, label]) => (
                                    <option key={kode} value={kode}>{`${kode} (${label})`}</option>
                                ))}
                            </select>
                        </label>

                        <label htmlFor="input-harga-satuan" className="block text-xs font-semibold text-slate-600">
                            Harga Satuan (Rp)
                            <input
                                id="input-harga-satuan"
                                type="number"
                                min={0}
                                value={form.data.harga_satuan}
                                onChange={(e) => form.setData('harga_satuan', Number(e.target.value))}
                                className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                            />
                        </label>

                        <label htmlFor="input-tanggal-perolehan" className="block text-xs font-semibold text-slate-600">
                            Tanggal Perolehan *
                            <input
                                id="input-tanggal-perolehan"
                                type="date"
                                value={form.data.tanggal_perolehan}
                                onChange={(e) => {
                                    form.setData('tanggal_perolehan', e.target.value);
                                    pratinjauRegister(form.data.kode_barang, e.target.value);
                                }}
                                className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                            />
                            {form.errors.tanggal_perolehan && (
                                <p className="mt-1 text-xs text-rose-600">{form.errors.tanggal_perolehan}</p>
                            )}
                        </label>

                        <label htmlFor="input-asal-perolehan" className="block text-xs font-semibold text-slate-600">
                            Asal Usul Perolehan
                            <input
                                id="input-asal-perolehan"
                                type="text"
                                value={form.data.asal_perolehan}
                                onChange={(e) => form.setData('asal_perolehan', e.target.value)}
                                placeholder="BOS / APBN / Hibah"
                                className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                            />
                        </label>

                        <label htmlFor="input-no-sertifikat" className="block text-xs font-semibold text-slate-600">
                            No Identitas
                            <input
                                id="input-no-sertifikat"
                                type="text"
                                value={form.data.no_sertifikat}
                                onChange={(e) => form.setData('no_sertifikat', e.target.value)}
                                className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                            />
                        </label>

                        <label htmlFor="input-keterangan" className="block text-xs font-semibold text-slate-600 sm:col-span-2">
                            Keterangan
                            <textarea
                                id="input-keterangan"
                                value={form.data.keterangan}
                                onChange={(e) => form.setData('keterangan', e.target.value)}
                                rows={2}
                                className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                            />
                        </label>
                    </div>

                    <div className="flex justify-end gap-2 pt-2">
                        <button type="button" onClick={tutupInput} className="rounded-xl px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100">
                            Batal
                        </button>
                        <button type="submit" disabled={form.processing} className="rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white disabled:opacity-60">
                            Simpan
                        </button>
                    </div>
                </form>
            </Modal>

            <Modal isOpen={modalKondisi} onClose={() => setModalKondisi(false)} title="UBAH KONDISI UNIT">
                <form onSubmit={simpanKondisiMassal} className="space-y-3">
                    <label htmlFor="input-kondisi-semua" className="block text-xs font-semibold text-slate-600">
                        Ubah Semua Sekaligus
                        <select
                            id="input-kondisi-semua"
                            value={kondisiSemua}
                            onChange={(e) => pilihKondisiSemua(e.target.value)}
                            className="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
                        >
                            <option value="">-- Pilih Kondisi --</option>
                            {Object.entries(kondisiList).map(([kode, label]) => (
                                <option key={kode} value={kode}>{label}</option>
                            ))}
                        </select>
                    </label>

                    <div className="max-h-72 space-y-2 overflow-y-auto pr-1">
                        {units.data.filter((u) => terpilih.includes(u.id)).map((unit) => (
                            <div key={unit.id} className="flex items-center gap-3 rounded-xl border border-slate-100 px-3 py-2">
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-xs font-semibold text-slate-700">{unit.nama_barang ?? '-'}</p>
                                    <p className="text-xs text-slate-500">Reg {unit.nomor_register}</p>
                                </div>
                                <select
                                    value={kondisiMassal[unit.id] ?? (unit.kondisi === '-' ? '' : unit.kondisi)}
                                    onChange={(e) => setKondisiMassal((s2) => ({ ...s2, [unit.id]: e.target.value }))}
                                    aria-label={`Kondisi unit ${unit.nomor_register}`}
                                    className="rounded-lg border border-slate-300 px-2 py-1.5 text-xs"
                                >
                                    {(kondisiMassal[unit.id] ?? (unit.kondisi === '-' ? '' : unit.kondisi)) === '' && (
                                        <option value="" disabled>Belum ditentukan</option>
                                    )}
                                    {Object.entries(kondisiList).map(([kode, label]) => (
                                        <option key={kode} value={kode}>{kode}</option>
                                    ))}
                                </select>
                            </div>
                        ))}
                    </div>

                    {galatKondisi && <p className="text-xs text-rose-600">{galatKondisi}</p>}

                    <div className="flex justify-end gap-2 pt-1">
                        <button
                            type="button"
                            onClick={() => setModalKondisi(false)}
                            className="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            className="rounded-xl bg-blue-600 px-4 py-2 text-sm font-bold text-white"
                        >
                            Simpan Kondisi
                        </button>
                    </div>
                </form>
            </Modal>

            <Modal isOpen={modalEdit !== null} onClose={tutupEdit} title="EDIT DATA INVENTARIS">
                <form onSubmit={simpanEdit} className="space-y-3">
                    <label htmlFor="edit-register" className="block text-xs font-semibold text-slate-600">
                        No. Register Saat Ini
                        <input
                            id="edit-register"
                            readOnly
                            value={modalEdit?.nomor_register ?? ''}
                            className="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-sm text-slate-500"
                        />
                    </label>

                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <label htmlFor="edit-kode-barang" className="block text-xs font-semibold text-slate-600">
                            Kode Barang *
                            <input
                                id="edit-kode-barang"
                                type="text"
                                value={edit.data.kode_barang}
                                onChange={(e) => edit.setData('kode_barang', e.target.value)}
                                className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                            />
                            {edit.errors.kode_barang && <p className="mt-1 text-xs text-rose-600">{edit.errors.kode_barang}</p>}
                        </label>
                        <label htmlFor="edit-nama-barang" className="block text-xs font-semibold text-slate-600">
                            Nama Barang *
                            <input
                                id="edit-nama-barang"
                                type="text"
                                value={edit.data.nama_barang}
                                onChange={(e) => edit.setData('nama_barang', e.target.value)}
                                className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                            />
                            {edit.errors.nama_barang && <p className="mt-1 text-xs text-rose-600">{edit.errors.nama_barang}</p>}
                        </label>
                        <label htmlFor="edit-merk-tipe" className="block text-xs font-semibold text-slate-600">
                            Merek / Type
                            <input
                                id="edit-merk-tipe"
                                type="text"
                                value={edit.data.merk_tipe}
                                onChange={(e) => edit.setData('merk_tipe', e.target.value)}
                                className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                            />
                        </label>
                        <label htmlFor="edit-bahan" className="block text-xs font-semibold text-slate-600">
                            Bahan / Material
                            <input
                                id="edit-bahan"
                                type="text"
                                value={edit.data.bahan}
                                onChange={(e) => edit.setData('bahan', e.target.value)}
                                className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                            />
                        </label>
                        <label htmlFor="edit-tanggal-perolehan" className="block text-xs font-semibold text-slate-600">
                            Tanggal Perolehan *
                            <input
                                id="edit-tanggal-perolehan"
                                type="date"
                                value={edit.data.tanggal_perolehan}
                                onChange={(e) => edit.setData('tanggal_perolehan', e.target.value)}
                                className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                            />
                        </label>
                        <label htmlFor="edit-satuan" className="block text-xs font-semibold text-slate-600">
                            Satuan
                            <input
                                id="edit-satuan"
                                type="text"
                                value={edit.data.satuan}
                                onChange={(e) => edit.setData('satuan', e.target.value)}
                                className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                            />
                        </label>
                        <label htmlFor="edit-harga-satuan" className="block text-xs font-semibold text-slate-600">
                            Harga Satuan (Rp)
                            <input
                                id="edit-harga-satuan"
                                type="number"
                                min={0}
                                value={edit.data.harga_satuan}
                                onChange={(e) => edit.setData('harga_satuan', Number(e.target.value))}
                                className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                            />
                        </label>
                        <label htmlFor="edit-kondisi" className="block text-xs font-semibold text-slate-600">
                            Keadaan Barang
                            <select
                                id="edit-kondisi"
                                value={edit.data.kondisi}
                                onChange={(e) => edit.setData('kondisi', e.target.value)}
                                className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                            >
                                {edit.data.kondisi === '' && (
                                    <option value="" disabled>Belum ditentukan</option>
                                )}
                                {Object.entries(kondisiList).map(([kode, label]) => (
                                    <option key={kode} value={kode}>{`${kode} (${label})`}</option>
                                ))}
                            </select>
                        </label>
                        <label htmlFor="edit-asal-perolehan" className="block text-xs font-semibold text-slate-600">
                            Asal Usul Perolehan
                            <input
                                id="edit-asal-perolehan"
                                type="text"
                                value={edit.data.asal_perolehan}
                                onChange={(e) => edit.setData('asal_perolehan', e.target.value)}
                                className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                            />
                        </label>
                        <label htmlFor="edit-no-sertifikat" className="block text-xs font-semibold text-slate-600">
                            No Identitas
                            <input
                                id="edit-no-sertifikat"
                                type="text"
                                value={edit.data.no_sertifikat}
                                onChange={(e) => edit.setData('no_sertifikat', e.target.value)}
                                className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                            />
                        </label>
                    </div>

                    <p className="rounded-xl bg-slate-50 px-3 py-2 text-xs italic text-slate-500">
                        Volume tidak bisa diubah per unit. Tambah atau hapus unit untuk mengubah jumlah.
                    </p>

                    <div className="flex justify-end gap-2 pt-2">
                        <button type="button" onClick={tutupEdit} className="rounded-xl px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100">
                            Tutup
                        </button>
                        <button type="submit" disabled={edit.processing} className="rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white disabled:opacity-60">
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </Modal>

            <Modal
                isOpen={copySource !== null}
                onClose={() => { if (!copyProcessing) { setCopySource(null); setCopyTargets([]); } }}
                title="Salin Data ke Unit Lain"
            >
                <div className="space-y-4">
                    <div className="rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-700">
                        <p className="font-semibold">Sumber: {copySource?.nama_barang ?? '-'}</p>
                        <p className="text-xs text-slate-500">Reg {copySource?.nomor_register}</p>
                    </div>

                    <div>
                        <p className="mb-2 text-xs font-semibold text-slate-600">Pilih field yang disalin</p>
                        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            {([
                                ['merk_tipe', 'Merek / Type'],
                                ['bahan', 'Bahan / Material'],
                                ['satuan', 'Satuan'],
                                ['asal_perolehan', 'Asal Perolehan'],
                                ['no_sertifikat', 'No Identitas'],
                                ['keterangan', 'Keterangan'],
                                ['kondisi', 'Keadaan/Kondisi'],
                            ] as const).map(([key, label]) => (
                                <label key={key} className="flex items-center gap-2 text-xs text-slate-700">
                                    <input
                                        type="checkbox"
                                        checked={copyFields[key]}
                                        onChange={(e) => setCopyFields((prev) => ({ ...prev, [key]: e.target.checked }))}
                                        className="rounded border-slate-300"
                                    />
                                    {label}
                                </label>
                            ))}
                        </div>
                    </div>

                    <div>
                        <div className="mb-2 flex items-center justify-between">
                            <p className="text-xs font-semibold text-slate-600">Pilih unit tujuan (halaman ini)</p>
                            <div className="flex gap-2">
                                <button
                                    type="button"
                                    onClick={() => setCopyTargets(units.data.filter((u) => u.id !== copySource?.id).map((u) => u.id))}
                                    className="text-xs font-bold text-blue-600 hover:underline"
                                >
                                    Pilih semua
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setCopyTargets([])}
                                    className="text-xs font-bold text-slate-600 hover:underline"
                                >
                                    Kosongkan
                                </button>
                            </div>
                        </div>
                        <div className="max-h-56 space-y-2 overflow-y-auto pr-1">
                            {units.data
                                .filter((u) => u.id !== copySource?.id)
                                .map((unit) => (
                                    <label key={unit.id} className="flex items-center gap-3 rounded-xl border border-slate-100 px-3 py-2">
                                        <input
                                            type="checkbox"
                                            checked={copyTargets.includes(unit.id)}
                                            onChange={(e) => {
                                                setCopyTargets((prev) =>
                                                    e.target.checked
                                                        ? [...prev, unit.id]
                                                        : prev.filter((id) => id !== unit.id),
                                                );
                                            }}
                                            className="rounded border-slate-300"
                                        />
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-xs font-semibold text-slate-700">{unit.nama_barang ?? '-'}</p>
                                            <p className="text-xs text-slate-500">Reg {unit.nomor_register}</p>
                                        </div>
                                    </label>
                                ))}
                            {units.data.filter((u) => u.id !== copySource?.id).length === 0 && (
                                <p className="text-xs text-slate-500">Tidak ada unit lain di halaman ini.</p>
                            )}
                        </div>
                    </div>

                    <div className="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            onClick={() => { if (!copyProcessing) { setCopySource(null); setCopyTargets([]); } }}
                            className="rounded-xl px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100"
                            disabled={copyProcessing}
                        >
                            Batal
                        </button>
                        <button
                            type="button"
                            onClick={simpanCopy}
                            disabled={copyProcessing || copyTargets.length === 0 || Object.values(copyFields).every((v) => !v)}
                            className="rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white disabled:opacity-60"
                        >
                            {copyProcessing ? 'Menyalin...' : `Salin ke ${copyTargets.length} unit`}
                        </button>
                    </div>
                </div>
            </Modal>

            <ConfirmDialog
                isOpen={hapus !== null}
                title="Hapus Data?"
                message={`Data barang ${hapus?.nama_barang ?? ''} akan dihapus permanen dan tidak dapat dikembalikan.`}
                confirmText="Ya, Hapus"
                onConfirm={konfirmasiHapus}
                onClose={() => setHapus(null)}
            />

            <ConfirmDialog
                isOpen={hapusSemua}
                title="Hapus SEMUA Data?"
                message="Seluruh data barang dan detail kondisi sekolah ini akan dihapus PERMANEN dan tidak dapat dikembalikan."
                confirmText="Ya, Hapus Semua"
                onConfirm={konfirmasiHapusSemua}
                onClose={() => setHapusSemua(false)}
            />
        </AppLayout>
    );
}
