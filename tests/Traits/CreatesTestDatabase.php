<?php

namespace Tests\Traits;

use Illuminate\Support\Facades\Schema;

trait CreatesTestDatabase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create test tables without running migrations
        $this->createTestTables();
    }

    protected function createTestTables(): void
    {
        // Create users table
        if (!Schema::hasTable('users')) {
            Schema::create('users', function ($table) {
                $table->id();
                $table->string('first_name');
                $table->string('last_name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->rememberToken();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // Create academic_papers table (without fulltext index)
        if (!Schema::hasTable('academic_papers')) {
            Schema::create('academic_papers', function ($table) {
                $table->id();
                $table->string('catalog_code')->unique();
                $table->string('title');
                $table->year('publication_year');
                $table->string('paper_type');
                $table->string('research_project_adviser');
                $table->string('department');
                $table->string('dean');
                $table->timestamps();

                // Regular indexes only (no fulltext for SQLite)
                $table->index('department');
                $table->index('research_project_adviser');
                $table->index('title');
            });
        }

        // Create other essential tables for testing
        $this->createOtherTestTables();
    }

    protected function createOtherTestTables(): void
    {
        // Create authors table
        if (!Schema::hasTable('authors')) {
            Schema::create('authors', function ($table) {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });
        }

        // Create academic_paper_authors table
        if (!Schema::hasTable('academic_paper_authors')) {
            Schema::create('academic_paper_authors', function ($table) {
                $table->id();
                $table->foreignId('academic_paper_id')->constrained()->onDelete('cascade');
                $table->foreignId('author_id')->constrained()->onDelete('cascade');
                $table->timestamps();
            });
        }

        // Create inventories table
        if (!Schema::hasTable('inventories')) {
            Schema::create('inventories', function ($table) {
                $table->id();
                $table->foreignId('academic_paper_id')->constrained('academic_papers')->onDelete('cascade');
                $table->unsignedInteger('copy_number');
                $table->enum('status', ['Available', 'Reserved', 'Unavailable'])->default('Available');
                $table->timestamps();

                $table->unique(['academic_paper_id', 'copy_number']);
                $table->index('status');
            });
        }

        // Create borrow_transactions table
        if (!Schema::hasTable('borrow_transactions')) {
            Schema::create('borrow_transactions', function ($table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->foreignId('academic_paper_id')->constrained('academic_papers')->onDelete('cascade');
                $table->foreignId('inventory_id')->constrained('inventories')->onDelete('cascade');
                $table->timestamp('time_in')->nullable();
                $table->timestamp('time_out')->nullable();
                $table->enum('status', ['requested', 'started', 'completed', 'expired', 'cancelled'])->default('requested');
                $table->timestamp('expires_at');
                $table->string('session_token', 64)->unique();
                $table->text('notes')->nullable();
                $table->integer('duration_minutes')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'status']);
                $table->index(['academic_paper_id', 'status']);
                $table->index('expires_at');
                $table->index('session_token');
                $table->index(['status', 'time_in']);
                $table->index(['user_id', 'time_in', 'time_out']);
                $table->index(['academic_paper_id', 'time_in']);
                $table->index(['status', 'expires_at']);
            });
        }

        // Create librarians table (must be created before attendances due to foreign key)
        if (!Schema::hasTable('librarians')) {
            Schema::create('librarians', function ($table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('batch_no')->nullable(); // Changed to string to match migration
                $table->enum('status', ['active', 'inactive', 'expired'])->default('active');
                $table->timestamp('expires_at');
                $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamp('last_login_at')->nullable();
                $table->string('shift_notes')->nullable();
                $table->timestamps();

                $table->unique('user_id');
                $table->index(['status', 'expires_at']);
                $table->index('expires_at');
                $table->index(['status', 'last_login_at']);
                $table->index('created_by');
            });
        }

        // Create attendances table (after librarians due to foreign key constraint)
        if (!Schema::hasTable('attendances')) {
            Schema::create('attendances', function ($table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->timestamp('time_in')->nullable();
                $table->timestamp('time_out')->nullable();
                $table->enum('status', ['active', 'completed'])->default('active');
                $table->foreignId('scanned_by')->nullable()->constrained('librarians')->onDelete('set null');
                $table->integer('duration_minutes')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'status']);
                $table->index(['time_in', 'time_out']);
                $table->index('status');
                $table->index(['status', 'time_in']);
                $table->index(['user_id', 'time_in']);
                $table->index('scanned_by');
                $table->index(['time_in', 'status']);
                $table->index(['user_id', 'status', 'time_in']);
            });
        }

        // Create violations table
        if (!Schema::hasTable('violations')) {
            Schema::create('violations', function ($table) {
                $table->id();
                $table->string('name');
                $table->text('description');
                $table->integer('penalty_score');
                $table->timestamps();

                $table->index('penalty_score');
                $table->index('name');
            });
        }

        // Create violation_transactions table
        if (!Schema::hasTable('violation_transactions')) {
            Schema::create('violation_transactions', function ($table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->foreignId('violation_id')->constrained()->onDelete('cascade');
                $table->date('date_occurred');
                $table->enum('severity', ['Minor', 'Major', 'Critical']);
                $table->text('remarks')->nullable();
                $table->timestamps();

                // Add indexes for better performance
                $table->index(['user_id', 'date_occurred']); // For user violation history queries
                $table->index(['violation_id', 'date_occurred']); // For violation type reporting
                $table->index('severity'); // For filtering by severity level
                $table->index(['user_id', 'severity']); // For user-specific severity queries
                $table->index('date_occurred'); // For date-based reporting
            });
        }

        // Create password_reset_tokens table (for Laravel auth)
        if (!Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function ($table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        // Create personal_access_tokens table (for Laravel Sanctum)
        if (!Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function ($table) {
                $table->id();
                $table->morphs('tokenable');
                $table->string('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }
    }
}
