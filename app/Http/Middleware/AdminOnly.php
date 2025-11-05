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
        // Check if user is authenticated and is an admin
        if (!$request->user()) {
            return redirect()->route('login');
        }

        if (!$request->user()->is_admin) {
            abort(403, 'Access denied. This action requires administrator privileges.');
        }

        return $next($request);
    }
}
