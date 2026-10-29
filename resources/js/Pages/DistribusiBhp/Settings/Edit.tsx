import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

interface BhpSettings {
    format_nomor: string;
    format_nomor_nota_permintaan: string | null;
    format_nomor_surat_permintaan: string | null;
    format_nomor_surat_penyaluran: string | null;
    format_nomor_bast: string | null;
    format_nomor_bap: string | null;
    format_nomor_stok_opname: string | null;
    format_nomor_buku_pengeluaran: string | null;
    format_nomor_buku_penyaluran: string | null;
}

const JENIS_DOKUMEN: { key: keyof BhpSettings; label: string }[] = [
    { key: 'format_nomor', label: 'Default (semua jenis)' },
    { key: 'format_nomor_nota_permintaan', label: 'Nota Permintaan Barang (NPB)' },
    { key: 'format_nomor_surat_permintaan', label: 'Surat Permintaan Barang (SPB)' },
    { key: 'format_nomor_surat_penyaluran', label: 'Surat Perintah Penyaluran (SPPB)' },
    { key: 'format_nomor_bast', label: 'BAST' },
    { key: 'format_nomor_bap', label: 'BAP' },
    { key: 'format_nomor_stok_opname', label: 'Stok Opname (SO)' },
    { key: 'format_nomor_buku_pengeluaran', label: 'Buku Pengeluaran (BPB)' },
    { key: 'format_nomor_buku_penyaluran', label: 'Buku Penyaluran (BP)' },
];

export default function Edit({ settings, sumberDana }: { settings: BhpSettings; sumberDana: string }) {
    const initial: Record<string, string> = { sumber_dana: sumberDana };
    for (const j of JENIS_DOKUMEN) {
        initial[j.key] = settings[j.key] ?? '';
    }

    const form = useForm(initial);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(`/distribusi-bhp/settings?sumber_dana=${sumberDana}`);
    };

    return (
        <AppLayout title={`Settings BHP ${sumberDana}`}>
            <Head title={`Settings BHP ${sumberDana}`} />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-lg font-bold text-slate-900">Settings BHP {sumberDana}</h1>
                    {/* Settings disimpan per sumber dana, sedangkan menu sidebar
                        hanya punya satu tautan: tanpa pemilih ini format BOPD
                        tidak bisa dijangkau dari UI. */}
                    <div className="flex gap-2">
                        {['BOS', 'BOPD'].map((dana) => (
                            <Link
                                key={dana}
                                href={`/distribusi-bhp/settings?sumber_dana=${dana}`}
                                className={`inline-flex min-h-10 items-center px-3 rounded-xl text-sm font-bold border ${
                                    dana === sumberDana
                                        ? 'bg-blue-600 text-white border-blue-600'
                                        : 'bg-white text-slate-600 border-slate-300 hover:bg-slate-50'
                                }`}
                            >
                                {dana}
                            </Link>
                        ))}
                    </div>
                </div>

                <form onSubmit={submit} className="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm space-y-4 max-w-4xl sm:p-6">
                    <input type="hidden" name="sumber_dana" value={sumberDana} />

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {JENIS_DOKUMEN.map(({ key, label }) => (
                            <div key={key}>
                                <label htmlFor={`format-${key}`} className="block text-xs font-bold uppercase text-slate-500 mb-1">{label}</label>
                                <input
                                    id={`format-${key}`}
                                    value={form.data[key]}
                                    onChange={(e) => form.setData(key as any, e.target.value)}
                                    className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm font-mono"
                                    placeholder="Kosongkan untuk memakai format default"
                                />
                                {form.errors[key] && <p className="text-xs text-rose-600 mt-1">{form.errors[key]}</p>}
                            </div>
                        ))}
                    </div>

                    <p className="text-xs text-slate-500">
                        Placeholder: <code>{'{urut:3}'}</code>, <code>{'{kode}'}</code>, <code>{'{sumber_dana}'}</code>, <code>{'{bulan_romawi}'}</code>, <code>{'{bulan}'}</code>, <code>{'{tahun}'}</code>
                        &nbsp;— isi per jenis untuk override format default.
                    </p>

                    <p className="text-xs text-slate-500">
                        Data penandatangan dokumen dikelola di menu <strong>Master Data → Penugasan Pegawai</strong>.
                    </p>

                    <button type="submit" disabled={form.processing} className="min-h-11 w-full px-4 bg-blue-600 text-white rounded-xl text-sm font-bold disabled:opacity-50 sm:w-auto">
                        Simpan Settings {sumberDana}
                    </button>
                </form>
            </div>
        </AppLayout>
    );
}