<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Master\Sekolah;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        $users = User::whereDoesntHave('roles', function ($q) {
            $q->where('name', 'super_admin');
        })->with(['sekolah', 'roles'])->orderBy('name')->get();
        $sekolahs = Sekolah::orderBy('nama_sekolah')->select('id', 'nama_sekolah', 'kota_kab', 'npsn')->get();

        return Inertia::render('Admin/User/Index', [
            'users' => $users,
            'sekolahs' => $sekolahs,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'username' => 'required|string|max:50|unique:users,username',
            'password' => ['required', 'string', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
            'sekolah_id' => 'nullable|uuid|exists:master_data_sekolah,id',
            'role' => 'required|string|in:admin_kcd,operator_sekolah,bendahara_sekolah',
        ]);

        $user = activity()->withoutLogging(fn () => User::create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'password' => $validated['password'],
            'sekolah_id' => $validated['sekolah_id'] ?? null,
        ]));

        $user->assignRole($validated['role']);

        activity('sistem')
            ->performedOn($user)
            ->event('tambah-user')
            ->withProperties([
                'ringkasan' => "Tambah user {$user->username} ({$validated['role']})",
                'sekolah_id' => $user->sekolah_id,
                'role' => $validated['role'],
            ])
            ->log('tambah-user');

        return back()->with('success', 'User berhasil ditambahkan.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        if ($user->hasRole('super_admin')) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:150',
            'username' => "required|string|max:50|unique:users,username,{$user->id}",
            'password' => ['nullable', 'string', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
            'sekolah_id' => 'nullable|uuid|exists:master_data_sekolah,id',
            'role' => 'nullable|string|in:admin_kcd,operator_sekolah,bendahara_sekolah',
            'is_active' => 'nullable|boolean',
        ]);

        $menonaktifkan = array_key_exists('is_active', $validated) && ! (bool) $validated['is_active'];

        if ($menonaktifkan && $user->id === auth()->id()) {
            return back()->with('error', 'Anda tidak dapat menonaktifkan akun Anda sendiri!');
        }

        $data = [
            'username' => $validated['username'],
        ];

        if (! empty($validated['name'])) {
            $data['name'] = $validated['name'];
        }

        if (array_key_exists('sekolah_id', $validated)) {
            $data['sekolah_id'] = $validated['sekolah_id'];
        }

        if (! empty($validated['password'])) {
            $data['password'] = $validated['password'];
        }

        if (array_key_exists('is_active', $validated)) {
            $data['is_active'] = (bool) $validated['is_active'];
        }

        activity()->withoutLogging(fn () => $user->update($data));

        // Nonaktif = putus semua sesi login yang sedang berjalan.
        if ($menonaktifkan) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        if (! empty($validated['role'])) {
            $roleName = match ($validated['role']) {
                'admin', 'admin_kcd' => 'admin_kcd',
                'bendahara_sekolah' => 'bendahara_sekolah',
                default => 'operator_sekolah',
            };
            $user->syncRoles([$roleName]);
        }

        $ringkasan = "Ubah user {$user->username}"
            .(! empty($validated['role']) ? " ({$validated['role']})" : '')
            .(array_key_exists('is_active', $validated) ? ($validated['is_active'] ? ' [diaktifkan]' : ' [dinonaktifkan]') : '');

        activity('sistem')
            ->performedOn($user)
            ->event('ubah-user')
            ->withProperties([
                'ringkasan' => $ringkasan,
                'sekolah_id' => $user->sekolah_id,
                'role' => $validated['role'] ?? null,
                'is_active' => array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : null,
            ])
            ->log('ubah-user');

        return back()->with('success', 'Data user berhasil diperbarui!');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->hasRole('super_admin')) {
            abort(403);
        }

        if ($user->id === auth()->id()) {
            return back()->with('error', 'Anda tidak dapat menghapus akun Anda sendiri yang sedang digunakan!');
        }

        $username = $user->username;
        $sekolahId = $user->sekolah_id;
        activity()->withoutLogging(fn () => $user->delete());

        activity('sistem')
            ->event('hapus-user')
            ->withProperties([
                'ringkasan' => "Hapus user {$username}",
                'sekolah_id' => $sekolahId,
                'username' => $username,
            ])
            ->log('hapus-user');

        return back()->with('success', 'User berhasil dihapus!');
    }
}
