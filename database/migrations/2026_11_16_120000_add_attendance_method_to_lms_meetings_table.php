<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lms_meetings', function (Blueprint $table) {
            // Cara absensi per pertemuan: 'manual' (dicentang L&D) atau 'qr' (peserta scan QR
            // masuk/pulang). Beda dari attendance_type (online/offline/hybrid).
            $table->string('attendance_method')->default('manual')->after('attendance_type');
        });
    }

    public function down(): void
    {
        Schema::table('lms_meetings', function (Blueprint $table) {
            $table->dropColumn('attendance_method');
        });
    }
};
