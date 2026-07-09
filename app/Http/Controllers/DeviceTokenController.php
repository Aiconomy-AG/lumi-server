<?php

namespace App\Http\Controllers;

use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeviceTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['required', 'string', Rule::in(['android', 'ios'])],
        ]);

        $deviceToken = DeviceToken::query()->firstOrNew([
            'token' => $validated['token'],
        ]);

        $status = $deviceToken->exists ? 200 : 201;

        $deviceToken->fill([
            'user_id' => (int) $request->user()->id,
            'platform' => $validated['platform'],
        ])->save();

        return response()->json([
            'data' => [
                'id' => $deviceToken->id,
                'platform' => $deviceToken->platform,
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
}
