<?php

use App\Http\Middleware\AdminOnly;
use App\Http\Middleware\CheckCreditScore;
use App\Http\Middleware\LibrarianOrAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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

        // Add CheckCreditScore middleware to web group
        $middleware->web(append: [
            CheckCreditScore::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
