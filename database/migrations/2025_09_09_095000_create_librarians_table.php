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

        Schema::create('librarians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Reference to the student's account
            $table->integer('batch_no')->nullable();
            $table->date('start_date')->nullable(); // Start date of librarian duty batch
            $table->date('end_date')->nullable(); // End date of librarian duty batch
            $table->enum('status', ['active', 'inactive', 'expired'])->default('inactive');
            $table->timestamp('expires_at')->nullable(); // Legacy field, kept for backward compatibility
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null'); // Admin who created this account
            $table->timestamp('last_login_at')->nullable();
            $table->string('shift_notes')->nullable(); // Notes about their duty shift
            $table->timestamps();
            // Add indexes for better performance
            $table->unique('user_id'); // One librarian account per student
            $table->index('batch_no'); // For grouping by batch
            $table->index(['start_date', 'end_date']); // For finding active/expired batches
            $table->index('status'); // For filtering by status
            $table->index('created_by'); // For tracking which Admin created accounts
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('librarians');
    }
};
