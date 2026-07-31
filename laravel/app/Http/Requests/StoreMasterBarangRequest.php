<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMasterBarangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('user') ?? false;
    }

    public function rules(): array
    {
        return [
            'kategori' => ['nullable', 'string', 'max:50'],
            'no_sp2d' => ['required', 'string', 'max:100'],
            'sumber_perolehan' => ['required', 'string', 'max:100'],
            'bulan_realisasi' => ['required', 'integer', 'between:1,12'],
            'no_spk' => ['required', 'string', 'max:150'],
            'ba_no' => ['nullable', 'string', 'max:150'],
            'ba_tgl' => ['nullable', 'date'],
            'kode_barang' => ['required', 'string', 'max:50'],
            'nama_barang' => ['required', 'string', 'max:255'],
            'jenis_aset' => ['required', 'string', 'max:100'],
            'merk_tipe' => ['nullable', 'string', 'max:255'],
            'no_sertifikat' => ['nullable', 'string', 'max:150'],
            'ukuran_bangunan' => ['nullable', 'string', 'max:150'],
            'satuan' => ['required', 'string', 'max:50'],
            'volume' => ['required', 'numeric', 'gt:0'],
            'harga_satuan' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
