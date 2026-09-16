<?php

namespace App\Exports;

use App\Models\PelaporanBm\Realisasi;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class RealisasiBmExport implements Export, WithMultipleSheets
{
    use Exportable;

    /**
     * @param  Collection<int, Realisasi>  $records
     */
    public function __construct(
        private Collection $records,
        private string $namaSekolah,
        private int $filterTahun,
    ) {}

    public function sheets(): array
    {
        return [
            new RealisasiBmSheet($this->records, $this->namaSekolah, $this->filterTahun),
            new KodeBarangSheet,
        ];
    }
}
