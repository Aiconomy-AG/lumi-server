<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PhoneNumberService;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function updatePhone(Request $request, PhoneNumberService $phoneNumbers)
    {
        $validated = $request->validate([
            'phone_number' => ['required', 'string', 'max:20'],
        ]);

        $phoneNumber = $phoneNumbers->normalize($validated['phone_number']);
        if ($phoneNumber === null) {
            return response()->json([
                'code' => 'INVALID_PHONE_NUMBER',
                'message' => 'Use an international phone number such as +40722123456.',
                'errors' => ['phone_number' => ['The phone number must be in international format.']],
            ], 422);
        }

        $request->user()->update([
            'phone_number' => $phoneNumber,
        ]);

        return response()->json([
            'message' => 'Phone number updated successfully',
            'phone_number' => $phoneNumber,
        ]);
    }
}
