<?php

namespace App\View\Components\Ui;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class StatusBadge extends Component
{
    public function __construct(public string $status) {}

    public function classes(): string
    {
        return match ($this->status) {
            'Disetujui' => 'bg-emerald-100 text-emerald-800',
            'Menunggu Approval' => 'bg-amber-100 text-amber-800',
            'Ditolak' => 'bg-rose-100 text-rose-800',
            default => 'bg-slate-100 text-slate-800',
        };
    }

    public function render(): View|Closure|string
    {
        return view('components.ui.status-badge');
    }
}
