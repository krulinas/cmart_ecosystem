<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureRole
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();

        if (!$user || !in_array($user->role, $roles, true)) {
            return response()->json([
                'message' => '403 Forbidden: The authenticated user does not have permission to access this resource.',
            ], 403);
        }

        return $next($request);
    }
}
