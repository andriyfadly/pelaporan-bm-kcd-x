import { useForm, Head } from '@inertiajs/react';
import React, { useState, FormEventHandler } from 'react';
import { User as UserIcon, Lock, Eye, EyeOff, ArrowRight } from 'lucide-react';

export default function Login() {
    const [showPassword, setShowPassword] = useState(false);
    const { data, setData, post, processing, errors } = useForm({
        username: '',
        password: '',
        remember: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/login');
    };

    return (
        <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-[#f0f9ff] to-[#e0f2fe] p-4 font-sans">
            <Head title="Login | Sistem Belanja Modal" />

            <div className="w-full max-w-[400px] bg-white rounded-[20px] shadow-[0_20px_40px_rgba(0,0,0,0.05)] overflow-hidden">
                <div className="pt-6 pb-2 px-6 text-center">
                    <img
                        src="/images/diptanew.jpeg"
                        alt="Logo SI DIPTA"
                        className="w-36 h-auto mx-auto object-contain mb-1"
                        onError={(e) => {
                            (e.currentTarget as HTMLElement).style.display = 'none';
                        }}
                    />
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
                    <p className="text-[9px] font-bold text-slate-400 tracking-wider uppercase mt-1">
                        Sistem Digitalisasi Pelaporan Aset
                    </p>
                </div>

                <div className="p-6 pt-2">
                    {errors.username && (
                        <div className="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 text-xs font-semibold rounded-xl">
                            {errors.username}
                        </div>
                    )}

                    <form onSubmit={submit} className="space-y-3.5">
                        <div>
                            <label className="block text-xs font-bold text-slate-600 uppercase mb-1 tracking-wider">
                                Username
                            </label>
                            <div className="flex items-center bg-slate-50 border-[1.5px] border-slate-200 rounded-xl px-3 focus-within:border-[#38bdf8] focus-within:bg-white focus-within:ring-4 focus-within:ring-[#38bdf8]/15 transition">
                                <UserIcon className="w-4 h-4 text-slate-400 mr-2 shrink-0" />
                                <input
                                    type="text"
                                    value={data.username}
                                    onChange={(e) => setData('username', e.target.value)}
                                    placeholder="Masukkan username Anda"
                                    className="w-full bg-transparent border-none py-2 text-sm text-slate-800 outline-none font-medium placeholder-slate-400"
                                    autoComplete="username"
                                    required
                                />
                            </div>
                        </div>

                        <div>
                            <label className="block text-xs font-bold text-slate-600 uppercase mb-1 tracking-wider">
                                Password
                            </label>
                            <div className="flex items-center bg-slate-50 border-[1.5px] border-slate-200 rounded-xl px-3 focus-within:border-[#38bdf8] focus-within:bg-white focus-within:ring-4 focus-within:ring-[#38bdf8]/15 transition">
                                <Lock className="w-4 h-4 text-slate-400 mr-2 shrink-0" />
                                <input
                                    type={showPassword ? 'text' : 'password'}
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    placeholder="••••••••"
                                    className="w-full bg-transparent border-none py-2 text-sm text-slate-800 outline-none font-medium placeholder-slate-400"
                                    autoComplete="current-password"
                                    required
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowPassword(!showPassword)}
                                    className="text-slate-400 hover:text-slate-600 p-1 cursor-pointer"
                                >
                                    {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                                </button>
                            </div>
                            {errors.password && <p className="text-red-500 text-xs mt-1">{errors.password}</p>}
                        </div>

                        <div className="flex items-center justify-between pt-1">
                            <label className="flex items-center text-xs text-slate-600 font-medium cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={data.remember}
                                    onChange={(e) => setData('remember', e.target.checked)}
                                    className="rounded border-slate-300 text-[#0284c7] focus:ring-[#38bdf8] mr-2"
                                />
                                Ingat sesi saya
                            </label>
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full mt-2 py-2.5 px-4 bg-[#38bdf8] hover:bg-[#0284c7] text-white font-bold text-sm rounded-xl shadow-[0_4px_12px_rgba(56,189,248,0.2)] hover:shadow-[0_6px_16px_rgba(2,132,199,0.3)] hover:-translate-y-0.5 transition duration-200 disabled:opacity-50 flex items-center justify-center gap-2 cursor-pointer"
                        >
                            <span>{processing ? 'Memproses...' : 'MASUK KE SISTEM'}</span>
                            <ArrowRight className="w-4 h-4" />
                        </button>
                    </form>
                </div>
            </div>
        </div>
    );
}
