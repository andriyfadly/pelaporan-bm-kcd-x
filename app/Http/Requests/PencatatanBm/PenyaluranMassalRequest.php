<?php

namespace App\Http\Requests\PencatatanBm;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Simpan massal Buku Penyaluran: satu request membawa banyak baris, masing-masing
 * satu rentang register. Tenant dicek per unit di controller (semua unit harus
 * satu sekolah dengan pengguna).
 */
class PenyaluranMassalRequest extends FormRequest
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
        $sekolahId = $this->resolveSekolahIdWajib($this);

        return [
            'baris' => ['required', 'array', 'min:1'],
            'baris.*.unit_ids' => ['required', 'array', 'min:1'],
            'baris.*.unit_ids.*' => ['required', 'uuid', 'exists:inventaris_unit,id'],
            'baris.*.ruang_id' => [
                'required', 'uuid',
                Rule::exists('master_data_ruang', 'id')->where('sekolah_id', $sekolahId)->whereNull('deleted_at'),
            ],
            'baris.*.pegawai_id' => [
                'nullable', 'uuid',
                Rule::exists('master_data_pegawai', 'id')->where('sekolah_id', $sekolahId)->whereNull('deleted_at'),
            ],
            'baris.*.vol' => ['nullable', 'integer', 'min:1', 'max:500'],
            'baris.*.keterangan' => ['nullable', 'string', 'max:1000'],
            'baris.*.tanggal' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'baris.*.ruang_id.required' => 'Pilih lokasi/ruang terlebih dahulu.',
        ];
    }
}
