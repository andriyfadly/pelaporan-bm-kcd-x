import { Head } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    Clock,
    Home,
    RefreshCw,
    Search,
    Server,
    ShieldAlert,
    Wrench,
    type LucideIcon,
} from 'lucide-react';

interface ErrorPageProps {
    status: number;
}

interface ErrorMeta {
    icon: LucideIcon;
    title: string;
    description: string;
    action: 'back' | 'reload' | 'dashboard' | 'login';
    actionLabel: string;
}

function metaFor(status: number): ErrorMeta {
    switch (status) {
        case 401:
            return {
                icon: ShieldAlert,
                title: 'Belum Masuk',
                description: 'Silakan masuk dulu untuk membuka halaman ini.',
                action: 'login',
                actionLabel: 'Ke Halaman Login',
            };
        case 403:
            return {
                icon: ShieldAlert,
                title: 'Akses Ditolak',
                description: 'Akun Anda tidak memiliki izin untuk membuka halaman ini.',
                action: 'dashboard',
                actionLabel: 'Kembali ke Dashboard',
            };
        case 404:
            return {
                icon: Search,
                title: 'Halaman Tidak Ditemukan',
                description: 'Alamat yang Anda tuju tidak ada atau sudah dipindahkan.',
                action: 'back',
                actionLabel: 'Kembali',
            };
        case 419:
            return {
                icon: RefreshCw,
                title: 'Sesi Kedaluwarsa',
                description: 'Sesi Anda habis. Muat ulang halaman lalu ulangi lagi.',
                action: 'reload',
                actionLabel: 'Muat Ulang',
            };
        case 429:
            return {
                icon: Clock,
                title: 'Terlalu Banyak Permintaan',
                description: 'Anda mengirim terlalu banyak permintaan. Tunggu sebentar lalu coba lagi.',
                action: 'back',
                actionLabel: 'Kembali',
            };
        case 503:
            return {
                icon: Wrench,
                title: 'Sedang Pemeliharaan',
                description: 'Sistem sedang diperbarui. Silakan kembali beberapa saat lagi.',
                action: 'reload',
                actionLabel: 'Coba Lagi',
            };
        case 500:
        case 502:
        case 504:
            return {
                icon: Server,
                title: 'Gangguan Server',
                description: 'Terjadi kesalahan di server. Tim kami sudah mencatatnya, silakan coba lagi.',
                action: 'dashboard',
                actionLabel: 'Kembali ke Dashboard',
            };
        default:
            return {
                icon: AlertTriangle,
                title: 'Terjadi Kesalahan',
                description: 'Sesuatu yang tak terduga terjadi. Silakan coba lagi.',
                action: 'back',
                actionLabel: 'Kembali',
            };
    }
}

export default function Error({ status }: ErrorPageProps) {
    const meta = metaFor(status);
    const Icon = meta.icon;

    const go = () => {
        if (meta.action === 'reload') {
            window.location.reload();
            return;
        }
        if (meta.action === 'login') {
            window.location.href = '/login';
            return;
        }
        if (meta.action === 'dashboard') {
            window.location.href = '/dashboard';
            return;
        }
        window.history.back();
    };

    return (
        <div className="min-h-screen flex items-center justify-center bg-slate-50 p-6">
            <Head title={`${status} | ${meta.title}`} />
            <div className="w-full max-w-md bg-white rounded-2xl border border-slate-200 shadow-sm p-10 text-center">
                <div className="mx-auto mb-4 w-14 h-14 rounded-2xl bg-blue-50 text-[#2563eb] flex items-center justify-center">
                    <Icon className="w-7 h-7" />
                </div>
                <p className="font-black text-5xl text-slate-900 tracking-tight">{status}</p>
                <h1 className="mt-2 font-bold text-base text-slate-800">{meta.title}</h1>
                <p className="mt-1 text-sm text-slate-500">{meta.description}</p>
                <div className="mt-6 flex items-center justify-center gap-2">
                    <button
                        type="button"
                        onClick={go}
                        className="inline-flex items-center gap-2 px-4 py-2 text-xs font-bold text-white bg-[#2563eb] hover:bg-blue-700 rounded-xl transition"
                    >
                        {meta.action === 'back' ? <ArrowLeft className="w-4 h-4" /> : null}
                        {meta.action === 'dashboard' ? <Home className="w-4 h-4" /> : null}
                        {meta.action === 'reload' ? <RefreshCw className="w-4 h-4" /> : null}
                        {meta.actionLabel}
                    </button>
                </div>
            </div>
        </div>
    );
}
