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
            $table->foreignId('related_attendance_id')->nullable()->after('user_id')->constrained('attendances')->onDelete('cascade');

            // Add index for fast lookups when checking if attendance reward already exists
            $table->index(['user_id', 'related_attendance_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('score_increments', function (Blueprint $table) {
            $table->dropForeign(['related_attendance_id']);
            $table->dropIndex(['user_id', 'related_attendance_id']);
            $table->dropColumn('related_attendance_id');
        });
    }
};
