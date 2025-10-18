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
        Schema::table('violation_transactions', function (Blueprint $table) {
            // Add violation_penalty column to store the original penalty at the time of creation
            $table->integer('violation_penalty')->after('violation_id')->default(0);
        });

        // Backfill existing records with current penalty values
        DB::statement('
            UPDATE violation_transactions vt
            INNER JOIN violations v ON vt.violation_id = v.id
            SET vt.violation_penalty = v.penalty_score
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('violation_transactions', function (Blueprint $table) {
            $table->dropColumn('violation_penalty');
        });
    }
};
