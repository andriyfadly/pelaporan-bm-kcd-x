<?php

namespace App\Http\Requests\PencatatanBm;

use App\Models\PencatatanBm\InventarisUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PendaftaranInventarisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Kontrak impor 11 kolom (parser acuan `proses_import_langsung.php`):
     * A Kode Barang, B Asal Perolehan, C Nama Barang, D Merek/Tipe, E Bahan,
     * F Tanggal, G Bulan, H Tahun, I Satuan, J Volume, K Harga Satuan.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kode_barang' => ['required', 'string', 'max:100'],
            'nama_barang' => ['nullable', 'string', 'max:255'],
            'merk_tipe' => ['nullable', 'string', 'max:255'],
            'bahan' => ['nullable', 'string', 'max:100'],
            'no_sertifikat' => ['nullable', 'string', 'max:255'],
            'satuan' => ['nullable', 'string', 'max:50'],
            'tanggal_perolehan' => ['required', 'date'],
            'volume' => ['required', 'integer', 'min:1', 'max:500'],
            'harga_satuan' => ['nullable', 'numeric', 'min:0'],
            'asal_perolehan' => ['nullable', 'string', 'max:150'],
            'kondisi' => ['nullable', Rule::in(array_keys(InventarisUnit::KONDISI))],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kode_barang.required' => 'Kode barang wajib diisi.',
            'volume.min' => 'Volume minimal 1 unit.',
            'tanggal_perolehan.required' => 'Tanggal perolehan wajib diisi: nomor register diulang tiap tahun.',
        ];
    }
}
