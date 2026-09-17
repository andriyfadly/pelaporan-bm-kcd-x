import { Link, usePage, router } from '@inertiajs/react';
import React, { useState, ReactNode } from 'react';
import ConfirmDialog from '@/Components/ConfirmDialog';
import {
    LayoutDashboard,
    Database,
    BarChart3,
    FileSpreadsheet,
    ClipboardList,
    LogOut,
    Menu,
    ChevronDown,
    CheckCircle2,
    User as UserIcon,
} from 'lucide-react';

interface Props {
    title?: string;
    children: ReactNode;
}

export default function AppLayout({ title = 'Dashboard', children }: Props) {
    const [collapsed, setCollapsed] = useState(false);
    const [mobileOpen, setMobileOpen] = useState(false);
    const [showLogoutConfirm, setShowLogoutConfirm] = useState(false);
    const { auth } = usePage<any>().props;

    const user = auth?.user;
    const sekolah = user?.sekolah;
    const namaSekolahTampil = sekolah?.nama_sekolah || 'Dinas / Administrator';

    const currentPath = window.location.pathname;

    const roles: string[] = user?.roles || [];
    const isAdmin = roles.includes('admin_kcd') || roles.includes('super_admin') || !user?.sekolah_id;
    const isSuperAdmin = roles.includes('super_admin');

    const isMasterActive =
        currentPath.startsWith('/admin/kode-barang') ||
        currentPath.startsWith('/pelaporan-bm/acuan') ||
        currentPath.startsWith('/pelaporan-bm/rekapan') ||
        currentPath.startsWith('/admin/user');

    const isLaporanActive = currentPath.startsWith('/pelaporan-bm/cetak');

    const [openMaster, setOpenMaster] = useState(true);
    const [openLaporan, setOpenLaporan] = useState(true);

    return (
        <div className="min-h-screen bg-[#f8fafc] text-slate-900 font-sans flex">
            {mobileOpen && (
                <div
                    onClick={() => setMobileOpen(false)}
                    className="fixed inset-0 bg-black/40 z-40 lg:hidden"
                />
            )}

            {/* Sidebar */}
            <aside
                className={`fixed top-0 left-0 h-screen bg-white border-r border-slate-200 z-50 flex flex-col transition-all duration-300 ${collapsed ? 'w-20' : 'w-72'
                    } ${mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'}`}
            >
                <div className="pt-8 pb-5 px-5 flex flex-col items-center justify-center text-center border-b border-slate-100">
                    {!collapsed ? (
                        <>
                            <div className="leading-tight">
                                <span className="block font-black text-2xl text-[#1e3a8a] tracking-tight uppercase">
                                    SI DIPTA
                                </span>
                                <span
                                    className="block text-3xl text-[#f59e0b] -mt-1 ml-6 rotate-[-3deg]"
                                    style={{ fontFamily: "'Yellowtail', cursive" }}
                                >
                                    Beu!
                                </span>
                            </div>
                            <span className="text-[8px] text-slate-400 font-bold uppercase tracking-widest mt-1">
                                SISTEM DIGITALISASI PELAPORAN ASET
                            </span>
                        </>
                    ) : (
                        <span className="font-black text-xl text-[#1e3a8a]">SD</span>
                    )}
                </div>

                <div className="p-3 flex-1 overflow-y-auto space-y-1">
                    {isAdmin ? (
                        <>
                            {!collapsed && (
                                <div className="text-[11px] font-bold text-slate-400 uppercase tracking-wider px-3 py-2">
                                    Main Menu
                                </div>
                            )}
                            <Link
                                href="/dashboard"
                                className={`flex items-center gap-3 px-3.5 py-3 rounded-xl text-sm font-semibold transition ${currentPath === '/dashboard'
                                        ? 'bg-[#eff6ff] text-[#2563eb] border-l-4 border-[#2563eb] rounded-l-none'
                                        : 'text-slate-500 hover:bg-blue-50/50 hover:text-[#2563eb]'
                                    }`}
                                title={collapsed ? 'Dashboard' : undefined}
                            >
                                <LayoutDashboard className="w-5 h-5 shrink-0" />
                                {!collapsed && <span>Dashboard</span>}
                            </Link>

                            {/* Master Data Dropdown */}
                            <div>
                                <button
                                    type="button"
                                    onClick={() => setOpenMaster(!openMaster)}
                                    className={`w-full flex items-center justify-between px-3.5 py-3 rounded-xl text-sm font-semibold transition cursor-pointer ${isMasterActive
                                            ? 'text-[#2563eb] bg-blue-50/30'
                                            : 'text-slate-500 hover:bg-blue-50/50 hover:text-[#2563eb]'
                                        }`}
                                    title={collapsed ? 'Master Data' : undefined}
                                >
                                    <div className="flex items-center gap-3">
                                        <Database className="w-5 h-5 shrink-0" />
                                        {!collapsed && <span>Master Data</span>}
                                    </div>
                                    {!collapsed && (
                                        <ChevronDown
                                            className={`w-4 h-4 transition-transform duration-200 ${openMaster ? 'rotate-180' : ''
                                                }`}
                                        />
                                    )}
                                </button>

                                {(!collapsed && openMaster) && (
                                    <div className="pl-5 ml-6 border-l border-slate-200 my-1 space-y-1">
                                        <Link
                                            href="/admin/kode-barang"
                                            className={`block px-3 py-2 text-[13.5px] rounded-lg transition ${currentPath.startsWith('/admin/kode-barang')
                                                    ? 'text-[#2563eb] font-bold bg-blue-50/50'
                                                    : 'text-slate-500 hover:text-[#2563eb] hover:bg-slate-50 font-medium'
                                                }`}
                                        >
                                            Kode Barang
                                        </Link>
                                        <Link
                                            href="/pelaporan-bm/acuan"
                                            className={`block px-3 py-2 text-[13.5px] rounded-lg transition ${currentPath.startsWith('/pelaporan-bm/acuan')
                                                    ? 'text-[#2563eb] font-bold bg-blue-50/50'
                                                    : 'text-slate-500 hover:text-[#2563eb] hover:bg-slate-50 font-medium'
                                                }`}
                                        >
                                            Input Acuan
                                        </Link>
                                        <Link
                                            href="/pelaporan-bm/rekapan"
                                            className={`block px-3 py-2 text-[13.5px] rounded-lg transition ${currentPath.startsWith('/pelaporan-bm/rekapan')
                                                    ? 'text-[#2563eb] font-bold bg-blue-50/50'
                                                    : 'text-slate-500 hover:text-[#2563eb] hover:bg-slate-50 font-medium'
                                                }`}
                                        >
                                            Data Kendali Realisasi
                                        </Link>
                                        <Link
                                            href="/admin/user"
                                            className={`block px-3 py-2 text-[13.5px] rounded-lg transition ${currentPath.startsWith('/admin/user')
                                                    ? 'text-[#2563eb] font-bold bg-blue-50/50'
                                                    : 'text-slate-500 hover:text-[#2563eb] hover:bg-slate-50 font-medium'
                                                }`}
                                        >
                                            Kelola Users
                                        </Link>
                                    </div>
                                )}
                            </div>

                            {isAdmin && (
                                <>
                                    {!collapsed && (
                                        <div className="text-[11px] font-bold text-slate-400 uppercase tracking-wider px-3 pt-4 pb-2">
                                            Monitoring
                                        </div>
                                    )}

                                    <Link
                                        href="/admin/log-aktivitas"
                                        className={`flex items-center gap-3 px-3.5 py-3 rounded-xl text-sm font-semibold transition ${currentPath.startsWith('/admin/log-aktivitas')
                                                ? 'bg-[#eff6ff] text-[#2563eb] border-l-4 border-[#2563eb] rounded-l-none'
                                                : 'text-slate-500 hover:bg-blue-50/50 hover:text-[#2563eb]'
                                            }`}
                                        title={collapsed ? 'Log Aktivitas' : undefined}
                                    >
                                        <ClipboardList className="w-5 h-5 shrink-0" />
                                        {!collapsed && <span>Log Aktivitas</span>}
                                    </Link>

                                    {isSuperAdmin && (
                                        <a
                                            href="/admin/log-error"
                                            className="flex items-center gap-3 px-3.5 py-3 rounded-xl text-sm font-semibold transition text-slate-500 hover:bg-blue-50/50 hover:text-[#2563eb]"
                                            title={collapsed ? 'Log Error' : undefined}
                                        >
                                            <FileSpreadsheet className="w-5 h-5 shrink-0" />
                                            {!collapsed && <span>Log Error</span>}
                                        </a>
                                    )}
                                </>
                            )}

                            {!collapsed && (
                                <div className="text-[11px] font-bold text-slate-400 uppercase tracking-wider px-3 pt-4 pb-2">
                                    Reports & Tools
                                </div>
                            )}

                            {/* Laporan Dropdown */}
                            <div>
                                <button
                                    type="button"
                                    onClick={() => setOpenLaporan(!openLaporan)}
                                    className={`w-full flex items-center justify-between px-3.5 py-3 rounded-xl text-sm font-semibold transition cursor-pointer ${isLaporanActive
                                            ? 'text-[#2563eb] bg-blue-50/30'
                                            : 'text-slate-500 hover:bg-blue-50/50 hover:text-[#2563eb]'
                                        }`}
                                    title={collapsed ? 'Laporan' : undefined}
                                >
                                    <div className="flex items-center gap-3">
                                        <BarChart3 className="w-5 h-5 shrink-0" />
                                        {!collapsed && <span>Laporan</span>}
                                    </div>
                                    {!collapsed && (
                                        <ChevronDown
                                            className={`w-4 h-4 transition-transform duration-200 ${openLaporan ? 'rotate-180' : ''
                                                }`}
                                        />
                                    )}
                                </button>

                                {(!collapsed && openLaporan) && (
                                    <div className="pl-5 ml-6 border-l border-slate-200 my-1 space-y-1">
                                        <Link
                                            href="/pelaporan-bm/cetak"
                                            className={`block px-3 py-2 text-[13.5px] rounded-lg transition ${currentPath.startsWith('/pelaporan-bm/cetak')
                                                    ? 'text-[#2563eb] font-bold bg-blue-50/50'
                                                    : 'text-slate-500 hover:text-[#2563eb] hover:bg-slate-50 font-medium'
                                                }`}
                                        >
                                            Cetak Laporan
                                        </Link>
                                    </div>
                                )}
                            </div>
                        </>
                    ) : (
                        <>
                            {!collapsed && (
                                <div className="text-[11px] font-bold text-slate-400 uppercase tracking-wider px-3 py-2">
                                    Menu Utama User
                                </div>
                            )}
                            <Link
                                href="/dashboard"
                                className={`flex items-center gap-3 px-3.5 py-3 rounded-xl text-sm font-semibold transition ${currentPath === '/dashboard'
                                        ? 'bg-[#eff6ff] text-[#2563eb] border-l-4 border-[#2563eb] rounded-l-none'
                                        : 'text-slate-500 hover:bg-blue-50/50 hover:text-[#2563eb]'
                                    }`}
                                title={collapsed ? 'Dashboard' : undefined}
                            >
                                <LayoutDashboard className="w-5 h-5 shrink-0" />
                                {!collapsed && <span>Dashboard</span>}
                            </Link>

                            <Link
                                href="/pelaporan-bm/spj/pilih-bulan"
                                className={`flex items-center gap-3 px-3.5 py-3 rounded-xl text-sm font-semibold transition ${currentPath.startsWith('/pelaporan-bm/spj')
                                        ? 'bg-[#eff6ff] text-[#2563eb] border-l-4 border-[#2563eb] rounded-l-none'
                                        : 'text-slate-500 hover:bg-blue-50/50 hover:text-[#2563eb]'
                                    }`}
                                title={collapsed ? 'Data Barang' : undefined}
                            >
                                <FileSpreadsheet className="w-5 h-5 shrink-0" />
                                {!collapsed && <span>Data Barang</span>}
                            </Link>

                            <Link
                                href="/pelaporan-bm/input-realisasi/pilih-bulan"
                                className={`flex items-center gap-3 px-3.5 py-3 rounded-xl text-sm font-semibold transition ${currentPath.startsWith('/pelaporan-bm/input-realisasi')
                                        ? 'bg-[#eff6ff] text-[#2563eb] border-l-4 border-[#2563eb] rounded-l-none'
                                        : 'text-slate-500 hover:bg-blue-50/50 hover:text-[#2563eb]'
                                    }`}
                                title={collapsed ? 'Input Realisasi' : undefined}
                            >
                                <ClipboardList className="w-5 h-5 shrink-0" />
                                {!collapsed && <span>Input Realisasi</span>}
                            </Link>

                            <Link
                                href="/pelaporan-bm/realisasi"
                                className={`flex items-center gap-3 px-3.5 py-3 rounded-xl text-sm font-semibold transition ${currentPath.startsWith('/pelaporan-bm/realisasi')
                                        ? 'bg-[#eff6ff] text-[#2563eb] border-l-4 border-[#2563eb] rounded-l-none'
                                        : 'text-slate-500 hover:bg-blue-50/50 hover:text-[#2563eb]'
                                    }`}
                                title={collapsed ? 'Data Realisasi' : undefined}
                            >
                                <FileSpreadsheet className="w-5 h-5 shrink-0" />
                                {!collapsed && <span>Data Realisasi</span>}
                            </Link>
                        </>
                    )}
                </div>

                <div className="p-3 border-t border-slate-100">
                    <button
                        type="button"
                        onClick={() => setShowLogoutConfirm(true)}
                        className="w-full flex items-center gap-3 px-3.5 py-3 rounded-xl text-sm font-semibold text-red-600 hover:bg-red-50 transition cursor-pointer"
                        title={collapsed ? 'Logout' : undefined}
                    >
                        <LogOut className="w-5 h-5 shrink-0" />
                        {!collapsed && <span>Logout</span>}
                    </button>
                </div>
            </aside>

            {/* Main Content Area */}
            <div
                className={`flex-1 flex flex-col transition-all duration-300 ${collapsed ? 'lg:ml-20' : 'lg:ml-72'
                    }`}
            >
                <header className="sticky top-0 h-20 bg-white/90 backdrop-blur-md border-b border-slate-200 px-6 lg:px-10 flex items-center justify-between z-30">
                    <div className="flex items-center gap-4">
                        <button
                            type="button"
                            onClick={() => setCollapsed(!collapsed)}
                            className="hidden lg:flex p-2 bg-slate-100 hover:bg-slate-200 rounded-lg text-slate-600 cursor-pointer"
                        >
                            <Menu className="w-5 h-5" />
                        </button>
                        <button
                            type="button"
                            onClick={() => setMobileOpen(!mobileOpen)}
                            className="lg:hidden p-2 bg-slate-100 hover:bg-slate-200 rounded-lg text-slate-600 cursor-pointer"
                        >
                            <Menu className="w-5 h-5" />
                        </button>

                        <div>
                            <h2 className="font-bold text-base text-slate-800 leading-tight">{title}</h2>
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        <div className="text-right hidden sm:block">
                            <h3 className="font-bold text-xs text-slate-800 max-w-[260px] truncate">
                                {namaSekolahTampil}
                            </h3>
                            <span className="inline-flex items-center gap-1 px-2 py-0.5 bg-emerald-50 text-emerald-700 text-[10px] font-bold rounded-md">
                                <CheckCircle2 className="w-3 h-3" /> Akses Sekolah
                            </span>
                        </div>

                        <div className="w-10 h-10 rounded-full bg-[#2563eb] text-white flex items-center justify-center font-bold text-sm shadow-sm">
                            {user?.name ? user.name[0].toUpperCase() : <UserIcon className="w-5 h-5" />}
                        </div>
                    </div>
                </header>

                <main className="p-6 lg:p-10 flex-1">
                    {children}
                </main>
            </div>

            <ConfirmDialog
                isOpen={showLogoutConfirm}
                onClose={() => setShowLogoutConfirm(false)}
                onConfirm={() => router.post('/logout')}
                title="Konfirmasi Keluar"
                message="Apakah Anda yakin ingin keluar dari sistem?"
                confirmText="Ya, Keluar"
                isDestructive={true}
            />
        </div>
    );
}
