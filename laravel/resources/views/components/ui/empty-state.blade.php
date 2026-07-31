<section {{ $attributes->class('rounded-lg border border-dashed border-slate-300 bg-white px-6 py-10 text-center') }}>
    <h2 class="font-semibold text-slate-900">{{ $title }}</h2>
    @if ($description)
        <p class="mt-1 text-sm text-slate-600">{{ $description }}</p>
    @endif
</section>
