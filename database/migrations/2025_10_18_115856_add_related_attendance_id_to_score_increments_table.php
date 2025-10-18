<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('score_increments', function (Blueprint $table) {
            // Add nullable foreign key to attendances for exact, indexed lookup
            // This replaces the inefficient LIKE search on description field
            // Using nullOnDelete instead of cascade so Eloquent observers run when cleaning up
            $table->foreignId('related_attendance_id')->nullable()->after('user_id')->constrained('attendances')->nullOnDelete();

            // Enforce idempotency: one reward per user+attendance
            $table->unique(['user_id', 'related_attendance_id'], 'score_increments_user_attendance_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('score_increments', function (Blueprint $table) {
            $table->dropForeign(['related_attendance_id']);
            $table->dropUnique('score_increments_user_attendance_unique');
            $table->dropColumn('related_attendance_id');
        });
    }
};
