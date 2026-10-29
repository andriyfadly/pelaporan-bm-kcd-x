<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

class CatatLoginLogout
{
    public function handle(Login|Logout $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        $isLogin = $event instanceof Login;

        activity('sistem')
            ->causedBy($user)
            ->performedOn($user)
            ->event($isLogin ? 'login' : 'logout')
            ->withProperties([
                'ringkasan' => ($isLogin ? 'Masuk: ' : 'Keluar: ').$user->username,
                'sekolah_id' => $user->sekolah_id,
                'ip' => request()->ip(),
            ])
            ->log($isLogin ? 'login' : 'logout');
    }
}
