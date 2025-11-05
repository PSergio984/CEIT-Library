<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LibrarianOrAdmin
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
        if (!$user) {
            abort(403, 'Access denied. Authentication required.');
        }

        // Allow access if user is admin or has active librarian role
        if ($user->is_admin || $user->isLibrarian()) {
            return $next($request);
        }

        abort(403, 'Access denied. This page is restricted to Librarians and Administrators only.');
    }
}
