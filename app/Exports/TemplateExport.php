<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Template impor master data sederhana: heading + beberapa baris contoh.
 */
class TemplateExport implements FromCollection, ShouldAutoSize, WithHeadings
{
    /**
     * @param  array<int, string>  $headings
     * @param  Collection<int, mixed>  $contoh
     */
    public function __construct(
        private readonly array $headings,
        private readonly Collection $contoh,
    ) {}

    public function collection(): Collection
    {
        return $this->contoh;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return $this->headings;
    }
}
