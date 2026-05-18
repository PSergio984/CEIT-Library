<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('
                UPDATE violation_transactions vt
                INNER JOIN violations v ON vt.violation_id = v.id
                SET vt.violation_penalty = v.penalty_score
            ');
        } elseif ($driver === 'pgsql') {
            DB::statement('
                UPDATE violation_transactions vt
                SET violation_penalty = v.penalty_score
                FROM violations v
                WHERE vt.violation_id = v.id
            ');
        } elseif ($driver === 'sqlite') {
            DB::statement('
                UPDATE violation_transactions
                SET violation_penalty = (
                    SELECT penalty_score FROM violations WHERE violations.id = violation_transactions.violation_id
                )
                WHERE violation_id IN (SELECT id FROM violations)
            ');
        } else {
            throw new RuntimeException("Unsupported database driver: $driver");
        }
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
