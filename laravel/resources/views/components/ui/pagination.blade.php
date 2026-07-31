@if ($paginator->hasPages())
    <nav {{ $attributes->class('mt-6') }} aria-label="Navigasi halaman">
        {{ $paginator->links() }}
    </nav>
@else
    <p {{ $attributes->class('mt-4 text-sm text-slate-500') }}>Tidak ada halaman lain.</p>
@endif
