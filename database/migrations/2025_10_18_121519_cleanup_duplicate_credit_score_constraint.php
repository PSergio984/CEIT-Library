<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Remove the duplicate constraint created by the now-deleted migration file.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        try {
            if ($driver === 'mysql') {
                // MySQL: Drop the duplicate constraint if it exists
                DB::statement('ALTER TABLE users DROP CHECK users_credit_score_check');
            } elseif ($driver === 'pgsql') {
                // PostgreSQL: Drop the duplicate constraint if it exists
                DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_credit_score_check');
            }
            // SQLite: No action needed as the duplicate migration would have failed
        } catch (\Exception $e) {
            // Constraint might not exist, which is fine
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to recreate the duplicate constraint
    }
};
