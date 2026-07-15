<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Mail\UserInviteMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->has('role')) {
            $query->where('role', $request->query('role'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return UserResource::collection($query->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'role' => ['required', 'string', Rule::in(['admin', 'employee'])],
            'email' => ['required', 'email', 'unique:users,email'],
        ]);

        $temporaryPassword = Str::password(16);

        $user = User::create([
            'email' => strtolower(trim($validated['email'])),
            'role' => $validated['role'],
            'name' => Str::before($validated['email'], '@'),
            'password' => $temporaryPassword,
            'status' => 'offline',
            'phone_number' => '',
            'language_flag' => 'en',
            'is_active' => true,
            'must_change_password' => true,
        ]);

        $this->sendInvite($user, $temporaryPassword);

        AuditLog::record(
            module: 'users',
            action: 'create',
            entity: $user,
            label: $user->email,
            changes: ['new' => ['email' => $user->email, 'role' => $user->role->value ?? (string) $user->role]],
            description: 'User invited.',
        );

        return (new UserResource($user))
            ->response()
            ->setStatusCode(201);
    }

    public function show(int $userId): UserResource
    {
        return new UserResource(User::findOrFail($userId));
    }

    public function update(Request $request, int $userId): UserResource
    {
        $user = User::findOrFail($userId);

        $validated = $request->validate([
            'role' => ['sometimes', 'required', 'string', Rule::in(['admin', 'employee'])],
            'email' => ['sometimes', 'required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['sometimes', 'nullable', 'string', 'min:6'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'language_flag' => ['nullable', 'string', 'max:10'],
            'status' => ['sometimes', 'required', 'string', Rule::in(['available', 'busy', 'offline', 'away'])],
            'is_active' => ['sometimes', 'boolean'],
            'must_change_password' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('password', $validated) && ! $validated['password']) {
            unset($validated['password']);
        }

        $passwordChanged = array_key_exists('password', $validated);
        $originalRole = $user->role->value ?? (string) $user->role;
        $wasActive = $user->is_active;

        $oldOtherValues = [];
        $newOtherValues = [];
        foreach ($validated as $key => $value) {
            if (in_array($key, ['password', 'role', 'is_active'], true)) {
                continue;
            }
            $original = $user->getAttribute($key);
            if ($original != $value) {
                $oldOtherValues[$key] = $original;
                $newOtherValues[$key] = $value;
            }
        }

        $user->update($validated);

        $label = $user->email;

        if ($passwordChanged) {
            AuditLog::record(
                module: 'users',
                action: 'password_reset',
                entity: $user,
                label: $label,
                description: 'Password changed by admin.',
            );
        }

        if (array_key_exists('role', $validated) && ($user->role->value ?? (string) $user->role) !== $originalRole) {
            AuditLog::record(
                module: 'users',
                action: 'role_change',
                entity: $user,
                label: $label,
                changes: [
                    'old' => ['role' => $originalRole],
                    'new' => ['role' => $user->role->value ?? (string) $user->role],
                ],
            );
        }

        if (array_key_exists('is_active', $validated) && $wasActive === false && $user->is_active) {
            AuditLog::record(
                module: 'users',
                action: 'reactivate',
                entity: $user,
                label: $label,
                description: 'User reactivated.',
            );
        }

        if ($newOtherValues !== []) {
            AuditLog::record(
                module: 'users',
                action: 'update',
                entity: $user,
                label: $label,
                changes: ['old' => $oldOtherValues, 'new' => $newOtherValues],
            );
        }

        return new UserResource($user->fresh());
    }

    public function destroy(Request $request, int $userId)
    {
        $user = User::findOrFail($userId);

        if ($request->user()->id === $user->id) {
            return response()->json([
                'message' => 'You cannot deactivate your own account.',
            ], 422);
        }

        $user->update(['is_active' => false]);
        $user->tokens()->delete();

        AuditLog::record(
            module: 'users',
            action: 'deactivate',
            entity: $user,
            label: $user->email,
            description: 'User deactivated and tokens revoked.',
        );

        return response()->noContent();
    }

    public function resendInvite(int $userId)
    {
        $user = User::findOrFail($userId);
        if (! $user->must_change_password) {
            return response()->json([
                'message' => 'Invite can only be resent for users who have not reset their password yet.',
            ], 422);
        }
        $temporaryPassword = Str::password(16);
        $user->update(['password' => $temporaryPassword]);
        $this->sendInvite($user, $temporaryPassword);

        AuditLog::record(
            module: 'users',
            action: 'invite_resent',
            entity: $user,
            label: $user->email,
            description: 'Invite email resent with new temporary password.',
        );

        return response()->json(['message' => 'Invite resent successfully.']);
    }

    private function sendInvite(User $user, string $temporaryPassword): void
    {
        $token = Password::broker()->createToken($user);
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');
        $resetUrl = $frontendUrl.'/reset-password?token='.urlencode($token).'&email='.urlencode($user->email);
        Mail::to($user->email)->send(new UserInviteMail(
            user: $user,
            resetUrl: $resetUrl,
        ));
    }
}
