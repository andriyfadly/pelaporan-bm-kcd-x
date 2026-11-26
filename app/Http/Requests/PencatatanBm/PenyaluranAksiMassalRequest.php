<?php

namespace App\Http\Requests\PencatatanBm;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Aksi per baris Buku Penyaluran (reset / ubah penerima) untuk seluruh unit
 * dalam satu baris. Baris UI adalah hasil pengelompokan backend, sehingga aksi
 * harus menyentuh semua unit, bukan hanya unit pertama.
 */
class PenyaluranAksiMassalRequest extends FormRequest
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
            'unit_ids' => ['required', 'array', 'min:1', 'max:500'],
            'unit_ids.*' => ['required', 'uuid', 'distinct', 'exists:inventaris_unit,id'],
            'ruang_id' => [
                Rule::requiredIf(fn (): bool => $this->routeIs('pencatatan-bm.penyaluran.penerima-massal')),
                'nullable', 'uuid',
                Rule::exists('master_data_ruang', 'id')->where('sekolah_id', $sekolahId)->whereNull('deleted_at'),
            ],
            'pegawai_id' => [
                'nullable', 'uuid',
                Rule::exists('master_data_pegawai', 'id')->where('sekolah_id', $sekolahId)->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ruang_id.required' => 'Pilih lokasi/ruang terlebih dahulu.',
        ];
    }
}
