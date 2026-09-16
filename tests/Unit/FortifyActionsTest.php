<?php

namespace Tests\Unit;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FortifyActionsTest extends TestCase
{
    use RefreshDatabase;

    private const STRONG = 'Kuat#1234';

    public function test_create_new_user_success(): void
    {
        $user = (new CreateNewUser)->create([
            'name' => 'User Baru',
            'username' => 'user_baru',
            'password' => self::STRONG,
            'password_confirmation' => self::STRONG,
        ]);

        $this->assertTrue($user->exists);
        $this->assertTrue(Hash::check(self::STRONG, $user->password));
    }

    public function test_create_new_user_rejects_weak_password(): void
    {
        $this->expectException(ValidationException::class);

        (new CreateNewUser)->create([
            'name' => 'User Baru',
            'username' => 'user_baru',
            'password' => 'simple',
            'password_confirmation' => 'simple',
        ]);
    }

    public function test_reset_user_password(): void
    {
        $user = User::create(['name' => 'A', 'username' => 'reset_me', 'password' => bcrypt('old')]);

        (new ResetUserPassword)->reset($user, [
            'password' => self::STRONG,
            'password_confirmation' => self::STRONG,
        ]);

        $this->assertTrue(Hash::check(self::STRONG, $user->refresh()->password));
    }

    public function test_update_user_password_success_and_wrong_current(): void
    {
        $user = User::create(['name' => 'A', 'username' => 'upd_me', 'password' => Hash::make('#SidiptaKCD10')]);

        $this->actingAs($user);
        (new UpdateUserPassword)->update($user, [
            'current_password' => '#SidiptaKCD10',
            'password' => self::STRONG,
            'password_confirmation' => self::STRONG,
        ]);
        $this->assertTrue(Hash::check(self::STRONG, $user->refresh()->password));

        try {
            (new UpdateUserPassword)->update($user, [
                'current_password' => 'salah',
                'password' => self::STRONG,
                'password_confirmation' => self::STRONG,
            ]);
            $this->fail('expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('current_password', $e->errors());
            $this->assertEquals('updatePassword', $e->errorBag);
        }
    }

    public function test_update_profile_information(): void
    {
        $user = User::create(['name' => 'Lama', 'username' => 'prof_me', 'password' => bcrypt('x')]);

        (new UpdateUserProfileInformation)->update($user, ['name' => 'Baru']);

        $this->assertEquals('Baru', $user->refresh()->name);
    }
}
