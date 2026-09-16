import { type ReactNode } from 'react';
import { FolderX } from 'lucide-react';

interface EmptyStateProps {
    title?: string;
    description?: string;
    icon?: ReactNode;
    action?: ReactNode;
    className?: string;
}

export default function EmptyState({
    title = 'Belum Ada Data',
    description = 'Data pada periode atau kriteria ini belum tersedia.',
    icon,
    action,
    className = '',
}: EmptyStateProps) {
    return (
        <div className={`p-12 text-center flex flex-col items-center justify-center ${className}`}>
            <div className="mb-3">{icon || <FolderX className="w-12 h-12 text-slate-300" />}</div>
            <h4 className="font-bold text-slate-700 text-sm">{title}</h4>
            <p className="text-xs text-slate-400 mt-1 max-w-sm">{description}</p>
            {action && <div className="mt-4">{action}</div>}
        </div>
    );
}
