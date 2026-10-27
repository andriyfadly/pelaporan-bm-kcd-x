<?php

namespace App\Services\PencatatanBm;

/**
 * Hasil satu putaran sinkronisasi realisasi → unit inventaris.
 */
final readonly class SyncResult
{
    public function __construct(
        public int $dibuat = 0,
        public int $tetap = 0,
        public int $surplus = 0,
        public int $perluReview = 0,
    ) {}

    public function adaPerubahan(): bool
    {
        return $this->dibuat > 0 || $this->surplus > 0 || $this->perluReview > 0;
    }
}
