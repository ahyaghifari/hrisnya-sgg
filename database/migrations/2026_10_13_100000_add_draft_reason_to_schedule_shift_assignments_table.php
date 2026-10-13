<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Alasan pengaju per sel draft - ditampilkan ke approver di banner pengajuan jadwal. */
    public function up(): void
    {
        Schema::table('schedule_shift_assignments', function (Blueprint $table) {
            $table->string('draft_reason', 255)->nullable()->after('draft_off_day');
        });
    }

    public function down(): void
    {
        Schema::table('schedule_shift_assignments', function (Blueprint $table) {
            $table->dropColumn('draft_reason');
        });
    }
};
