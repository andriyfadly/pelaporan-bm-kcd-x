<?php

namespace App\Exports;

use App\Models\Master\KodeBarang;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithTitle;

class KodeBarangSheet implements FromCollection, WithTitle
{
    public function title(): string
    {
        return 'KODE BARANG';
    }

    public function collection(): Collection
    {
        $rows = KodeBarang::query()
            ->orderBy('kode_barang')
            ->get(['kode_barang', 'uraian', 'kodering_aset', 'jenis_aset', 'umur_ekonomis'])
            ->map(fn ($m) => [$m->kode_barang, $m->uraian, $m->kodering_aset, $m->jenis_aset, (int) $m->umur_ekonomis]);

        return collect([['KODE BARANG', 'URAIAN', 'KODERING ASET', 'JENIS ASET', 'UMUR EKONOMIS']])->concat($rows);
    }
}
