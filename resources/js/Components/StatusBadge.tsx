interface StatusBadgeProps {
    status: string | null | undefined;
    className?: string;
}

const BADGE_STYLES: Record<string, { bg: string; text: string; label: string }> = {
    disetujui: { bg: 'bg-emerald-50 border-emerald-200', text: 'text-emerald-700', label: 'Disetujui' },
    selesai: { bg: 'bg-emerald-50 border-emerald-200', text: 'text-emerald-700', label: 'Selesai' },
    menunggu_approval: { bg: 'bg-amber-50 border-amber-200', text: 'text-amber-700', label: 'Menunggu Approval' },
    draft: { bg: 'bg-slate-100 border-slate-200', text: 'text-slate-600', label: 'Draft' },
    belum: { bg: 'bg-rose-50 border-rose-200', text: 'text-rose-700', label: 'Belum Selesai' },
};

export default function StatusBadge({ status, className = '' }: StatusBadgeProps) {
    const key = (status || 'draft').toLowerCase();
    const config = BADGE_STYLES[key] || {
        bg: 'bg-slate-100 border-slate-200',
        text: 'text-slate-600',
        label: status || 'Draft',
    };

    return (
        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border ${config.bg} ${config.text} ${className}`}>
            {config.label}
        </span>
    );
}
