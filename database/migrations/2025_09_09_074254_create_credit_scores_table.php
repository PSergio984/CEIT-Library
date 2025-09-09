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
        Schema::create('credit_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('score')->default(75);
            $table->timestamps();

            // Add indexes for better performance
            $table->unique('user_id'); // Ensure one score per user and fast user lookups
            $table->index('score'); // For score-based filtering and ranking
            $table->index(['score', 'updated_at']); // For recent score changes reporting
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_scores');
    }
};
