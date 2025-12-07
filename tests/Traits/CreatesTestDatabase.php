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
        // Create roles table first (required for users foreign key)
        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function ($table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('display_name');
                $table->text('description')->nullable();
                $table->timestamps();
            });

            // Insert default roles
            \DB::table('roles')->insert([
                ['name' => 'student', 'display_name' => 'Student', 'description' => 'Regular student user', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'librarian', 'display_name' => 'Librarian', 'description' => 'Librarian with QR scanning access', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'admin', 'display_name' => 'Admin', 'description' => 'Administrator with full system access', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'super_admin', 'display_name' => 'Super Admin', 'description' => 'Super administrator with complete system access', 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        // Create deans, research_advisers, technical_advisers (before academic_papers)
        if (! Schema::hasTable('deans')) {
            Schema::create('deans', function ($table) {
                $table->id();
                $table->string('name')->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('research_advisers')) {
            Schema::create('research_advisers', function ($table) {
                $table->id();
                $table->string('name')->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('technical_advisers')) {
            Schema::create('technical_advisers', function ($table) {
                $table->id();
                $table->string('name')->unique();
                $table->timestamps();
            });
        }

        // Create users table
        if (! Schema::hasTable('users')) {
            Schema::create('users', function ($table) {
                $table->id();
                $table->string('first_name');
                $table->string('last_name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->foreignId('role_id')->default(1)->constrained('roles')->onDelete('restrict');
                $table->integer('credit_score')->default(100);
                $table->string('account_status')->default('active');
                $table->rememberToken();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // Create academic_papers table (without fulltext index)
        if (! Schema::hasTable('academic_papers')) {
            Schema::create('academic_papers', function ($table) {
                $table->id();
                $table->string('catalog_code')->unique();
                $table->string('title');
                $table->year('publication_year');
                $table->string('paper_type');
                $table->foreignId('research_adviser_id')->nullable()->constrained('research_advisers')->nullOnDelete();
                $table->foreignId('technical_adviser_id')->nullable()->constrained('technical_advisers')->nullOnDelete();
                $table->string('department');
                $table->foreignId('dean_id')->nullable()->constrained('deans')->nullOnDelete();
                // Legacy fields for backward compatibility
                $table->string('research_project_adviser')->nullable();
                $table->string('dean')->nullable();
                $table->timestamps();

                // Regular indexes only (no fulltext for SQLite)
                $table->index('department');
                $table->index('title');
            });
        }

        // Create other essential tables for testing
        $this->createOtherTestTables();
    }

    protected function createOtherTestTables(): void
    {
        // Create authors table
        if (! Schema::hasTable('authors')) {
            Schema::create('authors', function ($table) {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });
        }

        // Create academic_paper_authors table
        if (! Schema::hasTable('academic_paper_authors')) {
            Schema::create('academic_paper_authors', function ($table) {
                $table->id();
                $table->foreignId('academic_paper_id')->constrained()->onDelete('cascade');
                $table->foreignId('author_id')->constrained()->onDelete('cascade');
                $table->timestamps();
            });
        }

        // Create inventories table
        if (! Schema::hasTable('inventories')) {
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
        if (! Schema::hasTable('borrow_transactions')) {
            Schema::create('borrow_transactions', function ($table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->foreignId('academic_paper_id')->constrained('academic_papers')->onDelete('cascade');
                $table->foreignId('inventory_id')->constrained('inventories')->onDelete('cascade');
                $table->timestamp('time_in')->nullable();
                $table->timestamp('time_out')->nullable();
                $table->enum('status', ['started', 'completed', 'overdue'])->default('started');
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
        if (! Schema::hasTable('librarians')) {
            Schema::create('librarians', function ($table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->integer('batch_no')->nullable();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->enum('status', ['active', 'inactive', 'expired'])->default('inactive');
                $table->timestamp('expires_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamp('last_login_at')->nullable();
                $table->string('shift_notes')->nullable();
                $table->timestamps();

                $table->unique('user_id');
                $table->index('batch_no');
                $table->index(['start_date', 'end_date']);
                $table->index('status');
                $table->index('created_by');
            });
        }

        // Create attendances table (after librarians due to foreign key constraint)
        if (! Schema::hasTable('attendances')) {
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
        if (! Schema::hasTable('violations')) {
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
        if (! Schema::hasTable('violation_transactions')) {
            Schema::create('violation_transactions', function ($table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->foreignId('violation_id')->constrained()->onDelete('cascade');
                $table->integer('violation_penalty')->default(0); // Store penalty at time of creation
                $table->timestamp('date_occurred');
                $table->text('remarks')->nullable();
                $table->foreignId('attendance_id')->nullable()->constrained('attendances')->onDelete('set null');
                $table->timestamps();

                // Add indexes for better performance
                $table->index(['user_id', 'date_occurred']); // For user violation history queries
                $table->index(['violation_id', 'date_occurred']); // For violation type reporting
                $table->index('user_id'); // For user-specific severity queries
                $table->index('date_occurred'); // For date-based reporting
            });
        }

        // Create catalog_sequences table (for academic paper catalog codes)
        if (! Schema::hasTable('catalog_sequences')) {
            Schema::create('catalog_sequences', function ($table) {
                $table->id();
                $table->string('sequence_key')->unique()->comment('Format: DEPT_CODE-YEAR (e.g., IT-25, CE-24)');
                $table->unsignedInteger('last_sequence')->default(0)->comment('Last sequence number used');
                $table->timestamps();

                $table->index('sequence_key');
            });
        }

        // Create rule_headers table
        if (! Schema::hasTable('rule_headers')) {
            Schema::create('rule_headers', function ($table) {
                $table->id();
                $table->string('title'); // "I. General Information"
                $table->integer('order')->default(0); // For sorting the headers
                $table->timestamps();
            });
        }

        // Create password_reset_tokens table (for Laravel auth)
        if (! Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function ($table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        // Create personal_access_tokens table (for Laravel Sanctum)
        if (! Schema::hasTable('personal_access_tokens')) {
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

        // Create notifications table
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function ($table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('type');
                $table->string('title');
                $table->text('message');
                $table->json('data')->nullable();
                $table->boolean('is_read')->default(false);
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'is_read']);
                $table->index('created_at');
            });
        }
    }
}
