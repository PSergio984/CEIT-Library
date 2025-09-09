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
            $table->timestamp('time_in')->nullable();
            $table->timestamp('time_out')->nullable();
            $table->enum('status', ['requested', 'started', 'completed', 'expired', 'cancelled'])->default('requested');
            $table->timestamp('expires_at');
            $table->string('session_token', 64)->unique();
            $table->text('notes')->nullable();
            $table->integer('duration_minutes')->nullable(); // calculated field
            $table->timestamps();

            // Add indexes for better performance
            $table->index(['user_id', 'status']);
            $table->index(['thesis_id', 'status']);
            $table->index('expires_at');
            $table->index('session_token');
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
