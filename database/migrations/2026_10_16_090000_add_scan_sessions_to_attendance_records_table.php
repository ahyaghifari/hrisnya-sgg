<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            // Jadwal IBS: daftar pasangan masuk-pulang hari itu, mis. [{"in":"08:00:00","out":"09:30:00","minutes":90}].
            $table->json('scan_sessions')->nullable()->after('out_of_window');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn('scan_sessions');
        });
    }
};
