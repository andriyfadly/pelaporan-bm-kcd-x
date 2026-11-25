<?php

namespace App\Http\Requests\PencatatanBm;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use Illuminate\Foundation\Http\FormRequest;

/**
 * BAST untuk banyak unit dalam satu baris Buku Penyaluran. Tenant dicek di
 * controller (semua unit harus satu sekolah dengan pengguna).
 */
class BastBarisRequest extends FormRequest
{
    use ResolvesSekolah;

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
            'unit_ids' => ['required', 'array', 'min:1'],
            'unit_ids.*' => ['required', 'uuid', 'exists:inventaris_unit,id'],
            'nomor' => ['nullable', 'string', 'max:150'],
            'tanggal' => ['nullable', 'date'],
        ];
    }
}
