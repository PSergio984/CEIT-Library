<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('borrow_transactions', function (Blueprint $table) {
            $table->timestamp('warning_notified_at')->nullable()->after('overdue_notified_at');
            $table->index('warning_notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('borrow_transactions', function (Blueprint $table) {
            $table->dropIndex(['warning_notified_at']);
            $table->dropColumn('warning_notified_at');
        });
    }
};
