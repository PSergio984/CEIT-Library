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
        Schema::create('catalog_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('sequence_key')->unique()->comment('Format: DEPT_CODE-YEAR (e.g., IT-25, CE-24)');
            $table->unsignedInteger('last_sequence')->default(0)->comment('Last sequence number used');
            $table->timestamps();

            $table->index('sequence_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalog_sequences');
    }
};
