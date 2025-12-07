<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminOnly
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Check if user is authenticated
        if (! $user) {
            return redirect()->route('login');
        }

        // Allow access if user has admin access (admin or super_admin)
        if ($user->hasAdminAccess()) {
            return $next($request);
        }

        abort(403, 'Access denied. This page is restricted to Administrators only.');
    }
}
