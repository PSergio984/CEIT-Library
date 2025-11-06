<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Admin access gate
        Gate::define('Admin-access', function ($user) {
            return $user->is_admin;
        });

        // Gate to check if user can assign librarian role (Admin only)
        Gate::define('assign-librarian-role', function ($user) {
            return $user->is_admin;
        });

        // Gate for librarian-specific actions (not admin)
        Gate::define('librarian-only', function ($user) {
            return !$user->is_admin && $user->isLibrarian();
        });

        // Gate to check if user can access privileged pages (Librarian or Admin)
        Gate::define('privileged-access', function ($user) {
            return $user->is_admin || $user->isLibrarian();
        });

        // GRANULAR PERMISSIONS - Admin only gates

        // Academic Papers management (Admin only)
        Gate::define('manage-academic-papers', function ($user) {
            return $user->is_admin;
        });

        // Attendance logs (Admin only)
        Gate::define('view-attendance-logs', function ($user) {
            return $user->is_admin;
        });

        // Student management (Admin only)
        Gate::define('manage-students', function ($user) {
            return $user->is_admin;
        });

        // Rules and Regulations - View (Librarian or Admin)
        Gate::define('view-rules', function ($user) {
            return $user->is_admin || $user->isLibrarian();
        });

        // Rules and Regulations - Edit (Admin only)
        Gate::define('manage-rules', function ($user) {
            return $user->is_admin;
        });

        // LIBRARIAN ALLOWED - These can be accessed by both Admin and Librarian

        // Dashboard access (Admin or Librarian)
        Gate::define('access-admin-dashboard', function ($user) {
            return $user->is_admin || $user->isLibrarian();
        });

        // Borrow logs (Admin or Librarian)
        Gate::define('view-borrow-logs', function ($user) {
            return $user->is_admin || $user->isLibrarian();
        });

        // Violation logs (Admin or Librarian)
        Gate::define('view-violation-logs', function ($user) {
            return $user->is_admin || $user->isLibrarian();
        });
    }
}
