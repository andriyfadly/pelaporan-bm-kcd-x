<x-layouts.app-layout title="Data Acuan">
    <x-ui.page-header title="Data Acuan" description="Daftar acuan belanja modal sekolah." />

    <section class="mt-6 grid gap-4 sm:grid-cols-2">
        <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-600">Total Nominal</p>
            <p class="mt-2 text-2xl font-bold text-slate-900"><x-ui.currency :value="$totalNominal" /></p>
        </article>
        <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-600">Total Sekolah</p>
            <p class="mt-2 text-2xl font-bold text-slate-900">{{ $totalSchools }}</p>
        </article>
    </section>

    <section class="mt-6 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead class="bg-slate-50 text-slate-700">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-semibold">Sekolah</th>
                        <th scope="col" class="px-4 py-3 font-semibold">NPSN</th>
                        <th scope="col" class="px-4 py-3 font-semibold">Uraian</th>
                        <th scope="col" class="px-4 py-3 text-right font-semibold">Nominal</th>
                        <th scope="col" class="px-4 py-3 font-semibold">Bulan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    @forelse ($rows as $row)
                        <tr>
                            <td class="px-4 py-3">{{ $row->satuan_pendidikan }}</td>
                            <td class="px-4 py-3">{{ $row->npsn }}</td>
                            <td class="px-4 py-3">{{ $row->uraian }}</td>
                            <td class="px-4 py-3 text-right"><x-ui.currency :value="$row->nominal" /></td>
                            <td class="px-4 py-3">{{ $row->bulan }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-4"><x-ui.empty-state title="Data tidak ditemukan." /></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <x-ui.pagination :paginator="$rows" />
</x-layouts.app-layout>
