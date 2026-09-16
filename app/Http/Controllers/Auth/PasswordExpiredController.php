<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordExpiredController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Auth/ChangePassword');
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => [
                'required',
                'string',
                Password::min(8)->letters()->mixedCase()->numbers()->symbols(),
                'confirmed',
                'different:current_password',
            ],
        ], [
            'current_password.current_password' => 'Password saat ini tidak sesuai.',
            'password.different' => 'Password baru tidak boleh sama dengan password saat ini.',
            'password.confirmed' => 'Konfirmasi password baru tidak cocok.',
        ]);

        $user = $request->user();
        $user->forceFill([
            'password' => Hash::make($request->password),
            'password_changed_at' => now(),
        ])->save();

        activity('sistem')
            ->causedBy($user)
            ->performedOn($user)
            ->event('ganti-password')
            ->withProperties([
                'ringkasan' => 'Ganti password: '.$user->username,
                'sekolah_id' => $user->sekolah_id,
            ])
            ->log('ganti-password');

        return redirect()->route('dashboard')->with('success', 'Password berhasil diperbarui.');
    }
}
