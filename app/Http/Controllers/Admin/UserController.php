<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Master\Sekolah;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        $users = User::with(['sekolah', 'roles'])->orderBy('name')->get();
        $sekolahs = Sekolah::orderBy('nama_sekolah')->select('id', 'nama_sekolah', 'kota_kab')->get();

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
            'password' => 'required|string|min:6',
            'sekolah_id' => 'nullable|uuid|exists:master_data_sekolah,id',
            'role' => 'required|string|in:admin_kcd,operator_sekolah',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'password' => $validated['password'],
            'sekolah_id' => $validated['sekolah_id'] ?? null,
        ]);

        $user->assignRole($validated['role']);

        return back()->with('success', 'User berhasil ditambahkan.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:150',
            'username' => "required|string|max:50|unique:users,username,{$user->id}",
            'password' => 'nullable|string|min:6',
            'sekolah_id' => 'nullable|uuid|exists:master_data_sekolah,id',
            'role' => 'nullable|string|in:admin_kcd,operator_sekolah,admin,user',
        ]);

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

        $user->update($data);

        if (! empty($validated['role'])) {
            $roleName = in_array($validated['role'], ['admin', 'admin_kcd'], true) ? 'admin_kcd' : 'operator_sekolah';
            $user->syncRoles([$roleName]);
        }

        return back()->with('success', 'Data user berhasil diperbarui!');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Anda tidak dapat menghapus akun Anda sendiri yang sedang digunakan!');
        }

        $user->delete();

        return back()->with('success', 'User berhasil dihapus!');
    }
}
