<?php

namespace App\Models\DistribusiBhp;

use App\Models\DistribusiBhp\Concerns\HasSumberDanaTable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $format_nomor
 * @property bool $is_singleton
 * @property string|null $format_nomor_nota_permintaan
 * @property string|null $format_nomor_surat_permintaan
 * @property string|null $format_nomor_surat_penyaluran
 * @property string|null $format_nomor_bast
 * @property string|null $format_nomor_bap
 * @property string|null $format_nomor_stok_opname
 * @property string|null $format_nomor_buku_pengeluaran
 * @property string|null $format_nomor_buku_penyaluran
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Settings extends Model
{
    use HasSumberDanaTable, HasUuids;

    public const JENIS_DOKUMEN = [
        'nota_permintaan',
        'surat_permintaan',
        'surat_penyaluran',
        'bast',
        'bap',
        'stok_opname',
        'buku_pengeluaran',
        'buku_penyaluran',
    ];

    public const DEFAULT_FORMAT = '{urut:3}/BHP/{kode}/{sumber_dana}/{bulan_romawi}/{tahun}';

    protected $fillable = [
        'format_nomor',
        'format_nomor_nota_permintaan',
        'format_nomor_surat_permintaan',
        'format_nomor_surat_penyaluran',
        'format_nomor_bast',
        'format_nomor_bap',
        'format_nomor_stok_opname',
        'format_nomor_buku_pengeluaran',
        'format_nomor_buku_penyaluran',
    ];

    /** Nama kolom untuk jenis dokumen tertentu. */
    public static function kolomJenis(string $jenis): string
    {
        return 'format_nomor_'.str_replace('-', '_', $jenis);
    }

    /**
     * Format nomor untuk jenis dokumen tertentu.
     * Jika belum diisi, fallback ke format_nomor (default umum).
     */
    public function getFormat(string $jenis): string
    {
        $kolom = self::kolomJenis($jenis);

        return $this->{$kolom} ?: ($this->format_nomor ?: self::DEFAULT_FORMAT);
    }

    public static function suffixTabel(): string
    {
        return 'settings';
    }
}
