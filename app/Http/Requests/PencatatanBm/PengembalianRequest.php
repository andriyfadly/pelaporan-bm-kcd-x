<?php

namespace App\Http\Requests\PencatatanBm;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Models\PencatatanBm\InventarisUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PengembalianRequest extends FormRequest
{
    use ResolvesSekolah;

    public function authorize(): bool
    {
        $unit = $this->route('unit');
        assert($unit instanceof InventarisUnit);

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
        return [
            'kondisi' => ['required', Rule::in(array_keys(InventarisUnit::KONDISI))],
            'tanggal' => ['required', 'date'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
