<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\v1\UserResource;
use App\Jobs\DispatchMailJob;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login with username, email, or phone_number.
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'login' => 'required|string|max:255',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:255',
        ]);

        $user = $this->findByLogin($validated['login']);

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return $this->jsonApiError(
                '401',
                'Unauthorized',
                'Login failed. The email address or password you provided is incorrect. Please double-check your credentials and try again.',
                401,
            );
        }

        $token = $user->createToken(
            $validated['device_name'] ?? 'api',
            ['*']
        );

        return response()->json([
            'data' => [
                'type' => 'tokens',
                'attributes' => [
                    'token' => $token->plainTextToken,
                    'tokenType' => 'Bearer',
                    'abilities' => $token->accessToken->abilities,
                ],
            ],
            'included' => [
                (new UserResource($user))->resolve(),
            ],
        ])->header('Content-Type', 'application/vnd.api+json');
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('sites'));
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }

    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json(null, 204);
    }

    /**
     * POST /api/v1/forgot-password
     *
     * Always 202 — does not reveal whether the login exists.
     * A reset email is sent only when the account has an email address.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login' => 'required|string|max:255',
        ]);

        $user = $this->findByLogin($validated['login']);

        $brokerStatus = null;
        DispatchMailJob::$lastVia = null;

        if ($user && filled($user->email)) {
            $brokerStatus = Password::sendResetLink(['email' => $user->email]);
            Log::info('Password reset requested', [
                'login' => $validated['login'],
                'email' => $user->email,
                'mailer' => config('mail.default'),
                'broker' => $brokerStatus,
                'via' => DispatchMailJob::$lastVia,
            ]);
        } else {
            Log::info('Password reset requested (no mail sent)', [
                'login' => $validated['login'],
                'reason' => $user ? 'user-has-no-email' : 'unknown-login',
            ]);
        }

        $attributes = [
            'status' => 'accepted',
            'detail' => 'If an account exists for that login, a password reset link has been sent.',
        ];

        if (config('app.debug')) {
            $attributes['mailer'] = config('mail.default');
            $attributes['brokerStatus'] = $brokerStatus;
            $attributes['queued'] = DispatchMailJob::$lastVia === 'rabbitmq';
            $attributes['via'] = DispatchMailJob::$lastVia;
            $attributes['queue'] = config('api.mail_queue', 'notifications');
            if ($brokerStatus === Password::RESET_THROTTLED) {
                $attributes['hint'] = 'Password broker throttled this login. Wait 60 seconds before requesting another reset email.';
            } elseif (DispatchMailJob::$lastVia === 'sync') {
                $attributes['hint'] = 'RabbitMQ publish failed; the mail was sent in-process and will not appear on the notifications queue.';
            } elseif (config('mail.default') === 'log') {
                $attributes['hint'] = 'MAIL_MAILER=log writes the message to storage/logs/laravel.log — it is not delivered to an inbox.';
            }
        }

        return response()->json([
            'data' => [
                'type' => 'password-resets',
                'id' => 'forgot',
                'attributes' => $attributes,
            ],
        ], 202)->header('Content-Type', 'application/vnd.api+json');
    }

    /**
     * POST /api/v1/reset-password
     *
     * Completes a reset using the token from the email (or the query string
     * on FRONTEND_URL/reset-password?token=&email=).
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $this->normalizePasswordConfirmation($request);

        $validated = $request->validate([
            'token' => 'required|string',
            'email' => 'required_without:login|nullable|email',
            'login' => 'required_without:email|nullable|string|max:255',
            'password' => ['required', 'string', 'confirmed', PasswordRule::defaults()],
        ]);

        $email = $validated['email'] ?? $this->findByLogin((string) ($validated['login'] ?? ''))?->email;

        if (! filled($email)) {
            throw ValidationException::withMessages([
                'email' => 'A valid email is required to reset the password.',
            ]);
        }

        $status = Password::reset(
            [
                'email' => $email,
                'password' => $validated['password'],
                'password_confirmation' => $request->input('password_confirmation', $validated['password']),
                'token' => $validated['token'],
            ],
            function (User $user, string $password): void {
                $user->resetToPassword($password, revokeTokens: true);
                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            $detail = match ($status) {
                Password::INVALID_TOKEN => 'This reset token is invalid or has expired.',
                Password::INVALID_USER => 'No account was found for that email.',
                Password::RESET_THROTTLED => 'Please wait before retrying.',
                default => __($status),
            };

            return $this->jsonApiError('422', 'Reset Failed', $detail, 422);
        }

        return response()->json([
            'data' => [
                'type' => 'password-resets',
                'id' => 'reset',
                'attributes' => [
                    'status' => 'reset',
                    'detail' => 'Password has been reset. Sign in with the new password.',
                ],
            ],
        ])->header('Content-Type', 'application/vnd.api+json');
    }

    /**
     * POST /api/v1/change-password  (authenticated)
     */
    public function changePassword(Request $request): JsonResponse
    {
        $this->normalizePasswordConfirmation($request);

        $validated = $request->validate([
            'currentPassword' => 'required|string',
            'password' => ['required', 'string', 'confirmed', PasswordRule::defaults()],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['currentPassword'], $user->password)) {
            throw ValidationException::withMessages([
                'currentPassword' => 'The current password is incorrect.',
            ]);
        }

        $currentId = $user->currentAccessToken()?->id;
        $user->resetToPassword($validated['password'], revokeTokens: false);

        if ($currentId) {
            $user->tokens()->where('id', '!=', $currentId)->delete();
        } else {
            $user->tokens()->delete();
        }

        return response()->json([
            'data' => [
                'type' => 'password-resets',
                'id' => 'changed',
                'attributes' => [
                    'status' => 'changed',
                    'detail' => 'Password updated. Other sessions have been signed out.',
                    'passwordChangedAt' => $user->fresh()?->password_changed_at?->toIso8601String(),
                ],
            ],
        ])->header('Content-Type', 'application/vnd.api+json');
    }

    private function findByLogin(string $login): ?User
    {
        return User::query()
            ->where(function ($query) use ($login) {
                $query->where('username', $login)
                    ->orWhere('email', $login)
                    ->orWhere('phone_number', $login);
            })
            ->first();
    }

    private function normalizePasswordConfirmation(Request $request): void
    {
        if ($request->filled('passwordConfirmation') && ! $request->filled('password_confirmation')) {
            $request->merge(['password_confirmation' => $request->input('passwordConfirmation')]);
        }
    }

    private function jsonApiError(string $status, string $title, string $detail, int $http): JsonResponse
    {
        return response()->json([
            'errors' => [[
                'status' => $status,
                'title' => $title,
                'detail' => $detail,
            ]],
        ], $http)->header('Content-Type', 'application/vnd.api+json');
    }
}
