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
            $message = count($roles) === 1 && $roles[0] === 'cmart_admin'
                ? '403 Forbidden: Boss access required.'
                : '403 Forbidden: The authenticated user does not have permission to access this resource.';

            return response()->json(['message' => $message], 403);
        }

        return $next($request);
    }
}
