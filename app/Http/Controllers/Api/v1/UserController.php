<?php

namespace App\Http\Controllers\Api\v1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\v1\UserResource;
use App\Jobs\DispatchMailJob;
use App\Jobs\SendWelcomeUserEmail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;

class UserController extends Controller
{
    #[Authorize('viewAny', User::class)]
    public function index(Request $request)
    {
        $query = User::query()
            ->accessibleBy($request->user());

        if ($role = $request->input('filter.role')) {
            $query->where('role', $role);
        }

        if ($search = $request->input('filter.search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        if ($siteId = $request->input('filter.site')) {
            $query->whereHas('sites', fn($q) => $q->where('sites.id', $siteId));
        }

        $users = $query->latest()->paginate($request->input('page.size', 15));

        return UserResource::collection($users);
    }

    #[Authorize('create', User::class)]
    public function store(Request $request): UserResource
    {
        $validated = $request->validate([
            'data.attributes.name' => 'required|string|max:255',
            'data.attributes.username' => 'required|string|max:64|unique:users,username',
            'data.attributes.email' => 'nullable|email|unique:users,email',
            'data.attributes.phoneNumber' => 'required|string|max:32|unique:users,phone_number',
            'data.attributes.password' => ['required', 'string', PasswordRule::defaults()],
            'data.attributes.role' => ['required', Rule::enum(UserRole::class)],
            'data.attributes.siteIds' => 'nullable|array',
            'data.attributes.siteIds.*' => 'uuid|exists:sites,id',
        ]);

        $attrs = $validated['data']['attributes'];

        // Only admin can create another admin
        if (($attrs['role'] ?? null) === UserRole::Admin->value && !$request->user()->isAdmin()) {
            abort(403, 'Only administrators can create admin users.');
        }

        $user = User::create([
            'name' => $attrs['name'],
            'username' => $attrs['username'],
            'email' => $attrs['email'] ?? null,
            'phone_number' => $attrs['phoneNumber'],
            'password' => $attrs['password'],
            'role' => $attrs['role'],
            'password_changed_at' => now(),
        ]);

        if (!empty($attrs['siteIds'])) {
            $sync = [];
            foreach ($attrs['siteIds'] as $siteId) {
                $sync[$siteId] = ['is_active' => true];
            }
            $user->allSites()->sync($sync);
        }

        if (filled($user->email)) {
            DispatchMailJob::pushAuth(new SendWelcomeUserEmail($user));
        }

        return new UserResource($user->load('sites'));
    }

    #[Authorize('view', 'user')]
    public function show(User $user): UserResource
    {
        return new UserResource($user->load('sites'));
    }

    #[Authorize('update', 'user')]
    public function update(Request $request, User $user): UserResource
    {
        $isSelf = $request->user()->id === $user->id;
        $canManage = $request->user()->canManageUsers();

        $rules = [
            'data.attributes.name' => 'sometimes|string|max:255',
            'data.attributes.username' => 'sometimes|string|max:64|unique:users,username,' . $user->id,
            'data.attributes.email' => 'nullable|email|unique:users,email,' . $user->id,
            'data.attributes.phoneNumber' => 'sometimes|string|max:32|unique:users,phone_number,' . $user->id,
            'data.attributes.password' => ['sometimes', 'string', PasswordRule::defaults()],
        ];

        if ($canManage && !$isSelf) {
            $rules['data.attributes.role'] = ['sometimes', Rule::enum(UserRole::class)];
            $rules['data.attributes.siteIds'] = 'nullable|array';
            $rules['data.attributes.siteIds.*'] = 'uuid|exists:sites,id';
        }

        $validated = $request->validate($rules);
        $attrs = $validated['data']['attributes'] ?? [];

        if (isset($attrs['role'])) {
            if ($attrs['role'] === UserRole::Admin->value && !$request->user()->isAdmin()) {
                abort(403, 'Only administrators can assign the admin role.');
            }
        }

        $data = array_filter([
            'name' => $attrs['name'] ?? null,
            'username' => $attrs['username'] ?? null,
            'email' => array_key_exists('email', $attrs) ? $attrs['email'] : null,
            'phone_number' => $attrs['phoneNumber'] ?? null,
            'role' => $attrs['role'] ?? null,
        ], fn($v) => $v !== null);

        // Allow clearing email
        if (array_key_exists('email', $attrs)) {
            $data['email'] = $attrs['email'];
        }

        if (!empty($attrs['password'])) {
            $data['password'] = $attrs['password'];
            $data['password_changed_at'] = now();
        }

        $user->update($data);

        if ($canManage && array_key_exists('siteIds', $attrs)) {
            $sync = [];
            foreach ($attrs['siteIds'] ?? [] as $siteId) {
                $sync[$siteId] = ['is_active' => true];
            }
            $user->allSites()->sync($sync);
        }

        return new UserResource($user->fresh()->load('sites'));
    }

    #[Authorize('delete', 'user')]
    public function destroy(Request $request, User $user)
    {
        if ($request->user()->id === $user->id) {
            abort(403, 'You cannot delete your own account.');
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json(null, 204);
    }

    /**
     * Assign sites to a user.
     */
    #[Authorize('manageSites', 'user')]
    public function syncSites(Request $request, User $user)
    {
        $validated = $request->validate([
            'data.attributes.siteIds' => 'required|array',
            'data.attributes.siteIds.*' => 'uuid|exists:sites,id',
        ]);

        $sync = [];
        foreach ($validated['data']['attributes']['siteIds'] as $siteId) {
            $sync[$siteId] = ['is_active' => true];
        }

        $user->allSites()->sync($sync);

        return new UserResource($user->fresh()->load('sites'));
    }

    /**
     * POST /api/v1/users/{user}/reset-password
     *
     * Admin / user-manager: set a new password directly, or email a reset link.
     */
    #[Authorize('resetPassword', 'user')]
    public function resetPassword(Request $request, User $user): JsonResponse|UserResource
    {
        $validated = $request->validate([
            'data.attributes.password' => ['required_without:data.attributes.sendEmail', 'nullable', 'string', PasswordRule::defaults()],
            'data.attributes.sendEmail' => 'sometimes|boolean',
            'data.attributes.revokeTokens' => 'sometimes|boolean',
        ]);

        $attrs = $validated['data']['attributes'] ?? [];
        $sendEmail = (bool) ($attrs['sendEmail'] ?? false);

        if ($sendEmail) {
            if (! filled($user->email)) {
                return response()->json([
                    'errors' => [[
                        'status' => '422',
                        'title' => 'Validation Error',
                        'detail' => 'This user has no email address; set a password directly.',
                    ]],
                ], 422)->header('Content-Type', 'application/vnd.api+json');
            }

            Password::sendResetLink(['email' => $user->email]);

            return response()->json([
                'data' => [
                    'type' => 'password-resets',
                    'id' => 'sent',
                    'attributes' => [
                        'status' => 'sent',
                        'detail' => 'A password reset link was sent to the user.',
                    ],
                ],
            ], 202)->header('Content-Type', 'application/vnd.api+json');
        }

        $user->resetToPassword(
            $attrs['password'],
            (bool) ($attrs['revokeTokens'] ?? true),
        );

        return new UserResource($user->fresh()->load('sites'));
    }
}
