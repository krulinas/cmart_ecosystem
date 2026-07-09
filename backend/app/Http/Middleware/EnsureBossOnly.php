<?php

namespace App\Http\Middleware;

use App\Support\ManagementCapability;
use Closure;
use Illuminate\Http\Request;

class EnsureBossOnly
{
    /**
     * Restricts routes to Carboot operational analytics owners (manager/super_admin in Phase 1).
     * Legacy alias: "boss" middleware.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user || !ManagementCapability::canAccessCarbootOperationalAnalytics($user->role)) {
            return response()->json([
                'message' => '403 Forbidden: Carboot operational analytics access required.',
            ], 403);
        }

        return $next($request);
    }
}
