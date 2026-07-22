<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * Penanda record dihitung otomatis (mis. dari pipeline access_logs -> jadwal baru), beda
     * dari hasil import Excel manual. Dibedakan dari `source` (string bebas) - kolom ini murni
     * boolean cepat buat filter, bukan pengganti `source`.
     */
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->boolean('is_auto_calculated')->default(false)->after('source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn('is_auto_calculated');
        });
    }
};
