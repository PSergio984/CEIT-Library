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
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_credit_score_check CHECK (credit_score >= 0 AND credit_score <= 100)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_credit_score_check');
    }
};
