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

        // Backfill attendance_id from remarks if possible, chunked for memory efficiency and bulk update
        DB::table('violation_transactions')
            ->whereNotNull('remarks')
            ->where('remarks', 'LIKE', '%Attendance ID:%')
            ->orderBy('id')
            ->chunk(1000, function ($rows) {
                $idAttendanceMap = [];
                foreach ($rows as $vt) {
                    if (preg_match('/Attendance ID: (\d+)/', $vt->remarks, $matches)) {
                        $idAttendanceMap[$vt->id] = $matches[1];
                    }
                }
                if (!empty($idAttendanceMap)) {
                    DB::transaction(function () use ($idAttendanceMap) {
                        $sanitizedMap = [];
                        foreach ($idAttendanceMap as $id => $attendanceId) {
                            $sanitizedMap[(int)$id] = (int)$attendanceId;
                        }
                        $ids = array_keys($sanitizedMap);
                        $cases = '';
                        $bindings = [];
                        foreach ($sanitizedMap as $id => $attendanceId) {
                            $cases .= "WHEN id = ? THEN ? ";
                            $bindings[] = $id;
                            $bindings[] = $attendanceId;
                        }
                        $inPlaceholders = implode(',', array_fill(0, count($ids), '?'));
                        $bindings = array_merge($bindings, $ids);
                        $sql = "UPDATE violation_transactions SET attendance_id = CASE {$cases} END WHERE id IN ({$inPlaceholders})";
                        DB::statement($sql, $bindings);
                    });
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
