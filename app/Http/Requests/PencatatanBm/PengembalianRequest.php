<?php

namespace App\Http\Requests\PencatatanBm;

use App\Models\PencatatanBm\InventarisUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Pengembalian satu baris Buku Penyaluran: banyak unit, satu Berita Acara.
 * Kepemilikan sekolah dan izin per unit dicek di controller karena unit baru
 * diketahui setelah query.
 */
class PengembalianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'unit_ids' => ['required', 'array', 'min:1', 'max:100'],
            'unit_ids.*' => ['required', 'uuid', 'exists:inventaris_unit,id'],
            'kondisi' => ['required', Rule::in(array_keys(InventarisUnit::KONDISI))],
            'tanggal' => ['required', 'date'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
