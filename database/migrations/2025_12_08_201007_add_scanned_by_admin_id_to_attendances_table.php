<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add scanned_by_admin_id for cases when an admin (not a librarian on duty) scans QR.
     * This allows tracking who scanned the QR even when they don't have a librarian record.
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->foreignId('scanned_by_admin_id')
                ->nullable()
                ->after('scanned_by')
                ->constrained('users')
                ->onDelete('set null');

            $table->index('scanned_by_admin_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropForeign(['scanned_by_admin_id']);
            $table->dropIndex(['scanned_by_admin_id']);
            $table->dropColumn('scanned_by_admin_id');
        });
    }
};
