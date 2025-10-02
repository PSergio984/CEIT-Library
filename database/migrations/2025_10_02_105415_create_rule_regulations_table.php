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
        Schema::create('rule_regulations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_header_id')->constrained()->onDelete('cascade');
            // The full text of the rule/regulation
            $table->text('content');
            // Sort order within its main header
            $table->unsignedSmallInteger('order');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rule_regulations');
    }
};
