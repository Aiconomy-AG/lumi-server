<?php

namespace App\Http\Controllers;

use App\Models\DeviceToken;
use App\Support\DeviceTokenPlatform;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DeviceTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['required', 'string', Rule::in([
                DeviceTokenPlatform::FCM_ANDROID,
                DeviceTokenPlatform::APNS_VOIP,
                DeviceTokenPlatform::WEB_PUSH,
                DeviceTokenPlatform::LEGACY_ANDROID,
                DeviceTokenPlatform::LEGACY_IOS,
            ])],
            'device_id' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $platform = DeviceTokenPlatform::normalize($validated['platform']);
        if (! DeviceTokenPlatform::isAllowed($platform)) {
            throw ValidationException::withMessages([
                'platform' => ['The selected platform is invalid.'],
            ]);
        }

        $deviceId = $validated['device_id'] ?? $validated['token'];

        $deviceToken = DeviceToken::query()->updateOrCreate(
            [
                'user_id' => (int) $request->user()->id,
                'platform' => $platform,
                'device_id' => $deviceId,
            ],
            [
                'token' => $validated['token'],
            ],
        );

        $status = $deviceToken->wasRecentlyCreated ? 201 : 200;

        return response()->json([
            'data' => [
                'id' => $deviceToken->id,
                'platform' => $deviceToken->platform,
                'device_id' => $deviceToken->device_id,
            ],
        ], $status);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);

        DeviceToken::query()
            ->where('user_id', $request->user()->id)
            ->where('token', $validated['token'])
            ->delete();

        return response()->json(status: 204);
    }

    public function destroyById(Request $request, int $deviceTokenId): JsonResponse
    {
        DeviceToken::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($deviceTokenId)
            ->delete();

        return response()->json(status: 204);
    }
}
