<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // One open deposit per slot, enforced by the database — the
        // application-level lock is a no-op on SQLite. Mirrors the
        // claim-code partial index (supported by SQLite/Postgres).
        DB::statement("CREATE UNIQUE INDEX bag_deposits_open_slot_unique ON bag_deposits (bag_slot_id) WHERE status = 'open'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS bag_deposits_open_slot_unique');
    }
};
