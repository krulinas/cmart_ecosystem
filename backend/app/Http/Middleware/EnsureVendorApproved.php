<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureVendorApproved
{
    /**
     * Registered but intentionally not applied in Phase 1.
     * Pending community vendors must retain /dashboard access during onboarding.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user || $user->role !== 'community' || $user->vendor_status !== 'approved') {
            return response()->json([
                'message' => __('api.vendor_access_has_not_been_approved'),
            ], 403);
        }

        return $next($request);
    }
}
