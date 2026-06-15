<?php

namespace App\Http\Middleware;

use App\Support\ManagementRole;
use Closure;
use Illuminate\Http\Request;

class EnsureBossOnly
{
    /**
     * Restricts routes to CMart managers and HQ super admins.
     * Legacy alias: "boss" middleware.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user || !ManagementRole::canAccessManagerRoutes($user->role)) {
            return response()->json([
                'message' => '403 Forbidden: Manager access required.',
            ], 403);
        }

        return $next($request);
    }
}
