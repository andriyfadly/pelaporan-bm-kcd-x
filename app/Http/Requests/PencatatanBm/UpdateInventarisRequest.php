<?php

namespace App\Http\Requests\PencatatanBm;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Models\PencatatanBm\InventarisUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Edit satu unit dari daftar barang. Volume tidak bisa diubah per unit:
 * menambah/mengurangi jumlah unit harus lewat pendaftaran/penghapusan.
 */
class UpdateInventarisRequest extends FormRequest
{
    use ResolvesSekolah;

    public function authorize(): bool
    {
        $unit = $this->route('unit');

        if (! $unit instanceof InventarisUnit) {
            return false;
        }

        $sekolahPengguna = $this->resolveSekolahId($this);

        if ($sekolahPengguna !== null && $sekolahPengguna !== $unit->sekolah_id) {
            abort(404);
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $unit = $this->route('unit');
        assert($unit instanceof InventarisUnit);

        return [
            'kode_barang' => ['required', 'string', 'max:100'],
            'nama_barang' => ['required', 'string', 'max:255'],
            'merk_tipe' => ['nullable', 'string', 'max:255'],
            'bahan' => ['nullable', 'string', 'max:100'],
            'satuan' => ['nullable', 'string', 'max:50'],
            'harga_satuan' => ['nullable', 'numeric', 'min:0'],
            'nilai_perolehan_unit' => ['nullable', 'numeric', 'min:0'],
            'asal_perolehan' => ['nullable', 'string', 'max:150'],
            'no_sertifikat' => ['nullable', 'string', 'max:255'],
            'tanggal_perolehan' => ['required', 'date'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'kondisi' => ['nullable', Rule::in(array_keys(InventarisUnit::KONDISI))],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
