<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pertemuan baru default scan QR. Pertemuan yang sudah ada tetap 'manual' (nilai kolom tidak diubah).
        Schema::table('lms_meetings', function (Blueprint $table) {
            $table->string('attendance_method')->default('qr')->change();
        });
    }

    public function down(): void
    {
        Schema::table('lms_meetings', function (Blueprint $table) {
            $table->string('attendance_method')->default('manual')->change();
        });
    }
};
