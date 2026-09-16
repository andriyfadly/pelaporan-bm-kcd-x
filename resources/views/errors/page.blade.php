<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $status }} | SI DIPTA Beu!</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body class="bg-slate-50 text-slate-900 font-sans antialiased">
    <div class="min-h-screen flex items-center justify-center p-6">
        <div class="w-full max-w-md bg-white rounded-2xl border border-slate-200 shadow-sm p-10 text-center">
            <p class="font-black text-5xl tracking-tight">{{ $status }}</p>
            <h1 class="mt-2 font-bold text-base text-slate-800">
                @switch($status)
                    @case(401) Belum Masuk @break
                    @case(403) Akses Ditolak @break
                    @case(404) Halaman Tidak Ditemukan @break
                    @case(419) Sesi Kedaluwarsa @break
                    @case(429) Terlalu Banyak Permintaan @break
                    @case(503) Sedang Pemeliharaan @break
                    @default Terjadi Kesalahan
                @endswitch
            </h1>
            <p class="mt-1 text-sm text-slate-500">
                @switch($status)
                    @case(401) Silakan masuk dulu untuk membuka halaman ini. @break
                    @case(403) Akun Anda tidak memiliki izin untuk membuka halaman ini. @break
                    @case(404) Alamat yang Anda tuju tidak ada atau sudah dipindahkan. @break
                    @case(419) Sesi Anda habis. Muat ulang halaman lalu ulangi lagi. @break
                    @case(429) Anda mengirim terlalu banyak permintaan. Tunggu sebentar lalu coba lagi. @break
                    @case(503) Sistem sedang diperbarui. Silakan kembali beberapa saat lagi. @break
                    @default Sesuatu yang tak terduga terjadi. Silakan coba lagi.
                @endswitch
            </p>
            <div class="mt-6">
                <a href="/dashboard" class="inline-flex items-center gap-2 px-4 py-2 text-xs font-bold text-white bg-[#2563eb] hover:bg-blue-700 rounded-xl transition">Kembali ke Dashboard</a>
            </div>
        </div>
    </div>
</body>
</html>
