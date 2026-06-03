<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureBossOnly
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user || $user->role !== 'cmart_admin') {
            return response()->json([
                'message' => '403 Forbidden: Boss access required.',
            ], 403);
        }

        return $next($request);
    }
}
