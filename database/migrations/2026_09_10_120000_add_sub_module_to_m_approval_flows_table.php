<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('m_approval_flows', function (Blueprint $table) {
            // Daftar "ember" jenis pengajuan dipisah koma, mis. "leave,tidak_masuk_seharian".
            // NULL = alur berlaku untuk semua jenis di modul ini (perilaku lama / fallback).
            // Hanya diisi untuk module = attendance_request. Lihat AttendanceApprovalBucketEnum.
            $table->string('sub_module', 255)->nullable()->after('module');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('m_approval_flows', function (Blueprint $table) {
            $table->dropColumn('sub_module');
        });
    }
};
