<?php

namespace App\Http\Middleware;

use App\Support\ManagementCapability;
use Closure;
use Illuminate\Http\Request;

class EnsureCapability
{
    /**
     * @param  string  ...$capabilities
     */
    public function handle(Request $request, Closure $next, ...$capabilities)
    {
        $user = $request->user();

        foreach ($capabilities as $capability) {
            if ($user && ManagementCapability::can($user->role, $capability)) {
                return $next($request);
            }
        }

        return response()->json([
            'message' => '403 Forbidden: Required management capability not granted.',
        ], 403);
    }
}
