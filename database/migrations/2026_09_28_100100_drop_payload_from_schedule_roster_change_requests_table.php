<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `payload` (JSON diff) tidak lagi dipakai - sejak 2026-09-28 diff hidup di baris
     * schedule_shift_assignments sendiri lewat kolom draft_* (lihat migration
     * 2026_09_28_100000_add_draft_columns_to_schedule_shift_assignments_table.php), bukan snapshot
     * JSON di tabel ini lagi. Tabel ini sekarang murni wrapper (siapa mengajukan, status, kapan).
     */
    public function up(): void
    {
        Schema::table('schedule_roster_change_requests', function (Blueprint $table) {
            $table->dropColumn('payload');
        });
    }

    public function down(): void
    {
        Schema::table('schedule_roster_change_requests', function (Blueprint $table) {
            $table->json('payload')->nullable();
        });
    }
};
