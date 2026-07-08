<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsStaff
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ($user->role === UserRole::Client || ! $user->is_active) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
