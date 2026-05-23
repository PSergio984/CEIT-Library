<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class CheckCreditScore
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Share credit score status with all views
        if (Auth::check()) {
            $user = Auth::user();
            $hasZeroCreditScore = $user->credit_score < 1;
            View::share('hasZeroCreditScore', $hasZeroCreditScore);

            // Block access to specific routes if credit score is 0
            // Admins are exempt from credit score checks
            if ($hasZeroCreditScore && ! $user->hasAdminAccess()) {
                $restrictedRoutes = [
                    'academic-paper.index',
                    'academic-paper.show',
                    'test-qr',
                ];

                if ($request->routeIs($restrictedRoutes)) {
                    return redirect()->route('student.dashboard')->with('error', 'Your credit score is too low to access this resource. Please settle your violations.');
                }
            }
        }

        return $next($request);
    }
}
