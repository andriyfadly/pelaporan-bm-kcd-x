import { Head, Link, router, useForm } from '@inertiajs/react';
import React, { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import EmptyState from '@/Components/EmptyState';
import { formatRupiah } from '@/Utils/format';
import { ArrowLeft, FileText, History, QrCode, Tag, Upload } from 'lucide-react';

interface Unit {
    id: string;
    nomor_register: string;
    kode_barang: string;
    nama_barang: string | null;
    jenis_aset: string | null;
    merk_tipe: string | null;
    no_sertifikat: string | null;
    satuan: string | null;
    tanggal_perolehan: string | null;
    harga_satuan: string;
    nilai_perolehan_unit: string;
    kondisi: string;
    status: string;
    keterangan: string | null;
    ruang_id: string | null;
    pegawai_id: string | null;
    realisasi?: { no_spk: string | null; ba_no: string | null; ba_tgl: string | null } | null;
}

interface MutasiItem {
    id: string;
    jenis: string;
    tanggal: string;
    asal_kondisi: string | null;
    tujuan_kondisi: string | null;
    keterangan: string | null;
    asal_ruang?: { nama_ruang: string } | null;
    tujuan_ruang?: { nama_ruang: string } | null;
    asal_pegawai?: { nama_lengkap: string } | null;
    tujuan_pegawai?: { nama_lengkap: string } | null;
    user?: { name: string } | null;
}

interface Props {
    unit: Unit;
    mutasi: MutasiItem[];
    ruangList: { id: string; nama_ruang: string }[];
    pegawaiList: { id: string; nama_lengkap: string }[];
    kondisiList: Record<string, string>;
}

const LABEL_JENIS: Record<string, string> = {
    penempatan: 'Penempatan',
    mutasi: 'Mutasi',
    pengembalian: 'Pengembalian',
    koreksi: 'Koreksi',
};

export default function Show({ unit, mutasi, ruangList, pegawaiList, kondisiList }: Props) {
    const [editing, setEditing] = useState(false);
    const form = useForm({
        kondisi: unit.kondisi,
        keterangan: unit.keterangan ?? '',
        ruang_id: unit.ruang_id ?? '',
        pegawai_id: unit.pegawai_id ?? '',
    });

    const [berkas, setBerkas] = useState<File | null>(null);
    const [unggah, setUnggah] = useState(false);
    const [errorFoto, setErrorFoto] = useState<string | null>(null);
    // Tanggal dokumen menentukan pejabat yang di-snapshot, jadi harus bisa
    // diisi mundur — bukan selalu hari ini.
    const [tanggalDokumen, setTanggalDokumen] = useState(() => new Date().toISOString().slice(0, 10));

    const kirimFoto = (e: React.FormEvent) => {
        e.preventDefault();
        if (!berkas) {
            return;
        }

        setUnggah(true);
        setErrorFoto(null);
        const data = new FormData();
        data.append('foto', berkas);

        router.post(`/pencatatan-bm/inventaris/${unit.id}/foto`, data, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => setBerkas(null),
            onError: (bag) => setErrorFoto(bag.foto ?? 'Berkas ditolak.'),
            onFinish: () => setUnggah(false),
        });
    };

    const buatBast = () => {
        router.post(`/pencatatan-bm/dokumen/bast/${unit.id}`, { tanggal: tanggalDokumen }, { preserveScroll: true });
    };

    const buatFaktaIntegritas = () => {
        if (!unit.pegawai_id) {
            return;
        }

        router.post(
            `/pencatatan-bm/dokumen/fakta-integritas/${unit.pegawai_id}`,
            { tanggal: tanggalDokumen },
            { preserveScroll: true },
        );
    };

    const simpan = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(`/pencatatan-bm/inventaris/${unit.id}/kondisi`, {
            preserveScroll: true,
            onSuccess: () => setEditing(false),
        });
    };

    return (
        <AppLayout>
            <Head title={`Unit ${unit.nomor_register}`} />

            <div className="space-y-5">
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <Link
                            href="/pencatatan-bm/inventaris"
                            className="rounded-lg p-2 text-slate-500 hover:bg-slate-100"
                            aria-label="Kembali ke daftar inventaris"
                        >
                            <ArrowLeft className="w-4 h-4" />
                        </Link>
                        <div>
                            <h1 className="text-xl font-bold text-slate-800">
                                {unit.nama_barang ?? 'Unit Inventaris'} · {unit.nomor_register}
                            </h1>
                            <p className="font-mono text-xs text-slate-500">{unit.kode_barang}</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={() => setEditing((prev) => !prev)}
                        className="rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700"
                    >
                        {editing ? 'Batal' : 'Ubah Kondisi / Penempatan'}
                    </button>
                </header>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <section className="rounded-2xl border border-slate-200 bg-white p-5 lg:col-span-2">
                        <h2 className="mb-3 font-bold text-slate-800">Data Unit</h2>
                        <dl className="grid grid-cols-2 gap-3 text-sm">
                            <Baris label="Merk / Tipe" nilai={unit.merk_tipe} />
                            <Baris label="Jenis Aset" nilai={unit.jenis_aset} />
                            <Baris label="No. Sertifikat" nilai={unit.no_sertifikat} />
                            <Baris label="Satuan" nilai={unit.satuan} />
                            <Baris label="Tanggal Perolehan" nilai={unit.tanggal_perolehan} />
                            <Baris label="Nilai Perolehan" nilai={formatRupiah(unit.nilai_perolehan_unit)} />
                            <Baris label="No. SPK" nilai={unit.realisasi?.no_spk} />
                            <Baris label="No. BA" nilai={unit.realisasi?.ba_no} />
                        </dl>
                        {unit.keterangan && <p className="mt-4 text-xs text-slate-500">{unit.keterangan}</p>}
                    </section>

                    {editing && (
                        <section className="rounded-2xl border border-slate-200 bg-white p-5">
                            <h2 className="mb-3 font-bold text-slate-800">Perubahan</h2>
                            <form onSubmit={simpan} className="space-y-3">
                                <label className="block text-xs font-semibold text-slate-600">
                                    Kondisi
                                    <select
                                        value={form.data.kondisi}
                                        onChange={(e) => form.setData('kondisi', e.target.value)}
                                        className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                                    >
                                        {Object.entries(kondisiList).map(([kode, label]) => (
                                            <option key={kode} value={kode}>
                                                {label}
                                            </option>
                                        ))}
                                    </select>
                                    {form.errors.kondisi && <p className="mt-1 text-xs text-rose-600">{form.errors.kondisi}</p>}
                                </label>

                                <label className="block text-xs font-semibold text-slate-600">
                                    Ruang
                                    <select
                                        value={form.data.ruang_id}
                                        onChange={(e) => form.setData('ruang_id', e.target.value)}
                                        className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                                    >
                                        <option value="">-</option>
                                        {ruangList.map((ruang) => (
                                            <option key={ruang.id} value={ruang.id}>
                                                {ruang.nama_ruang}
                                            </option>
                                        ))}
                                    </select>
                                </label>

                                <label className="block text-xs font-semibold text-slate-600">
                                    Penanggung Jawab
                                    <select
                                        value={form.data.pegawai_id}
                                        onChange={(e) => form.setData('pegawai_id', e.target.value)}
                                        className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                                    >
                                        <option value="">-</option>
                                        {pegawaiList.map((pegawai) => (
                                            <option key={pegawai.id} value={pegawai.id}>
                                                {pegawai.nama_lengkap}
                                            </option>
                                        ))}
                                    </select>
                                </label>

                                <label className="block text-xs font-semibold text-slate-600">
                                    Keterangan
                                    <textarea
                                        value={form.data.keterangan}
                                        onChange={(e) => form.setData('keterangan', e.target.value)}
                                        rows={2}
                                        className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                                    />
                                </label>

                                <button
                                    type="submit"
                                    disabled={form.processing}
                                    className="w-full rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700 disabled:opacity-60"
                                >
                                    Simpan
                                </button>
                            </form>
                        </section>
                    )}
                </div>

                <section className="rounded-2xl border border-slate-200 bg-white p-5">
                    <h2 className="mb-1 font-bold text-slate-800">Dokumen & Label</h2>
                    <p className="mb-3 text-xs text-slate-500">
                        Penandatangan diambil dari riwayat penugasan yang berlaku pada tanggal dokumen — isi tanggal lampau bila
                        dokumennya mundur.
                    </p>
                    <label className="mb-3 block w-44 text-xs font-semibold text-slate-600">
                        Tanggal dokumen
                        <input
                            type="date"
                            value={tanggalDokumen}
                            onChange={(e) => setTanggalDokumen(e.target.value)}
                            className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                        />
                    </label>
                    <div className="flex flex-wrap items-center gap-2">
                        <a
                            href={`/pencatatan-bm/label/${unit.id}/pdf`}
                            className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50"
                        >
                            <Tag className="w-3.5 h-3.5" /> Label PDF
                        </a>
                        <a
                            href={`/pencatatan-bm/label/${unit.id}/qr`}
                            target="_blank"
                            rel="noreferrer"
                            className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50"
                        >
                            <QrCode className="w-3.5 h-3.5" /> QR
                        </a>
                        <button
                            type="button"
                            onClick={buatBast}
                            className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50"
                        >
                            <FileText className="w-3.5 h-3.5" /> Buat BAST
                        </button>
                        <button
                            type="button"
                            onClick={buatFaktaIntegritas}
                            disabled={!unit.pegawai_id}
                            title={unit.pegawai_id ? undefined : 'Tetapkan pegawai pada unit ini dulu'}
                            className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                        >
                            <FileText className="w-3.5 h-3.5" /> Fakta Integritas
                        </button>
                    </div>

                    <form onSubmit={kirimFoto} className="mt-4 flex flex-wrap items-center gap-2">
                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            onChange={(e) => setBerkas(e.target.files?.[0] ?? null)}
                            aria-label="Pilih foto unit"
                            className="text-xs text-slate-600"
                        />
                        <button
                            type="submit"
                            disabled={!berkas || unggah}
                            className="inline-flex items-center gap-1.5 rounded-xl bg-slate-800 px-3 py-2 text-xs font-bold text-white hover:bg-slate-900 disabled:opacity-50"
                        >
                            <Upload className="w-3.5 h-3.5" /> Unggah Foto
                        </button>
                        {errorFoto && <p className="text-xs text-rose-600">{errorFoto}</p>}
                    </form>
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white">
                    <div className="flex items-center gap-2 border-b border-slate-100 px-5 py-4">
                        <History className="w-4 h-4 text-slate-400" />
                        <h2 className="font-bold text-slate-800">Riwayat Mutasi</h2>
                    </div>
                    {mutasi.length === 0 ? (
                        <EmptyState title="Belum ada mutasi" description="Unit ini belum pernah dipindah atau dikembalikan." />
                    ) : (
                        <ul className="divide-y divide-slate-50">
                            {mutasi.map((item) => (
                                <li key={item.id} className="px-5 py-3 text-sm">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <span className="font-semibold text-slate-700">{LABEL_JENIS[item.jenis] ?? item.jenis}</span>
                                        <span className="text-xs text-slate-400">{item.tanggal}</span>
                                    </div>
                                    <p className="mt-1 text-xs text-slate-500">
                                        {item.asal_ruang?.nama_ruang ?? '-'} → {item.tujuan_ruang?.nama_ruang ?? '-'} ·{' '}
                                        {item.asal_pegawai?.nama_lengkap ?? '-'} → {item.tujuan_pegawai?.nama_lengkap ?? '-'} ·{' '}
                                        {item.asal_kondisi ?? '-'} → {item.tujuan_kondisi ?? '-'}
                                    </p>
                                    {item.keterangan && <p className="text-xs text-slate-400">{item.keterangan}</p>}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}

function Baris({ label, nilai }: { label: string; nilai: string | null | undefined }) {
    return (
        <div>
            <dt className="text-xs text-slate-400">{label}</dt>
            <dd className="font-semibold text-slate-700">{nilai || '-'}</dd>
        </div>
    );
}
