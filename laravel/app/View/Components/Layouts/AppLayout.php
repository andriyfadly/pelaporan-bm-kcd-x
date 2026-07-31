<?php

namespace App\View\Components\Layouts;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class AppLayout extends Component
{
    public function __construct(public string $title = 'Pelaporan Belanja Modal') {}

    public function render(): View|Closure|string
    {
        return view('components.layouts.app-layout');
    }
}
