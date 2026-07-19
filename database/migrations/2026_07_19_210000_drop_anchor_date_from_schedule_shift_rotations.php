<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `anchor_date` dicabut dari template — tabel ini murni pola (day -> shift/off), tanggal
     * mulainya BUKAN bagian dari template, ditentukan user tiap kali generate 1 periode (lihat
     * ScheduleShiftRosterTab::generateFromTemplate()), bukan disimpan permanen per user.
     */
    public function up(): void
    {
        Schema::table('schedule_shift_rotations', function (Blueprint $table) {
            $table->dropColumn('anchor_date');
        });
    }

    public function down(): void
    {
        Schema::table('schedule_shift_rotations', function (Blueprint $table) {
            $table->date('anchor_date')->nullable()->after('day');
        });
    }
};
