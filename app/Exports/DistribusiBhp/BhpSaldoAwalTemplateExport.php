<?php

namespace App\Exports\DistribusiBhp;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Template impor saldo awal BHP.
 *
 * Kolom (index berbasis 0 saat dibaca kembali):
 * [0] Kode Barang*, [1] Jumlah*, [2] Harga Satuan*, [3] Merek/Tipe, [4] Keterangan
 *
 * Contoh baris sengaja disertakan agar operator tahu format; baris contoh
 * dilewati saat impor hanya bila barangnya tidak ditemukan (lihat controller).
 */
class BhpSaldoAwalTemplateExport implements FromCollection, WithHeadings, WithTitle
{
    /**
     * @param  array<int, array{kode_barang:string,jumlah:int,harga_satuan:int,merek_type?:?string,keterangan?:?string}>  $contoh
     */
    public function __construct(private array $contoh = []) {}

    public function title(): string
    {
        return 'Template Saldo Awal';
    }

    public function headings(): array
    {
        return ['Kode Barang*', 'Jumlah*', 'Harga Satuan*', 'Merek/Tipe', 'Keterangan'];
    }

    public function collection(): Collection
    {
        return collect($this->contoh)->map(fn (array $row): array => [
            $row['kode_barang'],
            $row['jumlah'],
            $row['harga_satuan'],
            $row['merek_type'] ?? '',
            $row['keterangan'] ?? '',
        ]);
    }
}
