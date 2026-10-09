import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Modal from '@/Components/Modal';
import ConfirmDialog from '@/Components/ConfirmDialog';
import CardStat from '@/Components/CardStat';
import SearchInput from '@/Components/SearchInput';
import { formatRupiah } from '@/Utils/format';
import { Package, Plus, Pencil, Trash2, DollarSign, Save, TrendingUp, Upload, Download } from 'lucide-react';

interface HargaItem {
    id: string;
    harga_satuan: number;
}

interface BarangItem {
    id: string;
    kode_barang: string | null;
    nama_barang: string;
    spesifikasi_barang_nusp: string | null;
    satuan: string | null;
    kode_rekening_id: string | null;
    kode_rekening?: { id: string; kode: string; nama: string } | null;
    harga: HargaItem[];
    created_at?: string;
}

interface KodeBarangOption {
    kode: string;
    uraian: string;
}

interface KodeRekeningOption {
    id: string;
    kode: string;
    nama: string;
    kode_barang?: string | null;
    uraian_kode_barang?: string | null;
}

interface Stats {
    total: number;
    stok_terpakai: number;
    bulan_ini: number;
}

const EMPTY_FORM = {
    kode_barang: '',
    nama_barang: '',
    spesifikasi_barang_nusp: '',
    satuan: '',
    kode_rekening_id: '',
    harga_satuan: 0,
};

export default function Index({
    items,
    sumberDana,
    stats,
    kodeBarangOptions,
    kodeRekening,
}: {
    items: BarangItem[];
    sumberDana: string;
    stats: Stats;
    kodeBarangOptions: KodeBarangOption[];
    kodeRekening: KodeRekeningOption[];
}) {
    const prefix = `/distribusi-bhp/${sumberDana.toLowerCase()}`;
    const [search, setSearch] = useState('');
    const [showCreate, setShowCreate] = useState(false);
    const [showImport, setShowImport] = useState(false);
    const [importFile, setImportFile] = useState<File | null>(null);
    const [editTarget, setEditTarget] = useState<BarangItem | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<BarangItem | null>(null);

    const createForm = useForm({ ...EMPTY_FORM, sumber_dana: sumberDana });
    const editForm = useForm({ ...EMPTY_FORM, sumber_dana: sumberDana });
    const importForm = useForm<{ file: File | null; sumber_dana: string }>({ file: null, sumber_dana: sumberDana });

    const filtered = useMemo(() => {
        const q = search.trim().toLowerCase();
        if (!q) return items;
        return items.filter((it) =>
            [it.nama_barang, it.kode_barang, it.kode_rekening?.kode]
                .filter(Boolean)
                .some((v) => String(v).toLowerCase().includes(q)),
        );
    }, [items, search]);

    const uraianFor = (kode: string | null) =>
        kodeBarangOptions.find((o) => o.kode === kode)?.uraian;

    const submitCreate = (e: React.FormEvent) => {
        e.preventDefault();
        createForm.post(`${prefix}/master/barang`, {
            preserveScroll: true,
            onSuccess: () => {
                createForm.reset();
                setShowCreate(false);
            },
        });
    };

    const submitImport = () => {
        if (!importFile) return;
        importForm.transform(() => ({ file: importFile, sumber_dana: sumberDana }));
        importForm.post(`${prefix}/master/barang/import`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setImportFile(null);
                importForm.reset();
                setShowImport(false);
            },
        });
    };

    const openEdit = (item: BarangItem) => {
        setEditTarget(item);
        editForm.setData({
            kode_barang: item.kode_barang ?? '',
            nama_barang: item.nama_barang,
            spesifikasi_barang_nusp: item.spesifikasi_barang_nusp ?? '',
            satuan: item.satuan ?? '',
            kode_rekening_id: item.kode_rekening_id ?? '',
            harga_satuan: 0,
            sumber_dana: sumberDana,
        });
    };

    const submitEdit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editTarget) return;
        editForm.put(`${prefix}/master/barang/${editTarget.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setEditTarget(null);
                editForm.reset();
            },
        });
    };

    const confirmDelete = () => {
        if (!deleteTarget) return;
        router.delete(`${prefix}/master/barang/${deleteTarget.id}`, {
            preserveScroll: true,
            data: { sumber_dana: sumberDana },
        });
    };

    const handleExport = () => {
        if (filtered.length === 0) return;
        const rows = [
            ['KODE BARANG', 'NAMA BARANG', 'SATUAN', 'HARGA SATUAN', 'KODE REKENING'],
            ...filtered.map((it) => [
                it.kode_barang ?? '',
                it.nama_barang,
                it.satuan ?? '',
                it.harga.at(-1)?.harga_satuan ?? '',
                it.kode_rekening?.kode ?? '',
            ]),
        ];
        const csv = rows
            .map((r) => r.map((c) => `"${String(c).replace(/"/g, '""')}"`).join(','))
            .join('\n');
        const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8;' }));
        const a = document.createElement('a');
        a.href = url;
        a.download = `data-barang-${sumberDana.toLowerCase()}.csv`;
        a.click();
        URL.revokeObjectURL(url);
    };

    const fields = (form: ReturnType<typeof useForm>, isEdit: boolean) => (
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div className="sm:col-span-2">
                <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Kode Rekening</label>
                <select
                    value={form.data.kode_rekening_id}
                    onChange={(e) => {
                        const selected = kodeRekening.find((r) => r.id === e.target.value);
                        form.setData({
                            ...form.data,
                            kode_rekening_id: e.target.value,
                            kode_barang: selected?.kode_barang || form.data.kode_barang,
                        });
                    }}
                    className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm bg-white"
                >
                    <option value="">— Pilih Kode Rekening —</option>
                    {kodeRekening.map((r) => (
                        <option key={r.id} value={r.id}>{r.kode} — {r.nama}</option>
                    ))}
                </select>
                {form.errors.kode_rekening_id && <p className="text-xs text-rose-600 mt-1">{form.errors.kode_rekening_id}</p>}
            </div>

            <div>
                <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Kode Barang</label>
                <select
                    value={form.data.kode_barang}
                    onChange={(e) => form.setData('kode_barang', e.target.value)}
                    className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm bg-white"
                >
                    <option value="">— Pilih Kode Barang —</option>
                    {kodeBarangOptions.map((o) => (
                        <option key={o.kode} value={o.kode}>{o.kode} — {o.uraian}</option>
                    ))}
                </select>
                {form.errors.kode_barang && <p className="text-xs text-rose-600 mt-1">{form.errors.kode_barang}</p>}
            </div>

            <div>
                <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Satuan</label>
                <input
                    value={form.data.satuan}
                    onChange={(e) => form.setData('satuan', e.target.value)}
                    placeholder="Unit, Pcs, Kg, Liter, dll"
                    className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm"
                />
                {form.errors.satuan && <p className="text-xs text-rose-600 mt-1">{form.errors.satuan}</p>}
            </div>

            <div className="sm:col-span-2">
                <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Nama Barang</label>
                <input
                    value={form.data.nama_barang}
                    onChange={(e) => form.setData('nama_barang', e.target.value)}
                    placeholder="Masukkan nama barang"
                    className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm"
                />
                {form.errors.nama_barang && <p className="text-xs text-rose-600 mt-1">{form.errors.nama_barang}</p>}
            </div>

            <div className="sm:col-span-2">
                <label className="block text-xs font-bold uppercase text-slate-500 mb-1">
                    NUSP (Nomor Urut Spesifikasi Barang)
                </label>
                <input
                    value={form.data.spesifikasi_barang_nusp}
                    onChange={(e) => form.setData('spesifikasi_barang_nusp', e.target.value)}
                    placeholder="Opsional"
                    className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm"
                />
            </div>

            {!isEdit && (
                <div className="sm:col-span-2">
                    <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Harga Satuan</label>
                    <input
                        type="number"
                        value={form.data.harga_satuan}
                        onChange={(e) => form.setData('harga_satuan', Number(e.target.value))}
                        placeholder="Masukkan harga satuan"
                        className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm"
                    />
                    {form.errors.harga_satuan && <p className="text-xs text-rose-600 mt-1">{form.errors.harga_satuan}</p>}
                    <p className="text-xs text-slate-400 mt-1">Harga referensi; harga aktual bisa berbeda saat penerimaan.</p>
                </div>
            )}
        </div>
    );

    return (
        <AppLayout title="Data Barang BHP">
            <Head title="Data Barang BHP" />

            <div className="space-y-6">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-lg font-bold text-slate-900 flex items-center gap-2">
                            <Package className={`w-5 h-5 ${sumberDana === 'BOPD' ? 'text-emerald-600' : 'text-blue-600'}`} />
                            Data Barang {sumberDana}
                        </h1>
                        <p className="text-sm text-slate-500 mt-1">
                            Kelola data barang dan inventori {sumberDana} sekolah.
                        </p>
                    </div>
                    <div className="flex items-center gap-2 shrink-0">
                        <button
                            type="button"
                            onClick={() => setShowImport(true)}
                            className="inline-flex items-center gap-1.5 px-4 py-2.5 border border-slate-300 text-slate-700 rounded-xl text-sm font-bold hover:bg-slate-50"
                        >
                            <Upload className="w-4 h-4" /> Import
                        </button>
                        <button
                            type="button"
                            onClick={handleExport}
                            disabled={filtered.length === 0}
                            className="inline-flex items-center gap-1.5 px-4 py-2.5 border border-slate-300 text-slate-700 rounded-xl text-sm font-bold hover:bg-slate-50 disabled:opacity-50"
                        >
                            Export
                        </button>
                        <button
                            type="button"
                            onClick={() => setShowCreate(true)}
                            className="inline-flex items-center gap-1.5 px-4 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-bold hover:bg-blue-700 transition"
                        >
                            <Plus className="w-4 h-4" /> Tambah Barang
                        </button>
                    </div>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <CardStat label="Total Barang" value={stats.total.toLocaleString('id-ID')} icon={<Package className="w-5 h-5" />} color="blue" />
                    <CardStat label="Barang Berharga" value={stats.stok_terpakai.toLocaleString('id-ID')} icon={<DollarSign className="w-5 h-5" />} color="amber" />
                    <CardStat label="Bulan Ini" value={stats.bulan_ini.toLocaleString('id-ID')} icon={<TrendingUp className="w-5 h-5" />} color="emerald" />
                </div>

                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div className="p-4 border-b border-slate-100">
                        <SearchInput value={search} onChange={setSearch} placeholder="Cari barang..." className="max-w-xs" />
                    </div>
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 text-slate-500 uppercase text-xs">
                            <tr>
                                <th className="text-left py-3 px-4">Nama Barang</th>
                                <th className="text-left py-3 px-4">Kode Barang</th>
                                <th className="text-left py-3 px-4">Satuan</th>
                                <th className="text-left py-3 px-4">Harga Satuan</th>
                                <th className="text-left py-3 px-4">Kode Rekening</th>
                                <th className="text-right py-3 px-4 w-52">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {filtered.length === 0 ? (
                                <tr><td colSpan={6} className="py-10 text-center text-slate-400 text-xs">Belum ada barang.</td></tr>
                            ) : (
                                filtered.map((item) => {
                                    const harga = item.harga.at(-1)?.harga_satuan;
                                    const priceCount = item.harga.length;
                                    return (
                                        <tr key={item.id} className="hover:bg-slate-50/60">
                                            <td className="py-3 px-4 font-medium text-slate-800">{item.nama_barang}</td>
                                            <td className="py-3 px-4">
                                                {item.kode_barang ? (
                                                    <div className="space-y-1">
                                                        <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-mono font-medium bg-blue-100 text-blue-800">
                                                            {item.kode_barang}
                                                        </span>
                                                        {uraianFor(item.kode_barang) && (
                                                            <div className="text-xs text-slate-500">{uraianFor(item.kode_barang)}</div>
                                                        )}
                                                    </div>
                                                ) : (
                                                    <span className="text-slate-400">-</span>
                                                )}
                                            </td>
                                            <td className="py-3 px-4 text-slate-600">{item.satuan ?? '-'}</td>
                                            <td className="py-3 px-4">
                                                {harga ? (
                                                    <div className="space-y-1">
                                                        <span className="font-medium text-emerald-600 block">{formatRupiah(harga)}</span>
                                                        <span className={`text-xs px-2 py-1 rounded border ${
                                                            priceCount > 1
                                                                ? 'text-blue-600 bg-blue-50 border-blue-200'
                                                                : 'text-emerald-600 bg-emerald-50 border-emerald-200'
                                                        }`}>
                                                            {priceCount} harga
                                                        </span>
                                                    </div>
                                                ) : (
                                                    <span className="text-slate-400">-</span>
                                                )}
                                            </td>
                                            <td className="py-3 px-4">
                                                {item.kode_rekening ? (
                                                    <div className="flex flex-col">
                                                        <span className="font-mono text-sm text-blue-600">{item.kode_rekening.kode}</span>
                                                        <span className="text-xs text-slate-500">{item.kode_rekening.nama}</span>
                                                    </div>
                                                ) : (
                                                    <span className="text-slate-400">-</span>
                                                )}
                                            </td>
                                            <td className="py-3 px-4">
                                                <div className="flex items-center justify-end gap-2">
                                                    <a
                                                        href={`${prefix}/master/barang/${item.id}/kelola-harga`}
                                                        className="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700"
                                                    >
                                                        <DollarSign className="w-3.5 h-3.5" /> Kelola Harga
                                                    </a>
                                                    <button
                                                        type="button"
                                                        onClick={() => openEdit(item)}
                                                        className="inline-flex items-center gap-1 px-2.5 py-1.5 border border-slate-300 rounded-lg text-xs font-bold text-slate-600 hover:bg-slate-50"
                                                    >
                                                        <Pencil className="w-3.5 h-3.5" /> Edit
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => setDeleteTarget(item)}
                                                        className="inline-flex items-center gap-1 px-2.5 py-1.5 border border-rose-200 rounded-lg text-xs font-bold text-rose-600 hover:bg-rose-50"
                                                    >
                                                        <Trash2 className="w-3.5 h-3.5" /> Hapus
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <Modal isOpen={showCreate} onClose={() => setShowCreate(false)} title="Tambah Barang Baru" maxWidth="3xl">
                <form onSubmit={submitCreate} className="space-y-4">
                    {fields(createForm, false)}
                    <div className="flex justify-end gap-2 pt-2">
                        <button type="button" onClick={() => setShowCreate(false)} className="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl">
                            Batal
                        </button>
                        <button type="submit" disabled={createForm.processing} className="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-xl disabled:opacity-50">
                            <Plus className="w-4 h-4" /> Tambah Barang
                        </button>
                    </div>
                </form>
            </Modal>

            <Modal isOpen={editTarget !== null} onClose={() => setEditTarget(null)} title="Edit Barang" maxWidth="3xl">
                <form onSubmit={submitEdit} className="space-y-4">
                    {fields(editForm, true)}
                    <div className="flex justify-end gap-2 pt-2">
                        <button type="button" onClick={() => setEditTarget(null)} className="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl">
                            Batal
                        </button>
                        <button type="submit" disabled={editForm.processing} className="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-xl disabled:opacity-50">
                            <Save className="w-4 h-4" /> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </Modal>

            <Modal isOpen={showImport} onClose={() => setShowImport(false)} title="Import Data Barang" maxWidth="2xl">
                <div className="space-y-5">
                    <div className="flex items-start justify-between gap-4 rounded-2xl border border-blue-200 bg-blue-50/60 p-4">
                        <div>
                            <h4 className="text-sm font-bold text-blue-900">Unduh Template</h4>
                            <p className="text-xs text-blue-700 mt-1">
                                Gunakan template Excel agar format kolom sesuai.
                            </p>
                        </div>
                        <a
                            href={`${prefix}/master/barang/template?sumber_dana=${sumberDana}`}
                            className="inline-flex shrink-0 items-center gap-1.5 px-3 py-2 text-xs font-bold text-blue-700 border border-blue-300 rounded-xl hover:bg-blue-100"
                        >
                            <Download className="w-4 h-4" /> Download
                        </a>
                    </div>

                    <div>
                        <label className="block text-xs font-bold uppercase text-slate-500 mb-1">File Excel (.xlsx/.xls)</label>
                        <input
                            type="file"
                            accept=".xlsx,.xls"
                            onChange={(e) => setImportFile(e.target.files?.[0] ?? null)}
                            className="block w-full text-sm text-slate-600 file:mr-3 file:px-3 file:py-2 file:rounded-xl file:border-0 file:bg-slate-100 file:text-sm file:font-semibold"
                        />
                        {importForm.errors.file && <p className="text-xs text-rose-600 mt-1">{importForm.errors.file}</p>}
                        {importForm.errors.sumber_dana && <p className="text-xs text-rose-600 mt-1">{importForm.errors.sumber_dana}</p>}
                    </div>

                    <div className="rounded-2xl border border-slate-200 bg-slate-50/60 p-4 text-xs text-slate-600 space-y-1">
                        <p className="font-bold text-slate-700">Petunjuk:</p>
                        <ul className="list-disc list-inside space-y-0.5">
                            <li>Urutan kolom: Kode Rekening, Nama Barang, Spesifikasi/NUSP, Satuan, Harga Satuan.</li>
                            <li>Kode Barang & uraian diisi otomatis dari Kode Rekening.</li>
                            <li>Kode Rekening, Nama Barang, Satuan, dan Harga Satuan wajib diisi.</li>
                            <li>Harga Satuan berupa angka tanpa Rp/titik.</li>
                            <li>Nama barang yang sama akan menambah varian harga pada barang yang ada.</li>
                            <li>Import bersifat all-or-nothing: satu baris invalid membatalkan seluruh import.</li>
                            <li>Maksimal 1000 baris per upload, file maksimal 10MB.</li>
                        </ul>
                    </div>

                    <div className="flex justify-end gap-2">
                        <button type="button" onClick={() => setShowImport(false)} className="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl">
                            Batal
                        </button>
                        <button
                            type="button"
                            onClick={submitImport}
                            disabled={!importFile || importForm.processing}
                            className="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl disabled:opacity-50"
                        >
                            <Upload className="w-4 h-4" /> {importForm.processing ? 'Mengimpor...' : 'Import'}
                        </button>
                    </div>
                </div>
            </Modal>

            <ConfirmDialog
                isOpen={deleteTarget !== null}
                onClose={() => setDeleteTarget(null)}
                onConfirm={confirmDelete}
                title="Hapus Barang"
                message={`Hapus barang "${deleteTarget?.nama_barang ?? ''}"? Tindakan ini tidak dapat dibatalkan.`}
                confirmText="Ya, Hapus"
                isDestructive
            />
        </AppLayout>
    );
}
