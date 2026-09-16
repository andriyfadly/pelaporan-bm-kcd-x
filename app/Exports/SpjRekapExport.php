<?php

namespace App\Exports;

use App\Models\PelaporanBm\Spj;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class SpjRekapExport implements Export, FromCollection, WithHeadings, WithTitle
{
    use Exportable;

    /**
     * @param  Collection<int, Spj>  $items
     */
    public function __construct(private Collection $items) {}

    public function title(): string
    {
        return 'Rekap SPJ';
    }

    public function headings(): array
    {
        return ['No SPK', 'No SP2D', 'Sumber', 'Kode Barang', 'Nama Barang', 'Jenis Aset', 'Merk/Tipe', 'Satuan', 'Volume', 'Harga Satuan', 'Nilai Perolehan'];
    }

    public function collection(): Collection
    {
        return $this->items->map(fn ($item) => [
            $item->no_spk,
            $item->no_sp2d,
            $item->sumber_perolehan,
            $item->kode_barang,
            $item->nama_barang,
            $item->jenis_aset,
            $item->merk_tipe,
            $item->satuan,
            $item->volume,
            $item->harga_satuan,
            $item->nilai_perolehan,
        ]);
    }
}
