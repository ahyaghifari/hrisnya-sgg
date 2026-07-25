<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * access_logs = dump mentah dari alat absensi (mis. Hikvision) - employee_id di sini merujuk ke
 * users.nip, BUKAN foreign key (device tidak tahu ULID user, cuma NIP). Belum pernah dibuat lewat
 * migration sebelumnya (cuma ada di DB lokal beberapa developer, tidak ada di production) -
 * migration ini menyamakan skema di semua environment. Tidak ada primary key eksplisit karena
 * device kirim data mentah tanpa id unik dari sisi kita; index di access_datetime/access_date
 * dipakai AccessLogScheduleReader (baca sinkron/incremental, lihat sync_access_logs).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_logs', function (Blueprint $table) {
            $table->string('employee_id');
            $table->dateTime('access_datetime');
            $table->date('access_date');
            $table->time('access_time');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('person_name');
            $table->string('person_group');

            $table->index('access_datetime');
            $table->index('access_date');
            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_logs');
    }
};
