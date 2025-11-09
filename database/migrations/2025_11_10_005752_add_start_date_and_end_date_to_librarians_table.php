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
        Schema::table('librarians', function (Blueprint $table) {
            // Add start_date and end_date for batch system
            $table->date('start_date')->nullable()->after('batch_no');
            $table->date('end_date')->nullable()->after('start_date');
            
            // Make expires_at nullable since we're using start_date/end_date now
            $table->timestamp('expires_at')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('librarians', function (Blueprint $table) {
            $table->dropColumn(['start_date', 'end_date']);
        });
    }
};
