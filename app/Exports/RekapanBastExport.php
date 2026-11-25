<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Rekap BAST untuk diunduh sebagai Excel, mengikuti `rekapan_ba.php` acuan
 * (delapan kolom, satu baris per dokumen BAST).
 */
class RekapanBastExport implements FromCollection, ShouldAutoSize, WithHeadings, WithTitle
{
    /**
     * @param  array<int, array<int, mixed>>  $baris
     */
    public function __construct(private readonly array $baris) {}

    public function collection(): Collection
    {
        return collect($this->baris);
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'No', 'Nomor Urut', 'Nomor Surat Lengkap', 'Nama Barang', 'No. Reg', 'Kondisi Barang',
            'Nama Penerima', 'Tanggal BA',
        ];
    }

    public function title(): string
    {
        return 'Rekapan BAST';
    }
}
