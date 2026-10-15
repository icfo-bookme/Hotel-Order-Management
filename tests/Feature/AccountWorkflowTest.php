<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AccountWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::create(['name' => 'Staff', 'email' => 'staff@example.test', 'password' => 'Old-password-123!']);
    }

    public function test_registration_login_and_logout(): void
    {
        $this->post('/register', ['name' => 'Staff', 'email' => 'staff@example.test', 'password' => 'Old-password-123!', 'password_confirmation' => 'Old-password-123!'])->assertRedirect('/dashboard');
        $user = User::firstOrFail();
        $this->assertTrue(Hash::check('Old-password-123!', $user->password));
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
        $this->post('/login', ['email' => $user->email, 'password' => 'Old-password-123!'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_failed_logins_are_rate_limited(): void
    {
        Event::fake([Lockout::class]);
        $user = $this->user();
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        Event::assertDispatched(Lockout::class);
        $this->assertGuest();
    }

    public function test_profile_password_confirmation_and_password_update(): void
    {
        $user = $this->user();
        $this->actingAs($user)->patch('/profile', ['name' => 'Updated', 'email' => $user->email])->assertSessionHas('status', 'profile-updated');
        $this->assertSame('Updated', $user->fresh()->name);
        $this->post('/confirm-password', ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->post('/confirm-password', ['password' => 'Old-password-123!'])->assertSessionHas('auth.password_confirmed_at');
        $this->put('/password', ['current_password' => 'wrong', 'password' => 'New-password-123!', 'password_confirmation' => 'New-password-123!'])->assertSessionHasErrorsIn('updatePassword', 'current_password');
        $this->put('/password', ['current_password' => 'Old-password-123!', 'password' => 'New-password-123!', 'password_confirmation' => 'New-password-123!'])->assertSessionHas('status', 'password-updated');
        $this->assertTrue(Hash::check('New-password-123!', $user->fresh()->password));
    }

    public function test_password_reset_link_and_reset(): void
    {
        Notification::fake();
        $user = $this->user();
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class);
        $token = Password::createToken($user);
        $this->post('/reset-password', ['email' => $user->email, 'token' => $token, 'password' => 'New-password-123!', 'password_confirmation' => 'New-password-123!'])->assertRedirect('/login');
        $this->assertTrue(Hash::check('New-password-123!', $user->fresh()->password));
    }

    public function test_account_deletion_validates_password(): void
    {
        $user = $this->user();
        $this->actingAs($user)->delete('/profile', ['password' => 'wrong'])->assertSessionHasErrorsIn('userDeletion', 'password');
        $this->assertNotNull($user->fresh());
        $this->delete('/profile', ['password' => 'Old-password-123!'])->assertRedirect('/');
        $this->assertGuest();
        $this->assertNull($user->fresh());
    }
}
