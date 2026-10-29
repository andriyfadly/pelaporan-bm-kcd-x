import { useEffect, useId, useRef, type ReactNode } from 'react';
import { X } from 'lucide-react';

interface ModalProps {
    isOpen: boolean;
    onClose: () => void;
    title: string;
    children: ReactNode;
    maxWidth?: 'sm' | 'md' | 'lg' | 'xl' | '2xl' | '3xl';
}

const MAX_WIDTH_CLASSES: Record<string, string> = {
    sm: 'max-w-sm',
    md: 'max-w-md',
    lg: 'max-w-lg',
    xl: 'max-w-xl',
    '2xl': 'max-w-2xl',
    '3xl': 'max-w-3xl',
};

export default function Modal({ isOpen, onClose, title, children, maxWidth = 'lg' }: ModalProps) {
    const dialogRef = useRef<HTMLDivElement>(null);
    const sebelumnyaFokus = useRef<HTMLElement | null>(null);
    // Satu halaman bisa merender beberapa modal sekaligus; id judul harus unik
    // agar `aria-labelledby` tidak menunjuk judul modal yang salah.
    const judulId = useId();

    useEffect(() => {
        if (!isOpen) {
            return;
        }

        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'Escape') {
                onClose();

                return;
            }

            // Jebak fokus di dalam dialog: tanpa ini Tab bisa masuk ke konten
            // di belakang backdrop yang tidak terlihat.
            if (e.key !== 'Tab' || !dialogRef.current) {
                return;
            }

            const fokusable = dialogRef.current.querySelectorAll<HTMLElement>(
                'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
            );

            if (fokusable.length === 0) {
                return;
            }

            const pertama = fokusable[0];
            const terakhir = fokusable[fokusable.length - 1];

            if (e.shiftKey && document.activeElement === pertama) {
                e.preventDefault();
                terakhir.focus();
            } else if (!e.shiftKey && document.activeElement === terakhir) {
                e.preventDefault();
                pertama.focus();
            }
        };

        sebelumnyaFokus.current = document.activeElement as HTMLElement | null;
        document.addEventListener('keydown', handleKeyDown);
        document.body.style.overflow = 'hidden';

        // Fokus ke elemen pertama supaya pembaca layar langsung membacakan
        // judul, bukan tetap di tombol pemicu di belakang backdrop.
        const fokusableAwal = dialogRef.current?.querySelector<HTMLElement>(
            'input:not([type="hidden"]), select, textarea, button',
        );
        fokusableAwal?.focus();

        return () => {
            document.removeEventListener('keydown', handleKeyDown);
            document.body.style.overflow = '';
            sebelumnyaFokus.current?.focus();
        };
    }, [isOpen, onClose]);

    if (!isOpen) {
        return null;
    }

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 animate-in fade-in duration-150"
            onClick={(e) => {
                if (e.target === e.currentTarget) {
                    onClose();
                }
            }}
        >
            <div
                ref={dialogRef}
                role="dialog"
                aria-modal="true"
                aria-labelledby={judulId}
                className={`bg-white rounded-2xl shadow-xl w-full ${MAX_WIDTH_CLASSES[maxWidth] || 'max-w-lg'} max-h-[90vh] flex flex-col overflow-hidden`}
            >
                <div className="flex items-center justify-between px-6 py-4 border-b border-slate-100">
                    <h3 id={judulId} className="font-bold text-slate-800">
                        {title}
                    </h3>
                    <button
                        type="button"
                        onClick={onClose}
                        aria-label="Tutup dialog"
                        className="p-1 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition"
                    >
                        <X className="w-5 h-5" />
                    </button>
                </div>
                <div className="p-6 overflow-y-auto">{children}</div>
            </div>
        </div>
    );
}
