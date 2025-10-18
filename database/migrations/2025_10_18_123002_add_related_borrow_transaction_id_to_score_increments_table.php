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
        Schema::table('score_increments', function (Blueprint $table) {
            $table->foreignId('related_borrow_transaction_id')->nullable()->after('related_attendance_id')->constrained('borrow_transactions')->nullOnDelete();
            $table->unique(['user_id', 'related_borrow_transaction_id'], 'score_increments_user_borrow_txn_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('score_increments', function (Blueprint $table) {
            $table->dropForeign(['related_borrow_transaction_id']);
            $table->dropUnique('score_increments_user_borrow_txn_unique');
            $table->dropColumn('related_borrow_transaction_id');
        });
    }
};
