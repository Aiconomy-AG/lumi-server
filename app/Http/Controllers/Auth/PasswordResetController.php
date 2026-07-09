<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    public function validateToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
        ]);

        $email = strtolower(trim($validated['email']));
        $user = User::where('email', $email)->first();

        if (! $user || ! Password::broker()->tokenExists($user, $validated['token'])) {
            return response()->json(['message' => 'Invalid or expired reset link.'], 422);
        }

        return response()->json([
            'message' => 'Token is valid.',
            'data' => [
                'email' => $user->email,
                'name' => $user->name ?? '',
                'phone_number' => $user->phone_number ?? '',
                'language_flag' => $user->language_flag ?? 'en',
            ],
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'name' => ['required', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'language_flag' => ['nullable', 'string', 'max:10'],
        ]);

        $email = strtolower(trim($validated['email']));
        $phone = $validated['phone_number'] ?? '';
        $language = $validated['language_flag'] ?? 'en';

        $status = Password::broker()->reset(
            [
                'email' => $email,
                'token' => $validated['token'],
                'password' => $validated['password'],
                'password_confirmation' => $request->input('password_confirmation'),
            ],
            function (User $user, string $password) use ($validated, $phone, $language): void {
                $user->forceFill([
                    'password' => $password,
                    'must_change_password' => false,
                    'name' => $validated['name'],
                    'phone_number' => $phone,
                    'language_flag' => $language,
                ])->save();

                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json(['message' => 'Password has been reset successfully.']);
    }
}