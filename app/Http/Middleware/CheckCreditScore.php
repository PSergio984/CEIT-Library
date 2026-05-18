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
            $hasZeroCreditScore = Auth::user()->credit_score < 1;
            View::share('hasZeroCreditScore', $hasZeroCreditScore);
        }

        return $next($request);
    }
}
