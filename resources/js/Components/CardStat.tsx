import { type ReactNode } from 'react';

interface CardStatProps {
    label: string;
    value: string | number;
    icon: ReactNode;
    color?: 'blue' | 'emerald' | 'amber' | 'purple' | 'rose';
    subtext?: string;
    className?: string;
}

const COLOR_MAP: Record<string, { bg: string; text: string; border: string }> = {
    blue: { bg: 'bg-blue-50', text: 'text-blue-600', border: 'border-blue-100' },
    emerald: { bg: 'bg-emerald-50', text: 'text-emerald-600', border: 'border-emerald-100' },
    amber: { bg: 'bg-amber-50', text: 'text-amber-600', border: 'border-amber-100' },
    purple: { bg: 'bg-purple-50', text: 'text-purple-600', border: 'border-purple-100' },
    rose: { bg: 'bg-rose-50', text: 'text-rose-600', border: 'border-rose-100' },
};

export default function CardStat({
    label,
    value,
    icon,
    color = 'blue',
    subtext,
    className = '',
}: CardStatProps) {
    const c = COLOR_MAP[color] || COLOR_MAP.blue;
    return (
        <div className={`bg-white rounded-2xl border border-slate-200 p-5 shadow-sm flex flex-col justify-between ${className}`}>
            <div className="flex items-center justify-between mb-3">
                <span className="text-xs font-bold uppercase tracking-wider text-slate-500">{label}</span>
                <div className={`p-2 rounded-xl ${c.bg} ${c.text} ${c.border} border`}>{icon}</div>
            </div>
            <div>
                <h3 className="text-2xl font-bold text-slate-900 tracking-tight">{value}</h3>
                {subtext && <p className="text-xs text-slate-400 mt-1">{subtext}</p>}
            </div>
        </div>
    );
}
