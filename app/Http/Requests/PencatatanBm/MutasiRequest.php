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
     * Mutasi berarti pindah lokasi, jadi tujuan wajib ada: minimal salah satu
     * dari ruang/pegawai. Koreksi kondisi saja punya jalurnya sendiri
     * (halaman detail unit), bukan lewat mutasi.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $sekolahId = $this->unit()->sekolah_id;

        return [
            'ruang_id' => [
                'nullable', 'required_without:pegawai_id', 'uuid',
                Rule::exists('master_data_ruang', 'id')->where('sekolah_id', $sekolahId)->whereNull('deleted_at'),
            ],
            'pegawai_id' => [
                'nullable', 'required_without:ruang_id', 'uuid',
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
            'ruang_id.required_without' => 'Mutasi wajib menentukan ruang atau penanggung jawab tujuan.',
            'pegawai_id.required_without' => 'Mutasi wajib menentukan ruang atau penanggung jawab tujuan.',
        ];
    }

    private function unit(): InventarisUnit
    {
        $unit = $this->route('unit');
        assert($unit instanceof InventarisUnit);

        return $unit;
    }
}
