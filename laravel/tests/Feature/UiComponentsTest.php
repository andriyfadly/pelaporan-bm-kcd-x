<?php

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Blade;

it('formats currency and report statuses consistently', function (): void {
    expect(Blade::render('<x-ui.currency :value="1200000" />'))->toContain('Rp 1.200.000')
        ->and(Blade::render('<x-ui.status-badge status="Menunggu Approval" />'))->toContain('Menunggu Approval')
        ->toContain('amber')
        ->and(Blade::render('<x-ui.status-badge status="Disetujui" />'))->toContain('Disetujui')
        ->toContain('emerald');
});

it('renders reusable application chrome and empty state', function (): void {
    $html = Blade::render(<<<'BLADE'
        <x-layouts.app-layout title="Data Acuan">
            <x-ui.page-header title="Data Acuan" description="Daftar target." />
            <x-ui.empty-state title="Data tidak ditemukan." />
        </x-layouts.app-layout>
    BLADE);

    expect($html)->toContain('<title>Data Acuan</title>')
        ->toContain('Data Acuan')
        ->toContain('Daftar target.')
        ->toContain('Data tidak ditemukan.')
        ->toContain('stylesheet');
});

it('renders reusable pagination from a paginator', function (): void {
    $paginator = new LengthAwarePaginator([], 0, 50, 1, ['path' => '/acuan']);

    expect(Blade::render('<x-ui.pagination :paginator="$paginator" />', ['paginator' => $paginator]))
        ->toContain('Tidak ada halaman lain.');
});
