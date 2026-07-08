<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TokenRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

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

        return response()->json([
            'token' => $user->createToken('api')->plainTextToken,
            'user' => new UserResource($user),
        ], 201);
    }

    public function destroy(Request $request): Response
    {
        $token = $request->user()->currentAccessToken();

        if($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->noContent();
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
