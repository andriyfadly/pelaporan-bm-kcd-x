<?php

namespace App\Models\PencatatanBm;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Penomoran register atomic per (sekolah_id, kode_barang). Nomor yang sudah
 * terbit tidak pernah dipakai ulang.
 */
class InventarisRegisterSequence extends Model
{
    use HasUuids;

    protected $table = 'inventaris_register_sequences';

    protected $fillable = ['sekolah_id', 'kode_barang', 'next_number'];

    protected function casts(): array
    {
        return ['next_number' => 'integer'];
    }
}
