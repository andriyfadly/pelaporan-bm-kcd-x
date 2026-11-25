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
     * @param  Collection<int, array{0: string, 1: string, 2: string, 3: string, 4: string, 5: int, 6: int, 7: int, 8: string, 9: int, 10: float}>  $contoh
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
            'Sumber Perolehan', 'Kode Barang', 'Nama Barang', 'Merek / Type', 'Bahan',
            'Tanggal', 'Bulan Perolehan', 'Tahun Perolehan', 'Satuan', 'Volume', 'Harga Satuan',
        ];
    }
}
