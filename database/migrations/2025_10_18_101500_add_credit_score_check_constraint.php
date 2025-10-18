<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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
            // SQLite doesn't support adding constraints to existing tables
            // Would need to recreate the table
            DB::statement('PRAGMA foreign_keys=off');
            DB::statement('
                CREATE TABLE users_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    first_name VARCHAR NOT NULL,
                    last_name VARCHAR NOT NULL,
                    email VARCHAR NOT NULL UNIQUE,
                    email_verified_at DATETIME,
                    credit_score INTEGER DEFAULT 100 CHECK(credit_score >= 0 AND credit_score <= 100),
                    password VARCHAR NOT NULL,
                    is_admin INTEGER DEFAULT 0,
                    remember_token VARCHAR,
                    created_at DATETIME,
                    updated_at DATETIME
                )
            ');
            DB::statement('INSERT INTO users_new SELECT * FROM users');
            DB::statement('DROP TABLE users');
            DB::statement('ALTER TABLE users_new RENAME TO users');
            DB::statement('PRAGMA foreign_keys=on');
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
            // SQLite doesn't support dropping constraints easily
            // Would need to recreate the table without the constraint
        }
    }
};
