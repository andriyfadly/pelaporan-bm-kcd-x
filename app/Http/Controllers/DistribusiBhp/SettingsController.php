<?php

namespace App\Http\Controllers\DistribusiBhp;

use App\Http\Controllers\Controller;
use App\Services\DistribusiBhp\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function edit(): Response
    {
        return Inertia::render('DistribusiBhp/Settings/Edit', [
            'settings' => $this->settings->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'format_nomor' => ['required', 'string', 'max:200'],
            'paper_size' => ['nullable', Rule::in(['a4', 'letter', 'foli/f4'])],
            'orientation' => ['nullable', Rule::in(['portrait', 'landscape'])],
        ]);

        $this->settings->update($validated);

        return back()->with('success', 'Settings berhasil disimpan.');
    }
}
