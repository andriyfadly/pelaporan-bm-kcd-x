import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

interface BhpSettings {
    format_nomor: string;
    paper_size: string;
    orientation: string;
}

export default function Edit({ settings }: { settings: BhpSettings }) {
    const form = useForm({
        format_nomor: settings.format_nomor,
        paper_size: settings.paper_size || 'a4',
        orientation: settings.orientation || 'portrait',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put('/distribusi-bhp/settings');
    };

    return (
        <AppLayout title="Settings BHP">
            <Head title="Settings BHP" />

            <div className="space-y-6">
                <h1 className="text-lg font-bold text-slate-900">Settings BHP</h1>

                <form onSubmit={submit} className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-5 max-w-3xl">
                    <div>
                        <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Format Nomor Dokumen</label>
                        <input
                            value={form.data.format_nomor}
                            onChange={(e) => form.setData('format_nomor', e.target.value)}
                            className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm font-mono"
                        />
                        <p className="text-xs text-slate-400 mt-1">
                            Placeholder: <code>{'{urut:3}'}</code>, <code>{'{kode}'}</code>, <code>{'{sumber_dana}'}</code>, <code>{'{bulan_romawi}'}</code>, <code>{'{bulan}'}</code>, <code>{'{tahun}'}</code>
                        </p>
                        {form.errors.format_nomor && <p className="text-xs text-rose-600 mt-1">{form.errors.format_nomor}</p>}
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Ukuran Kertas PDF</label>
                            <select
                                value={form.data.paper_size}
                                onChange={(e) => form.setData('paper_size', e.target.value)}
                                className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm"
                            >
                                <option value="a4">A4</option>
                                <option value="letter">Letter</option>
                                <option value="foli/f4">Folio / F4</option>
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs font-bold uppercase text-slate-500 mb-1">Orientasi PDF</label>
                            <select
                                value={form.data.orientation}
                                onChange={(e) => form.setData('orientation', e.target.value)}
                                className="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm"
                            >
                                <option value="portrait">Portrait</option>
                                <option value="landscape">Landscape</option>
                            </select>
                        </div>
                    </div>

                    <p className="text-xs text-slate-400">
                        Data penandatangan dokumen dikelola di menu <strong>Pejabat Sekolah</strong>.
                    </p>

                    <button type="submit" disabled={form.processing} className="px-4 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-bold disabled:opacity-50">
                        Simpan Settings
                    </button>
                </form>
            </div>
        </AppLayout>
    );
}
