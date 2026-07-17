<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Identifikasi Cuti Tahunan secara eksplisit (bukan cocok nama, lihat
     * App\Enum\Attendance\LeaveTypeCategoryEnum) — dibutuhkan buat aturan baru "batas
     * akumulasi cuti tahunan per bulan" (setting `cuti_tahunan_max_per_bulan`, module
     * attendance). Nullable, data existing tidak ikut diklasifikasi otomatis — admin isi
     * manual lewat form Jenis Cuti kalau mau aturan batas ini berlaku.
     */
    public function up(): void
    {
        Schema::table('m_leave_types', function (Blueprint $table) {
            $table->string('category')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('m_leave_types', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
