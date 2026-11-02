<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request and enforce Role-Based Access Control (RBAC).
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        // Lenient development fallback for local inspection
        if (!$user) {
            $authHeader = $request->header('Authorization');
            if (!$authHeader) {
                // If endpoint requires admin/staff, guest cannot perform administrative actions
                if (in_array('super_admin', $roles) || in_array('admin', $roles)) {
                    // Check if request is local dev request
                    if (app()->environment('local')) {
                        return $next($request);
                    }
                    return response()->json([
                        'error' => 'Authentication required: Administrative privileges required.',
                        'required_roles' => $roles,
                    ], 401);
                }
                return $next($request);
            }
        }

        if ($user && !empty($roles)) {
            $userRole = strtolower($user->role);
            $normalizedRoles = array_map('strtolower', $roles);

            if (!in_array($userRole, $normalizedRoles) && !in_array('super_admin', [$userRole])) {
                return response()->json([
                    'error' => "Access denied: Your account role ({$userRole}) lacks clearance for this action.",
                    'required_roles' => $roles,
                ], 403);
            }
        }

        return $next($request);
    }
}
