<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Saklar "pakai Regu atau tidak" untuk jadwal shift ini - default FALSE (tidak semua jadwal
     * butuh dikelompokkan regu). Diatur DI TAB "Regu" (tombol Aktifkan/Batalkan), pola sama
     * persis `use_template` di ScheduleShiftRotationTab - bukan di form Create/Edit Jadwal.
     */
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->boolean('use_teams')->default(false)->after('use_template');
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn('use_teams');
        });
    }
};
