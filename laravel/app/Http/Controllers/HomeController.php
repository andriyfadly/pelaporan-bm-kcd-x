<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        return redirect()->route($request->user()->hasRole('admin') ? 'admin.dashboard' : 'dashboard');
    }
}
