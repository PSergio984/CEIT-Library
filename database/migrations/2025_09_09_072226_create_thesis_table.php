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
        Schema::create('theses', function (Blueprint $table) {
            $table->id();
            $table->string('catalog_code')->unique();
            $table->string('title');
            $table->unsignedInteger('copies')->default(1);
            $table->string('research_project_adviser');
            $table->string('department');
            $table->string('member1');
            $table->string('member2');
            $table->string('member3');
            $table->string('member4');
            $table->string('dean');
            $table->enum('status', ['Available','Reserved', 'Unavailable'])->default('Available');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('theses');
    }
};
