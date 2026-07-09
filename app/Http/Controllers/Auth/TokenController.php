<?php

namespace App\Http\Controllers\Auth;

use App\Events\UserStatusUpdated;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TokenRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

class TokenController extends Controller
{
    public function store(TokenRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        if ($user->role === UserRole::Client || ! $user->is_active) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($user->must_change_password) {
            return response()->json([
                'message' => 'You must reset your password using the invite link before signing in.',
            ], 403);
        }

        if ($user->status === 'offline') {
            $user->update(['status' => 'available']);
            $this->broadcastStatusUpdate((int) $user->id, 'available');
        }

        return response()->json([
            'token' => $user->createToken('api')->plainTextToken,
            'user' => new UserResource($user),
        ], 201);
    }

    public function destroy(Request $request): Response
    {
        $user = $request->user();
        $token = $user->currentAccessToken();

        if($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        if ($user->status !== 'offline') {
            $user->update(['status' => 'offline']);
            $this->broadcastStatusUpdate((int) $user->id, 'offline');
        }

        return response()->noContent();
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function updateStatus(Request $request): UserResource
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['available', 'busy', 'away'])],
        ]);

        $user = $request->user();
        $user->update([
            'status' => $validated['status'],
        ]);
        $this->broadcastStatusUpdate((int) $user->id, $validated['status']);

        return new UserResource($user->fresh());
    }

    private function broadcastStatusUpdate(int $userId, string $status): void
    {
        try {
            event(new UserStatusUpdated($userId, $status));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
