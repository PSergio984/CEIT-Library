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
        Schema::create('bag_deposit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bag_deposit_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['deposit', 'claim', 'temp_access', 'override']);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('scanned_by')->nullable()->constrained('librarians')->onDelete('set null');
            $table->foreignId('scanned_by_admin_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('method')->nullable();
            $table->text('note')->nullable();
            $table->boolean('id_verified')->default(false);
            $table->timestamps();

            $table->index(['bag_deposit_id', 'type']);
            $table->index('actor_user_id');
            $table->index('scanned_by');
            $table->index('scanned_by_admin_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bag_deposit_events');
    }
};
