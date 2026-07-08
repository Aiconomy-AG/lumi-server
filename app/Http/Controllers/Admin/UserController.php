<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
            'password' => ['required', 'string', 'min:6'],
            'name' => ['required', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'language_flag' => ['nullable', 'string', 'max:10'],
            'status' => ['sometimes', 'string', Rule::in(['available', 'busy', 'offline', 'away'])],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['status'] ??= 'offline';
        $validated['phone_number'] ??= '';
        $validated['language_flag'] ??= 'en';
        $validated['is_active'] ??= true;

        $user = User::create($validated);

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
        ]);

        if (array_key_exists('password', $validated) && ! $validated['password']) {
            unset($validated['password']);
        }

        $user->update($validated);

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

        return response()->noContent();
    }
}
