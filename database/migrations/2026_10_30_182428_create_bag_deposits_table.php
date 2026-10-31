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
        Schema::create('bag_deposits', function (Blueprint $table) {
            $table->id();
            // Claimed deposits (and their audit events) outlive a deleted
            // user as null-user rows; open deposits block deletion in
            // AdminUserList::deleteUser, so a live depositor is never lost.
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('bag_slot_id')->constrained()->onDelete('restrict');
            $table->unsignedSmallInteger('item_count')->default(1);
            $table->string('description')->nullable();
            $table->string('claim_code', 6);
            $table->string('photo_path')->nullable();
            $table->enum('status', ['open', 'claimed'])->default('open');
            $table->foreignId('claimed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('claimed_at')->nullable();
            $table->string('claim_method')->nullable();
            $table->timestamp('unclaimed_notified_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['bag_slot_id', 'status']);
            $table->index('status');
            $table->index('unclaimed_notified_at');
        });

        // Claim codes must be unique only among open deposits — a claimed
        // deposit's code can be reused. Partial index (supported by the
        // SQLite/Postgres drivers this app runs on; not available on MySQL).
        DB::statement("CREATE UNIQUE INDEX bag_deposits_open_claim_code_unique ON bag_deposits (claim_code) WHERE status = 'open'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bag_deposits');
    }
};
