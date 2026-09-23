<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tindak Lanjut coaching (planning/2026-09-23-sp-dan-merit-point.md §1.1) — evaluasi coach soal
 * kondisi karyawan setelah coaching, hanya diisi coach_by. Independen dari fitur SP (§1.2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coachings', function (Blueprint $table) {
            $table->string('follow_up_status')->nullable()->after('improvement_due_date');
            $table->text('follow_up_note')->nullable()->after('follow_up_status');
            $table->timestamp('follow_up_at')->nullable()->after('follow_up_note');
        });
    }

    public function down(): void
    {
        Schema::table('coachings', function (Blueprint $table) {
            $table->dropColumn(['follow_up_status', 'follow_up_note', 'follow_up_at']);
        });
    }
};
