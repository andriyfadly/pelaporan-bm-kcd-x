<?php

namespace App\Exports\DistribusiBhp;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class BhpStokExport implements Export, FromCollection, WithHeadings, WithTitle
{
    use Exportable;

    /**
     * @param  array<int,array<string,mixed>>  $rows
     */
    public function __construct(private array $rows) {}

    public function title(): string
    {
        return 'Laporan Stok BHP';
    }

    public function headings(): array
    {
        return ['Kode Barang', 'Nama Barang', 'Satuan', 'Fisik', 'Terpakai', 'Reservasi', 'Tersedia'];
    }

    public function collection(): Collection
    {
        return collect($this->rows)->map(fn (array $row) => [
            $row['kode_barang'],
            $row['nama_barang'],
            $row['satuan'],
            $row['fisik'],
            $row['terpakai'],
            $row['reservasi'],
            $row['tersedia'],
        ]);
    }
}
