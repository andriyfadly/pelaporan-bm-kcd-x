import { useForm, Head, usePage } from '@inertiajs/react';
import React, { useEffect, useRef, useState, FormEventHandler } from 'react';
import { User as UserIcon, KeyRound, Eye, EyeOff, AlertCircle } from 'lucide-react';

declare global {
    interface Window {
        turnstile?: {
            render: (container: string | HTMLElement, options: Record<string, unknown>) => string;
            reset: (widgetId?: string) => void;
            getResponse: (widgetId?: string) => string | undefined;
        };
        onloadTurnstileCallback?: () => void;
    }
}

const TURNSTILE_SRC = 'https://challenges.cloudflare.com/turnstile/v0/api.js?onload=onloadTurnstileCallback&render=explicit';

export default function Login() {
    const { turnstileSiteKey } = usePage<{ turnstileSiteKey?: string | null }>().props;
    const [showPassword, setShowPassword] = useState(false);
    const [turnstileReady, setTurnstileReady] = useState(false);
    const widgetRef = useRef<HTMLDivElement>(null);
    const widgetIdRef = useRef<string | null>(null);
    const { data, setData, post, processing, errors, transform } = useForm({
        username: '',
        password: '',
        remember: true,
    });

    useEffect(() => {
        if (!turnstileSiteKey) return;
        if (document.querySelector(`script[src="${TURNSTILE_SRC}"]`)) {
            setTurnstileReady(!!window.turnstile);
            return;
        }
        window.onloadTurnstileCallback = () => setTurnstileReady(true);
        const script = document.createElement('script');
        script.src = TURNSTILE_SRC;
        script.async = true;
        script.defer = true;
        document.head.appendChild(script);
        return () => {
            delete window.onloadTurnstileCallback;
        };
    }, [turnstileSiteKey]);

    useEffect(() => {
        if (!turnstileSiteKey || !turnstileReady || !window.turnstile || !widgetRef.current) return;
        if (widgetIdRef.current) return;
        widgetIdRef.current = window.turnstile.render(widgetRef.current, { sitekey: turnstileSiteKey });
    }, [turnstileSiteKey, turnstileReady]);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        transform((payload) => ({
            ...payload,
            'cf-turnstile-response': window.turnstile?.getResponse(widgetIdRef.current ?? undefined) ?? '',
        }));
        post('/login', {
            onFinish: () => window.turnstile?.reset(widgetIdRef.current ?? undefined),
        });
    };

    return (
        <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-[#f0f9ff] to-[#e0f2fe] p-[15px]">
            <Head title="Login | Sistem Belanja Modal" />

            <div className="w-full max-w-[400px] bg-white rounded-[20px] shadow-[0_20px_40px_rgba(0,0,0,0.05)] overflow-hidden">
                <div className="bg-white px-[30px] pt-[15px] pb-[10px] text-center">
                    <img
                        src="/images/logolog.jpeg"
                        alt="Logo"
                        className="w-[160px] h-auto max-w-full object-contain -mb-[15px] block mx-auto"
                        onError={(e) => {
                            (e.currentTarget as HTMLElement).style.display = 'none';
                        }}
                    />
                    <h3 className="font-bold text-slate-900 text-xl mb-0">Selamat Datang</h3>
                    <img
                        src="/images/diptanew.jpeg"
                        alt="Banner"
                        className="w-full max-h-[75px] object-cover rounded-[12px] mt-[5px] mb-[10px] shadow-[0_4px_10px_rgba(0,0,0,0.05)] block"
                        onError={(e) => {
                            (e.currentTarget as HTMLElement).style.display = 'none';
                        }}
                    />
                </div>

                <div className="px-[30px] pb-[25px]">
                    {(errors.username || errors.password) && (
                        <div className="mb-2 p-2.5 bg-red-50 text-red-700 text-xs font-semibold rounded-[10px] flex items-center gap-2 border border-red-200">
                            <AlertCircle className="w-4 h-4 text-red-600 shrink-0" />
                            <span>{errors.username || errors.password}</span>
                        </div>
                    )}

                    <form onSubmit={submit}>
                        <div className="mb-2">
                            <label className="block font-semibold text-[0.8rem] text-[#475569] mb-1 tracking-[0.5px] uppercase">
                                USERNAME
                            </label>
                            <div className="bg-[#f8fafc] border-[1.5px] border-[#e2e8f0] rounded-[12px] px-3 py-0.5 flex items-center focus-within:border-[#38bdf8] focus-within:bg-white focus-within:ring-4 focus-within:ring-[#38bdf8]/15 transition-all duration-200">
                                <UserIcon className="w-4 h-4 text-[#94a3b8] mr-2.5 shrink-0" />
                                <input
                                    type="text"
                                    name="username"
                                    value={data.username}
                                    onChange={(e) => setData('username', e.target.value)}
                                    placeholder="Username"
                                    className="w-full bg-transparent border-none py-2 font-medium text-[0.9rem] text-[#1e293b] outline-none placeholder-[#94a3b8]"
                                    autoComplete="username"
                                    required
                                />
                            </div>
                        </div>

                        <div className="mb-3">
                            <label className="block font-semibold text-[0.8rem] text-[#475569] mb-1 tracking-[0.5px] uppercase">
                                PASSWORD
                            </label>
                            <div className="bg-[#f8fafc] border-[1.5px] border-[#e2e8f0] rounded-[12px] px-3 py-0.5 flex items-center focus-within:border-[#38bdf8] focus-within:bg-white focus-within:ring-4 focus-within:ring-[#38bdf8]/15 transition-all duration-200">
                                <KeyRound className="w-4 h-4 text-[#94a3b8] mr-2.5 shrink-0" />
                                <input
                                    type={showPassword ? 'text' : 'password'}
                                    name="password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    placeholder="••••••••"
                                    className="w-full bg-transparent border-none py-2 font-medium text-[0.9rem] text-[#1e293b] outline-none placeholder-[#94a3b8]"
                                    autoComplete="current-password"
                                    required
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowPassword(!showPassword)}
                                    className="text-[#94a3b8] hover:text-[#475569] ml-1 p-0.5 cursor-pointer shrink-0"
                                    title="Tampilkan/Sembunyikan Password"
                                >
                                    {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                                </button>
                            </div>
                        </div>

                        {turnstileSiteKey ? (
                            <div className="my-3 flex justify-center">
                                <div ref={widgetRef} />
                            </div>
                        ) : null}

                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full mt-2 py-2.5 px-4 bg-[#38bdf8] hover:bg-[#0284c7] text-white font-bold text-[0.9rem] rounded-[12px] shadow-[0_4px_12px_rgba(56,189,248,0.2)] hover:shadow-[0_6px_16px_rgba(2,132,199,0.3)] hover:-translate-y-0.5 transition-all duration-300 disabled:opacity-50 flex items-center justify-center cursor-pointer"
                        >
                            {processing ? 'MEMPROSES...' : 'MASUK SEKARANG'}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    );
}
