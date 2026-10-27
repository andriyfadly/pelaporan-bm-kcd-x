<?php

namespace App\Exports\DistribusiBhp;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class BhpPengeluaranExport implements Export, FromCollection, WithHeadings, WithTitle
{
    use Exportable;

    /**
     * @param  array<int,array<string,mixed>>  $rows
     */
    public function __construct(private array $rows) {}

    public function title(): string
    {
        return 'Buku Pengeluaran BHP';
    }

    public function headings(): array
    {
        return ['Tanggal', 'Nomor Pengajuan', 'Nama Barang', 'Satuan', 'Jumlah', 'Keperluan'];
    }

    public function collection(): Collection
    {
        return collect($this->rows)->map(fn (array $row) => [
            $row['tanggal'],
            $row['nomor_pengajuan'],
            $row['nama_barang'],
            $row['satuan'],
            $row['jumlah'],
            $row['keperluan'],
        ]);
    }
}
