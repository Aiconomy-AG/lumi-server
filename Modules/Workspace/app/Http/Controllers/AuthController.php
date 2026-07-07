<?php
namespace Modules\Workspace\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Http\Resources\EmployeeResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json(['code' => 'UNAUTHORIZED', 'message' => 'Authentication is required or invalid.'], 401);
        }

        // Generate Sanctum Bearer token
        $token = $user->createToken('workspace_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'employee' => new EmployeeResource($user),
        ]);
    }

    public function profile(Request $request)
    {
        return new EmployeeResource(Auth::user());
    }
}
