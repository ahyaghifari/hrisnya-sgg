<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_records', function (Blueprint $table) {
            // Tugas berulang yang sudah dicentang selesai tetap berstatus Selesai sampai periodenya lewat;
            // di waktu ini (awal hari setelah periode berakhir) ia aktif lagi untuk periode berikutnya.
            $table->dateTime('recurrence_resume_at')->nullable()->after('recurrence');
        });
    }

    public function down(): void
    {
        Schema::table('task_records', function (Blueprint $table) {
            $table->dropColumn('recurrence_resume_at');
        });
    }
};
