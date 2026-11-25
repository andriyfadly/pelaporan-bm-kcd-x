<?php

namespace App\Exports;

use App\Models\PencatatanBm\InventarisUnit;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Ekspor unit inventaris satu sekolah, satu baris per unit.
 */
class InventarisExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(private readonly Builder $query) {}

    public function query(): Builder
    {
        return $this->query->with(['ruang:id,nama_ruang', 'pegawai:id,nama_lengkap,nip']);
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'No', 'Register', 'Kode Barang', 'Nama Barang', 'Jenis Aset', 'Merk/Tipe', 'Bahan',
            'No. Sertifikat', 'Asal Perolehan', 'Ukuran Bangunan', 'Satuan', 'Tanggal Perolehan',
            'Bulan Perolehan', 'Tahun Perolehan', 'Harga Satuan', 'Nilai Perolehan',
            'Kondisi', 'Status', 'Ruang', 'Penanggung Jawab', 'NIP', 'Keterangan',
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public function map($unit): array
    {
        static $no = 0;
        $no++;

        assert($unit instanceof InventarisUnit);

        return [
            $no,
            $unit->nomor_register,
            $unit->kode_barang,
            $unit->nama_barang,
            $unit->jenis_aset,
            $unit->merk_tipe,
            $unit->bahan,
            $unit->no_sertifikat,
            $unit->asal_perolehan,
            $unit->ukuran_bangunan,
            $unit->satuan,
            $unit->tanggal_perolehan?->format('d/m/Y'),
            $unit->bulan_perolehan,
            $unit->tahun_perolehan,
            (float) $unit->harga_satuan,
            (float) $unit->nilai_perolehan_unit,
            $unit->kondisi,
            $unit->status,
            $unit->ruang?->nama_ruang,
            $unit->pegawai?->nama_lengkap,
            $unit->pegawai->nip ?? null,
            $unit->keterangan,
        ];
    }
}
