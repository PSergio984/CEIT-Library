<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('academic_papers', function (Blueprint $table) {
            $table->id();
            $table->string('catalog_code')->unique();
            $table->string('title');
            $table->year('publication_year');
            $table->string('paper_type');
            $table->foreignId('research_adviser_id')->nullable()->constrained('research_advisers')->nullOnDelete();
            $table->foreignId('technical_adviser_id')->nullable()->constrained('technical_advisers')->nullOnDelete();
            $table->string('department');
            $table->foreignId('dean_id')->nullable()->constrained('deans')->nullOnDelete();
            $table->timestamps();

            // Add indexes for better performance
            $table->index('department'); // For department-based searches

            // Full-text search only supported on MySQL/MariaDB, not SQLite
            if (DB::connection()->getDriverName() !== 'sqlite') {
                $table->fullText(['title']); // Full-text search on title
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academic_papers');
    }
};
