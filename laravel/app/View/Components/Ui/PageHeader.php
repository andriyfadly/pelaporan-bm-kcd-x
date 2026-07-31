<?php

namespace App\View\Components\Ui;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class PageHeader extends Component
{
    public function __construct(public string $title, public ?string $description = null) {}

    public function render(): View|Closure|string
    {
        return view('components.ui.page-header');
    }
}
