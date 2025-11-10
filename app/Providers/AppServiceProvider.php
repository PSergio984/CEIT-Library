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
        // Admin access gate (for backward compatibility and general admin check)
        Gate::define('Admin-access', function ($user) {
            return $user->hasAdminAccess();
        });

        // SUPER ADMIN ONLY GATES

        // User role management (Super Admin only)
        Gate::define('manage-user-roles', function ($user) {
            return $user->isSuperAdmin();
        });

        // System settings (Super Admin only)
        Gate::define('manage-system-settings', function ($user) {
            return $user->isSuperAdmin();
        });

        // ADMIN AND SUPER ADMIN GATES

        // Gate to check if user can assign librarian role (Admin or Super Admin)
        Gate::define('assign-librarian-role', function ($user) {
            return $user->hasAdminAccess();
        });

        // Gate for librarian-specific actions (not admin)
        Gate::define('librarian-only', function ($user) {
            return !$user->hasAdminAccess() && $user->isLibrarian();
        });

        // Gate to check if user can access privileged pages (Librarian or Admin)
        Gate::define('privileged-access', function ($user) {
            return $user->hasAdminAccess() || $user->isLibrarian();
        });

        // GRANULAR PERMISSIONS - Super Admin only gates

        // Academic Papers - VIEW (Librarian can view, but not edit/delete)
        Gate::define('view-academic-papers', function ($user) {
            return $user->hasAdminAccess() || $user->isLibrarian();
        });

        // Academic Papers - MANAGE (Super Admin only - edit/delete)
        Gate::define('manage-academic-papers', function ($user) {
            return $user->isSuperAdmin();
        });

        // Attendance logs (Super Admin only)
        Gate::define('view-attendance-logs', function ($user) {
            return $user->isSuperAdmin();
        });

        // Student management (Super Admin only)
        Gate::define('manage-students', function ($user) {
            return $user->isSuperAdmin();
        });

        // Librarian batches (Super Admin only)
        Gate::define('manage-librarian-batches', function ($user) {
            return $user->isSuperAdmin();
        });

        // Rules and Regulations - View only (Librarian can view)
        Gate::define('view-rules', function ($user) {
            return $user->hasAdminAccess() || $user->isLibrarian();
        });

        // Rules and Regulations - Edit (Super Admin only)
        Gate::define('manage-rules', function ($user) {
            return $user->isSuperAdmin();
        });

        // LIBRARIAN READ-ONLY ACCESS - Can view but not edit

        // Dashboard access (Librarian and Super Admin)
        Gate::define('access-admin-dashboard', function ($user) {
            return $user->hasAdminAccess() || $user->isLibrarian();
        });

        // Borrow logs - VIEW (Librarian can view)
        Gate::define('view-borrow-logs', function ($user) {
            return $user->hasAdminAccess() || $user->isLibrarian();
        });

        // Borrow logs - MANAGE (Super Admin only - edit/update status)
        Gate::define('manage-borrow-logs', function ($user) {
            return $user->isSuperAdmin();
        });

        // Violation logs - VIEW (Librarian and Super Admin)
        Gate::define('view-violation-logs', function ($user) {
            return $user->hasAdminAccess() || $user->isLibrarian();
        });

        // Violation logs - MANAGE (Super Admin only - add/edit/delete violations)
        Gate::define('manage-violation-logs', function ($user) {
            return $user->isSuperAdmin();
        });
    }
}
