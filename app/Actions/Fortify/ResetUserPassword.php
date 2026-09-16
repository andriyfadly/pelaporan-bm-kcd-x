<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        activity()->withoutLogging(fn () => $user->forceFill([
            'password' => Hash::make($input['password']),
        ])->save());

        activity('sistem')
            ->causedByAnonymous()
            ->performedOn($user)
            ->event('reset-password')
            ->withProperties([
                'ringkasan' => 'Reset password: '.$user->username,
                'sekolah_id' => $user->sekolah_id,
            ])
            ->log('reset-password');
    }
}
