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
        Schema::create('thesis_copies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thesis_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('copy_number'); // e.g., 1, 2, 3
            $table->enum('status', ['Available', 'Reserved', 'Unavailable'])->default('Available');
            $table->timestamps();

            $table->unique(['thesis_id', 'copy_number']); // prevent duplicate copy numbers
            $table->index('status'); // for quick availability lookup
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('thesis_copies');
    }
};
