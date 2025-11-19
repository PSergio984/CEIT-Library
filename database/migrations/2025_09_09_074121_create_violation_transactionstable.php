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
        Schema::create('violation_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('violation_id')->constrained()->onDelete('cascade');
            $table->timestamp('date_occurred');
            $table->text('remarks')->nullable();
            $table->timestamps();

            // Add indexes for better performance
            $table->index(['user_id', 'date_occurred']); // For user violation history queries
            $table->index(['violation_id', 'date_occurred']); // For violation type reporting
            $table->index('date_occurred'); // For date-based reporting
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('violation_transactions');
    }
};
