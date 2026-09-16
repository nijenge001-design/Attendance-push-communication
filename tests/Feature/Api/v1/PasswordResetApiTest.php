<?php

declare(strict_types=1);

namespace Tests\Feature\Api\v1;

use App\Enums\UserRole;
use App\Jobs\SendPasswordChangedEmail;
use App\Jobs\SendResetPasswordEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithApi;
use Tests\TestCase;

final class PasswordResetApiTest extends TestCase
{
    use InteractsWithApi;
    use RefreshDatabase;

    public function test_forgot_password_is_always_accepted(): void
    {
        Queue::fake();

        $this->postJsonApi('/api/v1/forgot-password', [
            'login' => 'nobody@example.com',
        ])->assertStatus(202)
            ->assertJsonPath('data.type', 'password-resets');

        Queue::assertNotPushed(SendResetPasswordEmail::class);
    }

    public function test_forgot_password_emails_existing_user(): void
    {
        Queue::fake();

        $user = $this->makeUser([
            'username' => 'resetme',
            'email' => 'resetme@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->postJsonApi('/api/v1/forgot-password', [
            'login' => 'resetme',
        ])->assertStatus(202);

        Queue::assertPushed(SendResetPasswordEmail::class, function (SendResetPasswordEmail $job) use ($user) {
            return $job->user->is($user);
        });
    }

    public function test_reset_password_with_valid_token(): void
    {
        Queue::fake();

        $user = $this->makeUser([
            'email' => 'token.reset@example.com',
            'password' => bcrypt('old-password'),
        ]);
        $user->createToken('keep');

        $token = Password::broker()->createToken($user);

        $this->postJsonApi('/api/v1/reset-password', [
            'token' => $token,
            'email' => 'token.reset@example.com',
            'password' => 'NewPassw0rd!',
            'passwordConfirmation' => 'NewPassw0rd!',
        ])->assertOk()
            ->assertJsonPath('data.attributes.status', 'reset');

        $this->assertTrue(Hash::check('NewPassw0rd!', $user->fresh()->password));
        $this->assertSame(0, $user->fresh()->tokens()->count());
        $this->assertNotNull($user->fresh()->password_changed_at);
        Queue::assertPushed(SendPasswordChangedEmail::class);
    }

    public function test_reset_password_rejects_bad_token(): void
    {
        $this->makeUser(['email' => 'bad.token@example.com']);

        $this->postJsonApi('/api/v1/reset-password', [
            'token' => 'not-a-real-token',
            'email' => 'bad.token@example.com',
            'password' => 'NewPassw0rd!',
            'passwordConfirmation' => 'NewPassw0rd!',
        ])->assertStatus(422);
    }

    public function test_change_password_requires_current_password(): void
    {
        $user = $this->actingAsApiUser();
        $user->forceFill(['password' => 'password'])->save();

        $this->postJsonApi('/api/v1/change-password', [
            'currentPassword' => 'wrong',
            'password' => 'NewPassw0rd!',
            'passwordConfirmation' => 'NewPassw0rd!',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['currentPassword']);
    }

    public function test_change_password_updates_and_keeps_current_token(): void
    {
        $user = $this->makeUser(['password' => bcrypt('password')]);
        $user->createToken('old-device');

        $plain = $user->createToken('this-device')->plainTextToken;

        $this->withHeaders(array_merge($this->apiHeaders(), [
            'Authorization' => 'Bearer '.$plain,
        ]))->postJson('/api/v1/change-password', [
            'currentPassword' => 'password',
            'password' => 'NewPassw0rd!',
            'passwordConfirmation' => 'NewPassw0rd!',
        ])->assertOk()
            ->assertJsonPath('data.attributes.status', 'changed');

        $this->assertTrue(Hash::check('NewPassw0rd!', $user->fresh()->password));
        $this->assertSame(1, $user->fresh()->tokens()->count());
    }

    public function test_admin_can_set_another_users_password(): void
    {
        $this->actingAsApiUser();
        $target = $this->makeUser([
            'username' => 'target',
            'role' => UserRole::Operator,
            'password' => bcrypt('password'),
        ]);

        $this->postJsonApi('/api/v1/users/'.$target->id.'/reset-password', [
            'data' => [
                'attributes' => [
                    'password' => 'ForcedPass1!',
                    'revokeTokens' => true,
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('data.type', 'users');

        $this->assertTrue(Hash::check('ForcedPass1!', $target->fresh()->password));
    }

    public function test_admin_cannot_use_admin_reset_on_self(): void
    {
        $admin = $this->actingAsApiUser();

        $this->postJsonApi('/api/v1/users/'.$admin->id.'/reset-password', [
            'data' => [
                'attributes' => [
                    'password' => 'ForcedPass1!',
                ],
            ],
        ])->assertForbidden();
    }

    public function test_guest_cannot_change_password(): void
    {
        $this->postJsonApi('/api/v1/change-password', [
            'currentPassword' => 'x',
            'password' => 'NewPassw0rd!',
            'passwordConfirmation' => 'NewPassw0rd!',
        ])->assertUnauthorized();
    }
}
