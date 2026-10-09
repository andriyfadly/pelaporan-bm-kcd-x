<?php

namespace App\Http\Controllers\DistribusiBhp;

use App\Http\Controllers\Controller;
use App\Models\DistribusiBhp\Divisi;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DivisiController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $sekolahId = $user->sekolah_id;
        abort_unless((string) $sekolahId !== '', 403, 'Akun Anda belum terhubung ke sekolah mana pun.');

        $divisis = Divisi::where('sekolah_id', $sekolahId)
            ->with(['users' => fn ($q) => $q->select('id', 'name', 'username', 'divisi_id', 'is_active')])
            ->withCount('pengajuan')
            ->orderBy('nama')
            ->get();

        return Inertia::render('DistribusiBhp/Divisi/Index', [
            'divisis' => $divisis,
            'npsn' => $user->sekolah?->npsn,
        ]);
    }

    /**
     * Buat divisi + akun operator_divisi pertamanya dalam satu transaction.
     * Username selalu {npsn}-{suffix} dari NPSN sekolah user, tidak pernah
     * menerima prefix dari client.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $sekolahId = $user->sekolah_id;
        abort_unless((string) $sekolahId !== '', 403, 'Akun Anda belum terhubung ke sekolah mana pun.');

        $sekolah = $user->sekolah;
        abort_unless((string) $sekolah?->npsn !== '', 403, 'Sekolah Anda belum memiliki NPSN. Hubungi admin KCD untuk melengkapi data.');

        $validated = $request->validate([
            'nama_divisi' => 'required|string|max:150',
            'nama_operator' => 'required|string|max:150',
            'username_suffix' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            ],
            'password' => ['required', 'string', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        $username = $sekolah->npsn.'-'.$validated['username_suffix'];

        // NPSN sekolah lain tidak boleh dipakai: prefix ditentukan server.
        if (User::where('username', $username)->exists()) {
            throw ValidationException::withMessages([
                'username_suffix' => "Username {$username} sudah digunakan. Gunakan suffix lain.",
            ]);
        }

        DB::transaction(function () use ($validated, $sekolahId, $username) {
            $divisi = Divisi::firstOrCreate(
                ['sekolah_id' => $sekolahId, 'nama' => $validated['nama_divisi']],
            );

            $operator = activity()->withoutLogging(fn () => User::create([
                'name' => $validated['nama_operator'],
                'username' => $username,
                'password' => $validated['password'],
                'sekolah_id' => $sekolahId,
                'divisi_id' => $divisi->id,
                'password_changed_at' => null,
            ]));
            $operator->assignRole('operator_divisi');

            activity('sistem')
                ->performedOn($operator)
                ->event('tambah-operator-divisi')
                ->withProperties([
                    'ringkasan' => "Tambah operator divisi {$operator->username} untuk divisi {$divisi->nama}",
                    'sekolah_id' => $sekolahId,
                    'divisi_id' => $divisi->id,
                ])
                ->log('tambah-operator-divisi');

            return $divisi;
        });

        return back()->with('success', 'Divisi dan akun operator berhasil dibuat.');
    }

    /** Ubah nama divisi milik sekolah sendiri. */
    public function update(Request $request, Divisi $divisi): RedirectResponse
    {
        abort_unless($divisi->sekolah_id === $request->user()->sekolah_id, 403, 'Tidak memiliki akses ke divisi ini.');

        $validated = $request->validate([
            'nama_divisi' => 'required|string|max:150',
        ]);

        $divisi->update(['nama' => $validated['nama_divisi']]);

        activity('sistem')
            ->performedOn($divisi)
            ->event('ubah-divisi')
            ->withProperties([
                'ringkasan' => "Ubah divisi {$divisi->nama}",
                'sekolah_id' => $divisi->sekolah_id,
            ])
            ->log('ubah-divisi');

        return back()->with('success', 'Divisi berhasil diperbarui.');
    }

    /** Hapus divisi beserta seluruh akun operatornya. Ditolak bila masih ada pengajuan. */
    public function destroy(Request $request, Divisi $divisi): RedirectResponse
    {
        abort_unless($divisi->sekolah_id === $request->user()->sekolah_id, 403, 'Tidak memiliki akses ke divisi ini.');

        if ($divisi->pengajuan()->exists()) {
            return back()->with('error', 'Divisi tidak dapat dihapus karena masih memiliki pengajuan.');
        }

        $nama = $divisi->nama;

        DB::transaction(function () use ($divisi, $nama, $request) {
            $operatorIds = $divisi->users()->pluck('id');
            DB::table('sessions')->whereIn('user_id', $operatorIds)->delete();
            $divisi->users()->delete();
            $divisi->delete();

            activity('sistem')
                ->event('hapus-divisi')
                ->withProperties([
                    'ringkasan' => "Hapus divisi {$nama}",
                    'sekolah_id' => $request->user()->sekolah_id,
                ])
                ->log('hapus-divisi');
        });

        return back()->with('success', 'Divisi dan akun operatornya berhasil dihapus.');
    }

    /** Reset password akun operator divisi milik sekolah sendiri. */
    public function resetPassword(Request $request, User $operator): RedirectResponse
    {
        abort_unless($operator->divisi_id && $operator->sekolah_id === $request->user()->sekolah_id, 403, 'Tidak memiliki akses ke akun ini.');

        $validated = $request->validate([
            'password' => ['required', 'string', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        activity()->withoutLogging(fn () => $operator->update([
            'password' => $validated['password'],
            'password_changed_at' => null,
        ]));

        DB::table('sessions')->where('user_id', $operator->id)->delete();

        activity('sistem')
            ->performedOn($operator)
            ->event('reset-password-operator-divisi')
            ->withProperties([
                'ringkasan' => "Reset password operator divisi {$operator->username}",
                'sekolah_id' => $request->user()->sekolah_id,
            ])
            ->log('reset-password-operator-divisi');

        return back()->with('success', 'Password operator divisi berhasil direset.');
    }

    /** Aktif/nonaktifkan akun operator divisi milik sekolah sendiri. */
    public function toggleActive(Request $request, User $operator): RedirectResponse
    {
        abort_unless($operator->divisi_id && $operator->sekolah_id === $request->user()->sekolah_id, 403, 'Tidak memiliki akses ke akun ini.');

        if ($operator->id === $request->user()->id) {
            return back()->with('error', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
        }

        $operator->update(['is_active' => ! $operator->is_active]);

        if (! $operator->is_active) {
            DB::table('sessions')->where('user_id', $operator->id)->delete();
        }

        return back()->with('success', $operator->is_active ? 'Akun diaktifkan.' : 'Akun dinonaktifkan.');
    }
}
