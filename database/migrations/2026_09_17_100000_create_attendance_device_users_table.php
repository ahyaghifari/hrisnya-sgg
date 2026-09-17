<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Override per-user attendance_devices - buat user holding yang ditempatkan di unit usaha
 * dengan device beda dari company formal-nya (lihat App\Models\Master\Attendance\AttendanceDevice
 * ::users(), App\Services\Attendance\AccessLogScheduleReader::hasUnrecognizedDevice()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_device_users', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('attendance_device_id')->constrained('attendance_devices')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['attendance_device_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_device_users');
    }
};
