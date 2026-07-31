<?php

namespace App\View\Components\Ui;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Currency extends Component
{
    public function __construct(public int|float|string|null $value = 0) {}

    public function render(): View|Closure|string
    {
        return view('components.ui.currency');
    }
}
