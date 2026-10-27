<?php

namespace App\Http\Requests\PencatatanBm;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Models\PencatatanBm\InventarisUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MutasiRequest extends FormRequest
{
    use ResolvesSekolah;

    /**
     * Batas tenant diperiksa sebelum validasi: unit sekolah lain = 404,
     * bukan pesan validasi yang membocorkan keberadaan unit.
     */
    public function authorize(): bool
    {
        $sekolahPengguna = $this->resolveSekolahId($this);

        if ($sekolahPengguna !== null && $sekolahPengguna !== $this->unit()->sekolah_id) {
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
                'nullable', 'uuid',
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

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $ruang = $this->input('ruang_id');
            $pegawai = $this->input('pegawai_id');

            if ($ruang === null && $pegawai === null && $this->input('kondisi') === null) {
                $validator->errors()->add('ruang_id', 'Pilih ruang, pegawai, atau kondisi yang berubah.');
            }
        });
    }

    private function unit(): InventarisUnit
    {
        $unit = $this->route('unit');
        assert($unit instanceof InventarisUnit);

        return $unit;
    }
}
