<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function updatePhone(Request $request)
    {
        $validated = $request->validate([
            'phone_number' => ['required', 'string', 'max:20'],
        ]);

        $request->user()->update([
            'phone_number' => $validated['phone_number']
        ]);

        return response()->json([
            'message' => 'Phone number updated successfully',
            'phone_number' => $validated['phone_number']
        ]);
    }
}
