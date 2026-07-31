<header {{ $attributes->class('border-b border-slate-200 pb-5') }}>
    <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ $title }}</h1>
    @if ($description)
        <p class="mt-1 text-sm text-slate-600">{{ $description }}</p>
    @endif
</header>
