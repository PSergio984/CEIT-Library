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
        Schema::create('thesis_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('thesis_id')->constrained()->onDelete('cascade');
            $table->foreignId('thesis_copy_id')->constrained('thesis_copies')->onDelete('cascade');
            $table->timestamp('time_in')->nullable();
            $table->timestamp('time_out')->nullable();
            $table->enum('status', ['requested', 'started', 'completed', 'expired', 'cancelled'])->default('requested');
            $table->timestamp('expires_at');
            $table->string('session_token', 64)->unique();
            $table->text('notes')->nullable();
            $table->integer('duration_minutes')->nullable(); // calculated field
            $table->timestamps();

            // Add indexes for better performance
            $table->index(['user_id', 'status']); // For user session queries
            $table->index(['thesis_id', 'status']); // For thesis availability checks
            $table->index('expires_at'); // For cleanup of expired sessions
            $table->index('session_token'); // For QR code scanning and session lookup
            $table->index(['status', 'time_in']); // For active session monitoring
            $table->index(['user_id', 'time_in', 'time_out']); // For user session history
            $table->index(['thesis_id', 'time_in']); // For thesis usage analytics
            $table->index(['status', 'expires_at']); // For expired session cleanup jobs
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('thesis_sessions');
    }
};
