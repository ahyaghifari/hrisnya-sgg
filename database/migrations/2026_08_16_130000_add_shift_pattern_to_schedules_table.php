<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pola shift (2 shift Pagi/Siang, atau 3 shift Pagi/Siang/Malam) - cuma relevan buat Schedule
 * tipe SHIFT, kolom lain tetap null. Dipakai buat konteks umum (RS/klinik biasanya salah satu
 * dari 2 pola ini) - berpengaruh ke Extra Shift & kebutuhan lain ke depan (belum dikerjakan di
 * sini, cuma nyimpen datanya dulu), lihat App\Enum\ShiftPatternEnum.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->string('shift_pattern')->nullable()->after('schedule_type');
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn('shift_pattern');
        });
    }
};
