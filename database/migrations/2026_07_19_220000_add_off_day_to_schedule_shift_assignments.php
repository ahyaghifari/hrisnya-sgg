<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Roster (Detail Penjadwalan) sekarang bisa isi "Libur" eksplisit per tanggal (bukan cuma
     * disimpulkan dari baris kosong — biar konsisten dengan rotasi di tab Template Shift yang
     * memang nyimpen off_day) — shift_type_id jadi nullable, off_day = true kalau Libur.
     */
    public function up(): void
    {
        Schema::table('schedule_shift_assignments', function (Blueprint $table) {
            $table->foreignUlid('shift_type_id')->nullable()->change();
            $table->boolean('off_day')->default(false)->after('shift_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('schedule_shift_assignments', function (Blueprint $table) {
            $table->dropColumn('off_day');
            $table->foreignUlid('shift_type_id')->nullable(false)->change();
        });
    }
};
