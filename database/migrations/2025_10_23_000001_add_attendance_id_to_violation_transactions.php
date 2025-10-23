<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up()
    {
        Schema::table('violation_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('attendance_id')->nullable()->after('violation_id');
            $table->index(['user_id', 'violation_id', 'attendance_id'], 'violation_user_violation_attendance_idx');
        });

        // Backfill attendance_id from remarks if possible, chunked for memory efficiency
        DB::table('violation_transactions')
            ->whereNotNull('remarks')
            ->where('remarks', 'LIKE', '%Attendance ID:%')
            ->orderBy('id')
            ->chunk(1000, function ($rows) {
                foreach ($rows as $vt) {
                    if (preg_match('/Attendance ID: (\d+)/', $vt->remarks, $matches)) {
                        DB::table('violation_transactions')->where('id', $vt->id)->update([
                            'attendance_id' => $matches[1]
                        ]);
                    }
                }
            });
    }

    public function down()
    {
        Schema::table('violation_transactions', function (Blueprint $table) {
            $table->dropIndex('violation_user_violation_attendance_idx');
            $table->dropColumn('attendance_id');
        });
    }
};
