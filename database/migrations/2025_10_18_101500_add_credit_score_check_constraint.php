<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add CHECK constraint to ensure credit_score is between 0 and 100
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE users ADD CONSTRAINT credit_score_range CHECK (credit_score >= 0 AND credit_score <= 100)');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users ADD CONSTRAINT credit_score_range CHECK (credit_score >= 0 AND credit_score <= 100)');
        } elseif ($driver === 'sqlite') {
            // SQLite doesn't support adding constraints to existing tables.
            // Rebuilding the table is risky as it can drop foreign keys, indexes, and triggers.
            // The application-level clamping in ScoreIncrement::updateUserCreditScoreAtomic()
            // provides protection, so we skip the constraint for SQLite in dev/test environments.
            // For fresh databases, the constraint should be added during initial table creation.
            // This is a no-op for SQLite to avoid destructive schema changes.
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE users DROP CHECK credit_score_range');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT credit_score_range');
        } elseif ($driver === 'sqlite') {
            // No-op for SQLite (constraint was not added)
        }
    }
};
