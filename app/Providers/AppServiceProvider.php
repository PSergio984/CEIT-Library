<?php

namespace App\Providers;

use App\Models\AcademicPaper;
use App\Models\Author;
use App\Models\Dean;
use App\Models\ResearchAdviser;
use App\Models\RuleHeader;
use App\Models\RuleRegulation;
use App\Models\TechnicalAdviser;
use App\Observers\AcademicPaperObserver;
use App\Observers\PeopleNameObserver;
use App\Observers\RulebookObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        // AI sidecar index sync observers — catalog/policy edits queue a
        // debounced rebuild; deletions queue an immediate rebuild (D-10/D-11).
        AcademicPaper::observe(AcademicPaperObserver::class);
        Author::observe(PeopleNameObserver::class);
        ResearchAdviser::observe(PeopleNameObserver::class);
        TechnicalAdviser::observe(PeopleNameObserver::class);
        Dean::observe(PeopleNameObserver::class);
        RuleHeader::observe(RulebookObserver::class);
        RuleRegulation::observe(RulebookObserver::class);

        // Define Rate Limiters
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(Str::transliterate(Str::lower($request->email)).'|'.$request->ip());
        });

        RateLimiter::for('qr-scanning', function (Request $request) {
            return $this->rateLimitForUser($request, 300, 30);
        });

        RateLimiter::for('search', function (Request $request) {
            return $this->rateLimitForUser($request, 500, 60);
        });

        RateLimiter::for('transactions', function (Request $request) {
            return $this->rateLimitForUser($request, 200, 20);
        });

        // Admin access gate (for backward compatibility and general admin check)
        Gate::define('Admin-access', function ($user) {
            return $user->hasAdminAccess();
        });

        // SUPER ADMIN ONLY GATES

        // User role management - Only Super Admin can promote users to Admin
        Gate::define('manage-user-roles', function ($user) {
            return $user->isSuperAdmin();
        });

        // Promote users to admin role (Super Admin only)
        Gate::define('promote-to-admin', function ($user) {
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
            return $user->isLibrarian() && ! $user->hasAdminAccess();
        });

        // Gate to check if user can access privileged pages (Librarian or Admin)
        Gate::define('privileged-access', function ($user) {
            return $user->hasAdminAccess() || $user->isLibrarian();
        });

        // Alias for privileged-access (used in routes)
        Gate::define('librarian-or-admin-access', function ($user) {
            return $user->hasAdminAccess() || $user->isLibrarian();
        });

        // GRANULAR PERMISSIONS

        // Academic Papers - VIEW (Librarian, Admin, and Super Admin can view)
        Gate::define('view-academic-papers', function ($user) {
            return $user->hasLibrarianOrAdminAccess();
        });

        // Academic Papers - MANAGE (Admin and Super Admin - edit/delete)
        Gate::define('manage-academic-papers', function ($user) {
            return $user->hasAdminAccess();
        });

        // Attendance logs (Librarian, Admin, and Super Admin)
        Gate::define('view-attendance-logs', function ($user) {
            return $user->hasLibrarianOrAdminAccess();
        });

        // Student management (Admin and Super Admin)
        Gate::define('manage-students', function ($user) {
            return $user->hasAdminAccess();
        });

        // Librarian batches (Admin and Super Admin)
        Gate::define('manage-librarian-batches', function ($user) {
            return $user->hasAdminAccess();
        });

        // Rules and Regulations - View (Librarian, Admin, Super Admin)
        Gate::define('view-rules', function ($user) {
            return $user->hasLibrarianOrAdminAccess();
        });

        // Rules and Regulations - Edit (Admin and Super Admin)
        Gate::define('manage-rules', function ($user) {
            return $user->hasAdminAccess();
        });

        // LIBRARIAN READ-ONLY ACCESS - Can view but not edit

        // Dashboard access (Librarian, Admin, and Super Admin)
        Gate::define('access-admin-dashboard', function ($user) {
            return $user->hasLibrarianOrAdminAccess();
        });

        // Borrow logs - VIEW (Librarian, Admin, Super Admin)
        Gate::define('view-borrow-logs', function ($user) {
            return $user->hasLibrarianOrAdminAccess();
        });

        // Borrow logs - MANAGE (Admin and Super Admin - edit/update status)
        Gate::define('manage-borrow-logs', function ($user) {
            return $user->hasAdminAccess();
        });

        // Violation logs - VIEW (Librarian, Admin, Super Admin)
        Gate::define('view-violation-logs', function ($user) {
            return $user->hasLibrarianOrAdminAccess();
        });

        // Violation logs - MANAGE (Admin and Super Admin - add/edit/delete violations)
        Gate::define('manage-violation-logs', function ($user) {
            return $user->hasAdminAccess();
        });

        // Advisers & Deans management (Super Admin only)
        Gate::define('manage-advisers-deans', function ($user) {
            return $user->isSuperAdmin();
        });

        if (app()->environment('production')) {
            $this->app['request']->server->set('HTTPS', true);
        }
    }

    /**
     * Helper to determine rate limit based on user role.
     */
    protected function rateLimitForUser(Request $request, int $staffLimit, int $studentLimit)
    {
        $user = $request->user();
        $isStaff = $user ? ($user->isLibrarian() || $user->hasAdminAccess()) : false;
        $limit = $isStaff ? $staffLimit : $studentLimit;

        return Limit::perMinute($limit)->by($user?->id ?: $request->ip());
    }
}
