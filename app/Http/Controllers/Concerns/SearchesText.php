<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

trait SearchesText
{
    /**
     * Pola pencarian "mengandung". Wildcard milik user di-escape supaya tidak
     * menjadi wildcard liar: input '%' tidak boleh mencocokkan semua baris.
     */
    protected function likePattern(string $term): string
    {
        return '%'.addcslashes(trim($term), '%_\\').'%';
    }

    /**
     * Cocokkan $term ke salah satu kolom (OR).
     *
     * Wajib memakai whereLike(), bukan where(..., 'like', ...): PostgreSQL
     * mengompilasi whereLike() menjadi ILIKE, sedangkan 'like' peka huruf
     * besar/kecil sementara data sekolah tersimpan HURUF BESAR. SQLite
     * (engine test) membuang perbedaan ini, jadi bug hanya muncul di produksi.
     *
     * @param  array<int, string>  $columns
     */
    protected function whereAnyLike(EloquentBuilder|QueryBuilder $query, array $columns, string $term): void
    {
        $pattern = $this->likePattern($term);

        $query->where(function (EloquentBuilder|QueryBuilder $q) use ($columns, $pattern): void {
            foreach ($columns as $column) {
                $q->orWhereLike($column, $pattern);
            }
        });
    }
}
