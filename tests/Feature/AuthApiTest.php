<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedUser(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->forceFill([
            'is_verified' => true,
            'role' => $attributes['role'] ?? 'seeker',
        ])->save();

        return $user;
    }

    public function test_login_rejects_invalid_credentials_and_unverified_accounts(): void
    {
        $verified = $this->verifiedUser(['email' => 'verified@example.com']);
        $unverified = User::factory()->create([
            'email' => 'unverified@example.com',
            'password' => 'Password123',
        ]);
        $unverified->forceFill(['is_verified' => false])->save();

        $this->postJson('/api/auth/login', [
            'email' => $verified->email,
            'password' => 'wrong-password',
        ])->assertUnauthorized();

        $this->postJson('/api/auth/login', [
            'email' => $unverified->email,
            'password' => 'Password123',
        ])->assertForbidden()->assertJsonPath('status', 'unverified');
    }

    public function test_verified_user_can_login_and_access_protected_endpoint(): void
    {
        $user = $this->verifiedUser([
            'email' => 'login@example.com',
            'password' => 'Password123',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Password123',
        ])->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'email', 'role']]);

        $this->withToken($response->json('token'))
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_guest_cannot_access_protected_endpoints(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
        $this->getJson('/api/seeker/profile')->assertUnauthorized();
        $this->getJson('/api/employer/jobs')->assertUnauthorized();
        $this->getJson('/api/admin/companies')->assertUnauthorized();
    }

    public function test_forgot_password_response_does_not_reveal_account_existence(): void
    {
        Notification::fake();
        $user = $this->verifiedUser(['email' => 'reset@example.com']);

        $existingResponse = $this->postJson('/api/auth/forgot-password', ['email' => $user->email]);
        $missingResponse = $this->postJson('/api/auth/forgot-password', ['email' => 'missing@example.com']);

        $existingResponse->assertOk()->assertJsonPath('status', 'success');
        $missingResponse->assertOk()->assertExactJson($existingResponse->json());
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_valid_reset_token_changes_password_and_revokes_tokens(): void
    {
        $user = $this->verifiedUser([
            'email' => 'token-reset@example.com',
            'password' => 'OldPassword123',
        ]);
        $user->createToken('existing-device');
        $token = Password::createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewPassword123', $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_invalid_reset_token_does_not_change_password(): void
    {
        $user = $this->verifiedUser([
            'email' => 'invalid-reset@example.com',
            'password' => 'OldPassword123',
        ]);

        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => 'invalid-token',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertUnprocessable();

        $this->assertTrue(Hash::check('OldPassword123', $user->fresh()->password));
    }

    public function test_change_password_requires_current_password_and_revokes_all_tokens(): void
    {
        $user = $this->verifiedUser(['password' => 'OldPassword123']);
        $token = $user->createToken('current-device')->plainTextToken;
        $user->createToken('other-device');

        $this->withToken($token)->putJson('/api/auth/password', [
            'current_password' => 'wrong-password',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertUnprocessable();
        $this->assertSame(2, $user->tokens()->count());

        $this->withToken($token)->putJson('/api/auth/password', [
            'current_password' => 'OldPassword123',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewPassword123', $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_logout_revokes_only_current_token_and_logout_all_revokes_every_token(): void
    {
        $user = $this->verifiedUser();
        $firstToken = $user->createToken('first-device')->plainTextToken;
        $secondToken = $user->createToken('second-device')->plainTextToken;

        $this->withToken($firstToken)->postJson('/api/auth/logout')->assertOk();
        $this->assertSame(1, $user->tokens()->count());

        $this->withToken($secondToken)->postJson('/api/auth/logout-all')->assertOk();
        $this->assertSame(0, $user->tokens()->count());
    }
}
