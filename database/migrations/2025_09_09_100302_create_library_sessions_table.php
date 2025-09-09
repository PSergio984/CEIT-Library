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
        Schema::create('library_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamp('time_in')->nullable();
            $table->timestamp('time_out')->nullable();
            $table->enum('status', ['active', 'completed'])->default('active');
            $table->foreignId('scanned_by')->nullable()->constrained('librarians')->onDelete('set null'); // Librarian who scanned
            $table->integer('duration_minutes')->nullable(); // calculated field
            $table->timestamps();

            // Add indexes for better performance
            $table->index(['user_id', 'status']); // For checking if user is currently in library
            $table->index(['time_in', 'time_out']); // For duration calculations and reporting
            $table->index('status'); // For finding active sessions
            $table->index(['status', 'time_in']); // For active session monitoring
            $table->index(['user_id', 'time_in']); // For user visit history
            $table->index('scanned_by'); // For librarian activity tracking
            $table->index(['time_in', 'status']); // For daily/hourly library usage reports
            $table->index(['user_id', 'status', 'time_in']); // Composite for user active session lookup
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('library_sessions');
    }
};
