<?php

namespace App\Exports\DistribusiBhp;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class BhpKartuPersediaanExport implements Export, FromCollection, WithHeadings, WithTitle
{
    /**
     * @param  array<string,mixed>  $barang
     * @param  array<int,array<string,mixed>>  $rows
     */
    public function __construct(
        private array $barang,
        private array $rows,
    ) {}

    public function title(): string
    {
        return 'Kartu Persediaan BHP';
    }

    public function headings(): array
    {
        return [
            $this->barang['nama_barang'] ?? 'Barang',
            'Sumber Dana: '.($this->barang['sumber_dana'] ?? '-'),
            'Saldo Awal: '.($this->barang['saldo_awal'] ?? 0),
        ];
    }

    public function collection(): Collection
    {
        $rows = collect($this->rows)->map(fn (array $row) => [
            $row['tanggal'],
            $row['jenis'],
            $row['referensi'],
            $row['masuk'],
            $row['keluar'],
            $row['saldo'],
            $row['keterangan'],
        ]);

        return $rows->prepend([
            'Tanggal', 'Jenis', 'Referensi', 'Masuk', 'Keluar', 'Saldo', 'Keterangan',
        ])->push([
            'Total', '', '',
            $this->barang['total_masuk'] ?? 0,
            $this->barang['total_keluar'] ?? 0,
            $this->barang['saldo_akhir'] ?? 0,
            '',
        ]);
    }
}
