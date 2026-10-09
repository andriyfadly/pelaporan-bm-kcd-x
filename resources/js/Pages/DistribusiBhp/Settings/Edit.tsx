import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

interface BhpSettings {
    format_nomor: string;
}

export default function Edit({ settings, sumberDana }: { settings: BhpSettings; sumberDana: string }) {
    const form = useForm({
        format_nomor: settings.format_nomor,
        sumber_dana: sumberDana,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(`/distribusi-bhp/settings?sumber_dana=${sumberDana}`);
    };

    return (
        <AppLayout title={`Settings BHP ${sumberDana}`}>
            <Head title={`Settings BHP ${sumberDana}`} />

            <div className="space-y-6">
                <h1 className="text-lg font-bold text-slate-900">Settings BHP {sumberDana}</h1>

                <form onSubmit={submit} className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-5 max-w-3xl">
                    <input type="hidden" name="sumber_dana" value={sumberDana} />

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

                    <p className="text-xs text-slate-400">
                        Data penandatangan dokumen dikelola di menu <strong>Pejabat Sekolah</strong>.
                    </p>

                    <button type="submit" disabled={form.processing} className="px-4 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-bold disabled:opacity-50">
                        Simpan Settings {sumberDana}
                    </button>
                </form>
            </div>
        </AppLayout>
    );
}
