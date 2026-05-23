<?php

use App\Http\Middleware\AdminOnly;
use App\Http\Middleware\CheckAccountStatus;
use App\Http\Middleware\CheckCreditScore;
use App\Http\Middleware\LibrarianOrAdmin;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Register custom middleware aliases
        $middleware->alias([
            'admin.only' => AdminOnly::class,
            'librarian.or.admin' => LibrarianOrAdmin::class,
        ]);

        // Add CheckCreditScore and CheckAccountStatus middleware to web group
        $middleware->web(append: [
            CheckCreditScore::class,
            CheckAccountStatus::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $e, Request $request) {
            if ($response->getStatusCode() === 403 && ! $request->expectsJson()) {
                $user = Auth::user();
                $redirectTo = $user ? route('student.dashboard') : route('login');

                return redirect($redirectTo)->with('mary.toast', [
                    'type' => 'warning',
                    'title' => 'Access Denied',
                    'description' => 'You do not have permission to access this page.',
                    'position' => 'toast-top toast-end',
                ]);
            }

            return $response;
        });
    })->create();
