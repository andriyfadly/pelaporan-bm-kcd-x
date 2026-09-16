import { Head, router } from '@inertiajs/react';
import React, { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Pagination, { PaginationLink } from '@/Components/Pagination';
import EmptyState from '@/Components/EmptyState';
import SearchInput from '@/Components/SearchInput';
import { ScrollText, Filter } from 'lucide-react';

interface Actor {
    id: string;
    username?: string;
    name?: string;
    sekolah_id?: string | null;
}

interface LogItem {
    id: number;
    log_name: string;
    description: string;
    event: string | null;
    subject_type: string | null;
    subject_id: string | null;
    causer: Actor | null;
    properties: Record<string, unknown> | null;
    attribute_changes: { attributes?: Record<string, unknown>; old?: Record<string, unknown> } | null;
    created_at: string;
}

interface Paginated {
    data: LogItem[];
    links: PaginationLink[];
    total: number;
}

interface Filters {
    event: string;
    subject_type: string;
    q: string;
    sekolah_id: string;
    dari: string;
    sampai: string;
}

interface Props {
    items: Paginated;
    filters: Filters;
    events: string[];
    subjectTypes: string[];
    sekolahs: { id: string; nama_sekolah: string }[];
}

function ringkasan(log: LogItem): string {
    const props = log.properties as Record<string, unknown> | null;
    if (props && typeof props.ringkasan === 'string' && props.ringkasan !== '') {
        return props.ringkasan;
    }
    return log.description;
}

function namaAktor(log: LogItem): string {
    if (!log.causer) return 'Sistem';
    return log.causer.username || log.causer.name || 'Sistem';
}

export default function Index({ items, filters, events, subjectTypes, sekolahs }: Props) {
    const [form, setForm] = useState<Filters>(filters);

    const terapkan = (patch: Partial<Filters>) => {
        const next = { ...form, ...patch };
        setForm(next);
        router.get('/admin/log-aktivitas', next, { preserveState: true, replace: true });
    };

    return (
        <AppLayout title="Log Aktivitas">
            <Head title="Log Aktivitas" />

            <div className="bg-white rounded-xl border border-slate-200 p-4 mb-4">
                <div className="flex items-center gap-2 mb-3">
                    <Filter className="w-4 h-4 text-slate-400" />
                    <span className="text-sm font-bold text-slate-700">Filter</span>
                </div>
                <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            terapkan({});
                        }}
                    >
                        <SearchInput
                            value={form.q}
                            onChange={(v) => setForm({ ...form, q: v })}
                            placeholder="Cari ringkasan / deskripsi... (Enter)"
                        />
                    </form>
                    <select
                        value={form.event}
                        onChange={(e) => terapkan({ event: e.target.value })}
                        className="px-3 py-2 text-sm border border-slate-200 rounded-lg bg-white"
                    >
                        <option value="">Semua aksi</option>
                        {events.map((ev) => (
                            <option key={ev} value={ev}>{ev}</option>
                        ))}
                    </select>
                    <select
                        value={form.subject_type}
                        onChange={(e) => terapkan({ subject_type: e.target.value })}
                        className="px-3 py-2 text-sm border border-slate-200 rounded-lg bg-white"
                    >
                        <option value="">Semua entitas</option>
                        {subjectTypes.map((t) => (
                            <option key={t} value={t}>{t.split('\\').pop()}</option>
                        ))}
                    </select>
                    <select
                        value={form.sekolah_id}
                        onChange={(e) => terapkan({ sekolah_id: e.target.value })}
                        className="px-3 py-2 text-sm border border-slate-200 rounded-lg bg-white"
                    >
                        <option value="">Semua sekolah</option>
                        {sekolahs.map((s) => (
                            <option key={s.id} value={s.id}>{s.nama_sekolah}</option>
                        ))}
                    </select>
                    <input
                        type="date"
                        value={form.dari}
                        onChange={(e) => terapkan({ dari: e.target.value })}
                        className="px-3 py-2 text-sm border border-slate-200 rounded-lg"
                    />
                    <input
                        type="date"
                        value={form.sampai}
                        onChange={(e) => terapkan({ sampai: e.target.value })}
                        className="px-3 py-2 text-sm border border-slate-200 rounded-lg"
                    />
                </div>
            </div>

            <div className="bg-white rounded-xl border border-slate-200 overflow-hidden">
                {items.data.length === 0 ? (
                    <EmptyState
                        icon={<ScrollText className="w-10 h-10 text-slate-300" />}
                        title="Belum ada log aktivitas"
                        description="Aktivitas sistem akan tercatat di sini."
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="bg-slate-50 text-left text-xs uppercase text-slate-500">
                                    <th className="px-4 py-3">Waktu</th>
                                    <th className="px-4 py-3">Aktor</th>
                                    <th className="px-4 py-3">Aksi</th>
                                    <th className="px-4 py-3">Ringkasan</th>
                                    <th className="px-4 py-3">Detail</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {items.data.map((log) => (
                                    <tr key={log.id} className="hover:bg-slate-50/50 align-top">
                                        <td className="px-4 py-3 whitespace-nowrap text-xs text-slate-500">
                                            {new Date(log.created_at).toLocaleString('id-ID')}
                                        </td>
                                        <td className="px-4 py-3 font-semibold">{namaAktor(log)}</td>
                                        <td className="px-4 py-3">
                                            <span className="inline-block px-2 py-0.5 text-xs font-bold rounded-md bg-blue-50 text-blue-700">
                                                {log.event || log.description}
                                            </span>
                                            {log.subject_type && (
                                                <div className="text-[11px] text-slate-400 mt-1">
                                                    {log.subject_type.split('\\').pop()}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">{ringkasan(log)}</td>
                                        <td className="px-4 py-3">
                                            {log.attribute_changes && (log.attribute_changes.attributes || log.attribute_changes.old) ? (
                                                <details className="text-xs">
                                                    <summary className="cursor-pointer text-blue-600 font-semibold">
                                                        Lihat perubahan
                                                    </summary>
                                                    <pre className="mt-2 p-2 bg-slate-50 rounded-lg overflow-x-auto max-w-md text-[11px]">
                                                        {JSON.stringify(log.attribute_changes, null, 2)}
                                                    </pre>
                                                </details>
                                            ) : (
                                                <span className="text-slate-300">-</span>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                <div className="p-4">
                    <Pagination links={items.links} total={items.total} />
                </div>
            </div>
        </AppLayout>
    );
}
