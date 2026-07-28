<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Default min_work_minutes Fixed diubah dari 0 (nonaktif) ke 300 - baris yang sudah ada TIDAK
 * ikut berubah nilainya (cuma default kolom buat insert baru tanpa nilai eksplisit), form admin
 * sendiri sudah selalu kirim nilai eksplisit (lihat HandlesDayScheduleForm::initDays()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_fixed_days', function (Blueprint $table) {
            $table->unsignedSmallInteger('min_work_minutes')->default(300)->change();
        });
    }

    public function down(): void
    {
        Schema::table('schedule_fixed_days', function (Blueprint $table) {
            $table->unsignedSmallInteger('min_work_minutes')->default(0)->change();
        });
    }
};
