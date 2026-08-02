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
        Schema::table('attendance_requests', function (Blueprint $table) {
            // Siapa yang membuat baris ini - beda dari user_id (siapa yang izin/cuti/lemburnya).
            // Pengajuan mandiri lewat portal: sama dengan user_id. Dibuat admin lewat /admin atas
            // nama karyawan lain: id admin itu - dipakai buat gate tombol Hapus (cuma boleh kalau
            // dibuat admin, bukan pengajuan sendiri oleh karyawan).
            $table->foreignUlid('created_by')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_requests', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
        });
    }
};
