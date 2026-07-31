<?php

namespace App\View\Components\Ui;

use Closure;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Pagination extends Component
{
    public function __construct(public Paginator $paginator) {}

    public function render(): View|Closure|string
    {
        return view('components.ui.pagination');
    }
}
