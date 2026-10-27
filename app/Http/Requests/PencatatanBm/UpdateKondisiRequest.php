<?php

namespace App\Http\Requests\PencatatanBm;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Models\PencatatanBm\InventarisUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKondisiRequest extends FormRequest
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
            // Ruang/pegawai wajib milik sekolah unit (isolasi tenant di lapisan validasi).
            'kondisi' => ['required', Rule::in(array_keys(InventarisUnit::KONDISI))],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'ruang_id' => [
                'nullable', 'uuid',
                Rule::exists('master_data_ruang', 'id')->where('sekolah_id', $this->unit()->sekolah_id),
            ],
            'pegawai_id' => [
                'nullable', 'uuid',
                Rule::exists('master_data_pegawai', 'id')->where('sekolah_id', $this->unit()->sekolah_id),
            ],
        ];
    }

    private function unit(): InventarisUnit
    {
        $unit = $this->route('unit');
        assert($unit instanceof InventarisUnit);

        return $unit;
    }
}
