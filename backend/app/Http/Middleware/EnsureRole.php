<?php

namespace App\Http\Middleware;

use App\Support\ManagementRole;
use Closure;
use Illuminate\Http\Request;

class EnsureRole
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();

        if (!$user || !ManagementRole::userHasAnyRole($user->role, $roles)) {
            $managerRoles = ['manager', 'cmart_admin', 'boss', 'super_admin', ManagementRole::LEGACY_MANAGER];
            $requiresManager = !empty(array_intersect($roles, $managerRoles));

            $message = $requiresManager && ManagementRole::isStaffRole($user?->role)
                ? '403 Forbidden: Manager access required.'
                : '403 Forbidden: The authenticated user does not have permission to access this resource.';

            return response()->json(['message' => $message], 403);
        }

        return $next($request);
    }
}
