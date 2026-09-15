import { useForm, Head, router } from '@inertiajs/react';
import React, { useState, FormEventHandler } from 'react';
import { KeyRound, Eye, EyeOff, ShieldAlert, LogOut } from 'lucide-react';

export default function ChangePassword() {
    const [showCurrentPassword, setShowCurrentPassword] = useState(false);
    const [showNewPassword, setShowNewPassword] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/ubah-password');
    };

    const handleLogout = () => {
        router.post('/logout');
    };

    return (
        <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-[#f0f9ff] to-[#e0f2fe] p-4">
            <Head title="Wajib Ubah Password | Sistem Belanja Modal" />

            <div className="w-full max-w-[440px] bg-white rounded-2xl shadow-[0_20px_40px_rgba(0,0,0,0.06)] border border-slate-100 p-6 sm:p-8">
                <div className="text-center mb-6">
                    <div className="w-12 h-12 bg-amber-50 text-amber-600 rounded-2xl flex items-center justify-center mx-auto mb-3 border border-amber-200">
                        <ShieldAlert className="w-6 h-6" />
                    </div>
                    <h2 className="text-xl font-bold text-slate-900">Pembaruan Password Wajib</h2>
                    <p className="text-xs text-slate-500 mt-1 leading-relaxed">
                        Untuk keamanan akun Operator Sekolah, Anda diwajibkan mengganti password saat login perdana dan secara berkala setiap 3 bulan sekali.
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-4 text-xs">
                    <div>
                        <label className="block font-bold text-slate-700 uppercase mb-1">
                            Password Saat Ini (Default)
                        </label>
                        <div className="relative flex items-center bg-slate-50 border border-slate-200 rounded-xl px-3 py-1 focus-within:border-sky-500 focus-within:bg-white focus-within:ring-2 focus-within:ring-sky-200">
                            <KeyRound className="w-4 h-4 text-slate-400 mr-2 shrink-0" />
                            <input
                                type={showCurrentPassword ? 'text' : 'password'}
                                value={data.current_password}
                                onChange={(e) => setData('current_password', e.target.value)}
                                placeholder="Masukkan password saat ini"
                                className="w-full bg-transparent border-none py-1.5 font-medium text-slate-800 outline-none text-xs"
                                required
                            />
                            <button
                                type="button"
                                onClick={() => setShowCurrentPassword(!showCurrentPassword)}
                                className="text-slate-400 hover:text-slate-600 p-1 cursor-pointer"
                            >
                                {showCurrentPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                            </button>
                        </div>
                        {errors.current_password && (
                            <p className="text-red-500 text-[11px] mt-1 font-semibold">{errors.current_password}</p>
                        )}
                    </div>

                    <div>
                        <label className="block font-bold text-slate-700 uppercase mb-1">
                            Password Baru
                        </label>
                        <div className="relative flex items-center bg-slate-50 border border-slate-200 rounded-xl px-3 py-1 focus-within:border-sky-500 focus-within:bg-white focus-within:ring-2 focus-within:ring-sky-200">
                            <KeyRound className="w-4 h-4 text-slate-400 mr-2 shrink-0" />
                            <input
                                type={showNewPassword ? 'text' : 'password'}
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                placeholder="Min. 8 karakter (huruf besar/kecil, angka, simbol)"
                                className="w-full bg-transparent border-none py-1.5 font-medium text-slate-800 outline-none text-xs"
                                required
                            />
                            <button
                                type="button"
                                onClick={() => setShowNewPassword(!showNewPassword)}
                                className="text-slate-400 hover:text-slate-600 p-1 cursor-pointer"
                            >
                                {showNewPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                            </button>
                        </div>
                        {errors.password && (
                            <p className="text-red-500 text-[11px] mt-1 font-semibold">{errors.password}</p>
                        )}
                    </div>

                    <div>
                        <label className="block font-bold text-slate-700 uppercase mb-1">
                            Ulangi Password Baru
                        </label>
                        <div className="relative flex items-center bg-slate-50 border border-slate-200 rounded-xl px-3 py-1 focus-within:border-sky-500 focus-within:bg-white focus-within:ring-2 focus-within:ring-sky-200">
                            <KeyRound className="w-4 h-4 text-slate-400 mr-2 shrink-0" />
                            <input
                                type={showNewPassword ? 'text' : 'password'}
                                value={data.password_confirmation}
                                onChange={(e) => setData('password_confirmation', e.target.value)}
                                placeholder="Ketik ulang password baru"
                                className="w-full bg-transparent border-none py-1.5 font-medium text-slate-800 outline-none text-xs"
                                required
                            />
                        </div>
                    </div>

                    <div className="pt-2 space-y-2">
                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full py-2.5 px-4 bg-sky-600 hover:bg-sky-700 text-white font-bold rounded-xl shadow-sm transition-all duration-200 disabled:opacity-50 cursor-pointer flex items-center justify-center gap-2"
                        >
                            {processing ? 'Menyimpan...' : 'Simpan & Lanjutkan'}
                        </button>

                        <button
                            type="button"
                            onClick={handleLogout}
                            className="w-full py-2 px-4 border border-slate-200 hover:bg-slate-50 text-slate-600 font-semibold rounded-xl transition cursor-pointer flex items-center justify-center gap-1.5"
                        >
                            <LogOut className="w-3.5 h-3.5" /> Keluar Akun
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
