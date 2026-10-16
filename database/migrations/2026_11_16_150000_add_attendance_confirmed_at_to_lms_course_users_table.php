<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lms_course_users', function (Blueprint $table) {
            // Peserta yang diikutkan admin mengonfirmasi akan mengikuti pelatihan (RSVP).
            $table->timestamp('attendance_confirmed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('lms_course_users', function (Blueprint $table) {
            $table->dropColumn('attendance_confirmed_at');
        });
    }
};
