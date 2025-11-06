<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create advisers table
        Schema::create('advisers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        // Create deans table
        Schema::create('deans', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('department')->nullable();
            $table->timestamps();

            $table->index('department');
        });

        // Migrate existing data from academic_papers
        if (Schema::hasTable('academic_papers')) {
            // Migrate advisers - get distinct adviser names
            $adviserNames = DB::table('academic_papers')
                ->select('research_project_adviser as name')
                ->whereNotNull('research_project_adviser')
                ->distinct()
                ->get()
                ->map(function ($row) {
                    return [
                        'name' => $row->name,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                })
                ->toArray();

            // Insert advisers using upsert for database portability
            if (!empty($adviserNames)) {
                DB::table('advisers')->upsert(
                    $adviserNames,
                    ['name'], // Unique column
                    ['name']  // Columns to update on conflict (just keep the same name)
                );
            }

            // Migrate deans - get distinct dean names
            $deanNames = DB::table('academic_papers')
                ->select('dean as name')
                ->whereNotNull('dean')
                ->distinct()
                ->get()
                ->map(function ($row) {
                    return [
                        'name' => $row->name,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                })
                ->toArray();

            // Insert deans using upsert for database portability
            if (!empty($deanNames)) {
                DB::table('deans')->upsert(
                    $deanNames,
                    ['name'], // Unique column
                    ['name']  // Columns to update on conflict (just keep the same name)
                );
            }
        }

        // Modify academic_papers table
        Schema::table('academic_papers', function (Blueprint $table) {
            // Add foreign key columns
            $table->foreignId('adviser_id')->nullable()->after('paper_type')->constrained('advisers')->nullOnDelete();
            $table->foreignId('dean_id')->nullable()->after('department')->constrained('deans')->nullOnDelete();
        });

        // Update foreign key references
        if (Schema::hasTable('academic_papers')) {
            // Update adviser_id using JOIN for performance (single query instead of N queries)
            DB::statement('
                UPDATE academic_papers ap
                INNER JOIN advisers a ON ap.research_project_adviser = a.name
                SET ap.adviser_id = a.id
                WHERE ap.research_project_adviser IS NOT NULL
            ');

            // Update dean_id using JOIN for performance (single query instead of N queries)
            DB::statement('
                UPDATE academic_papers ap
                INNER JOIN deans d ON ap.dean = d.name
                SET ap.dean_id = d.id
                WHERE ap.dean IS NOT NULL
            ');
        }

        // Drop old string columns
        Schema::table('academic_papers', function (Blueprint $table) {
            $table->dropIndex(['research_project_adviser']);
            $table->dropColumn(['research_project_adviser', 'dean']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-add string columns
        Schema::table('academic_papers', function (Blueprint $table) {
            $table->string('research_project_adviser')->nullable()->after('paper_type');
            $table->string('dean')->nullable()->after('department');

            $table->index('research_project_adviser');
        });

        // Restore data from normalized tables
        if (Schema::hasTable('academic_papers')) {
            // Restore adviser names using JOIN for performance
            DB::statement('
                UPDATE academic_papers ap
                INNER JOIN advisers a ON ap.adviser_id = a.id
                SET ap.research_project_adviser = a.name
                WHERE ap.adviser_id IS NOT NULL
            ');

            // Restore dean names using JOIN for performance
            DB::statement('
                UPDATE academic_papers ap
                INNER JOIN deans d ON ap.dean_id = d.id
                SET ap.dean = d.name
                WHERE ap.dean_id IS NOT NULL
            ');
        }

        // Drop foreign key columns
        Schema::table('academic_papers', function (Blueprint $table) {
            $table->dropForeign(['adviser_id']);
            $table->dropForeign(['dean_id']);
            $table->dropColumn(['adviser_id', 'dean_id']);
        });

        // Drop normalized tables
        Schema::dropIfExists('deans');
        Schema::dropIfExists('advisers');
    }
};
