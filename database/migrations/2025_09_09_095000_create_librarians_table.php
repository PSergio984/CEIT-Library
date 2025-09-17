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
            $table->enum('status', ['active', 'inactive', 'expired'])->default('active');
            $table->timestamp('expires_at'); // Account expires within the day
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null'); // Admin who created this account
            $table->timestamp('last_login_at')->nullable();
            $table->string('shift_notes')->nullable(); // Notes about their duty shift
            $table->timestamps();

            // Add indexes for better performance
            $table->unique('user_id'); // One librarian account per student
            $table->index(['status', 'expires_at']); // For finding active/expired accounts
            $table->index('expires_at'); // For cleanup jobs and expiry checks
            $table->index(['status', 'last_login_at']); // For activity monitoring
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
