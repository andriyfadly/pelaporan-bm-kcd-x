<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Template impor unit inventaris. Kolomnya sama dengan template aplikasi acuan
 * (template_import_inventaris.xlsx) supaya berkas lama tetap bisa dipakai.
 */
class InventarisTemplateExport implements FromCollection, ShouldAutoSize, WithHeadings
{
    /**
     * @param  Collection<int, array<int, mixed>>  $contoh
     */
    public function __construct(private readonly Collection $contoh) {}

    public function collection(): Collection
    {
        return $this->contoh;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Kode Barang', 'Nama Barang', 'Merek / Type', 'Tanggal',
            'Bulan Perolehan', 'Tahun Perolehan', 'Satuan', 'Volume', 'Harga Satuan',
            'Asal Perolehan',
        ];
    }
}
