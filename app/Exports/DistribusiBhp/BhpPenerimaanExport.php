<?php

namespace App\Exports\DistribusiBhp;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class BhpPenerimaanExport implements Export, FromCollection, WithHeadings, WithTitle
{
    use Exportable;

    /**
     * @param  array<int,array<string,mixed>>  $rows
     */
    public function __construct(private array $rows) {}

    public function title(): string
    {
        return 'Buku Penerimaan BHP';
    }

    public function headings(): array
    {
        return [
            'Tanggal',
            'No. BAST',
            'No. Nota/Invoice',
            'Penyedia',
            'Kode Barang',
            'Nama Barang',
            'Satuan',
            'Jumlah',
            'Harga Satuan',
            'Nilai Total',
            'Keterangan',
        ];
    }

    public function collection(): Collection
    {
        return collect($this->rows)->map(fn (array $row) => [
            $row['tanggal'],
            $row['no_bast'],
            $row['no_nota_invoice'],
            $row['penyedia'],
            $row['kode_barang'],
            $row['nama_barang'],
            $row['satuan'],
            $row['jumlah'],
            $row['harga_satuan'],
            $row['nilai_total'],
            ($row['lunas_bayar'] ?? false) ? 'Lunas Bayar' : '',
        ]);
    }
}
