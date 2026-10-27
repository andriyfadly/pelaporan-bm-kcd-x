<?php

namespace App\Exports\DistribusiBhp;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Template impor data barang BHP.
 *
 * Kolom (index berbasis 0 saat dibaca kembali):
 * [0] Kode Rekening*, [1] Nama Barang*, [2] Satuan*, [3] Harga Satuan*
 *
 * Kode Barang & NUSP di-generate otomatis dari kode rekening (yang sudah punya
 * `kode_barang`), sehingga operator tidak mengetiknya manual.
 */
class BhpBarangTemplateExport implements FromCollection, WithHeadings, WithTitle
{
    /**
     * @param  array<int, array{kode_rekening:string,nama_barang:string,satuan:string,harga_satuan:int}>  $contoh
     */
    public function __construct(private array $contoh = []) {}

    public function title(): string
    {
        return 'Template Data Barang';
    }

    public function headings(): array
    {
        return ['Kode Rekening*', 'Nama Barang*', 'Satuan*', 'Harga Satuan*'];
    }

    public function collection(): Collection
    {
        return collect($this->contoh)->map(fn (array $row): array => [
            $row['kode_rekening'],
            $row['nama_barang'],
            $row['satuan'],
            $row['harga_satuan'],
        ]);
    }
}
