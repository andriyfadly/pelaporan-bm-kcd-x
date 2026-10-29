import { Link } from '@inertiajs/react';

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginationProps {
    links: PaginationLink[];
    total?: number;
    className?: string;
}

export default function Pagination({ links, total, className = '' }: PaginationProps) {
    if (!links || links.length <= 3) {
        return null;
    }

    return (
        <div className={`flex flex-wrap items-center justify-between gap-2 pt-3 ${className}`}>
            {total !== undefined && (
                <span className="text-xs text-slate-500">
                    Total: <strong>{total.toLocaleString('id-ID')}</strong> baris data
                </span>
            )}
            <div className="flex flex-wrap items-center gap-1">
                {links.map((link, i) => (
                    link.url ? (
                        <Link
                            key={i}
                            href={link.url}
                            className={`inline-flex min-h-9 min-w-9 items-center justify-center px-2.5 text-xs font-semibold rounded border transition ${
                                link.active
                                    ? 'bg-blue-600 text-white border-blue-600'
                                    : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'
                            }`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ) : (
                        <span
                            key={i}
                            className="inline-flex min-h-9 min-w-9 items-center justify-center px-2.5 text-xs text-slate-400 bg-slate-50 rounded border border-slate-100"
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    )
                ))}
            </div>
        </div>
    );
}
