<?php

namespace App\Http\Requests\PencatatanBm;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Models\PencatatanBm\InventarisUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Penyaluran = penempatan pertama. Ruang wajib dipilih karena penyaluran
 * tanpa lokasi tidak punya arti; penanggung jawab tetap opsional.
 */
class PenyaluranRequest extends FormRequest
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
        $sekolahId = $this->unit()->sekolah_id;

        return [
            'ruang_id' => [
                'required', 'uuid',
                Rule::exists('master_data_ruang', 'id')->where('sekolah_id', $sekolahId)->whereNull('deleted_at'),
            ],
            'pegawai_id' => [
                'nullable', 'uuid',
                Rule::exists('master_data_pegawai', 'id')->where('sekolah_id', $sekolahId)->whereNull('deleted_at'),
            ],
            'kondisi' => ['nullable', Rule::in(array_keys(InventarisUnit::KONDISI))],
            'tanggal' => ['required', 'date'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ruang_id.required' => 'Ruang tujuan wajib dipilih pada penyaluran.',
        ];
    }

    private function unit(): InventarisUnit
    {
        $unit = $this->route('unit');
        assert($unit instanceof InventarisUnit);

        return $unit;
    }
}
