<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Baca berkas Excel/CSV impor master data: baris pertama heading, sisanya data.
 * Dipakai Pegawai dan Ruang supaya batas baris dan penanganan berkas kosong
 * hanya ada di satu tempat.
 */
trait ImportsBaris
{
    /**
     * @return Collection<int, Collection<int, mixed>>
     */
    protected function barisImpor(UploadedFile $berkas, int $maksimal = 1000): Collection
    {
        $rows = Excel::toCollection(null, $berkas)->first();

        return $rows instanceof Collection
            ? $rows->slice(1)->values()->take($maksimal + 1)
            : collect();
    }
}
