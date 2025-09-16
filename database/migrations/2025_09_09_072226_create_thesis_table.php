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
            $table->year('publication_year');
            $table->string('research_project_adviser');
            $table->string('department');
            $table->string('dean');
            $table->timestamps();

            // Add indexes for better performance
            $table->index('department'); // For department-based searches
            $table->index('research_project_adviser'); // For searching by adviser
            $table->fullText(['title', 'research_project_adviser']); // Full-text search on title and adviser
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
