<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `swap_kind` bedain tukar-tanggal biasa ('schedule', default - konflik nyata cuma jadi
 * warning, lihat ShiftSwapRequestService::conflictWarnings()) dari tukar libur berpasangan
 * ('reciprocal_off' - konflik nyata WAJIB diblok keras, supaya jatah libur kedua pihak tetap
 * seimbang). Lihat planning/2026-09-12-tukar-shift-libur-berpasangan.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_swap_requests', function (Blueprint $table) {
            $table->string('swap_kind', 20)->default('schedule')->after('target_schedule_id');
        });
    }

    public function down(): void
    {
        Schema::table('shift_swap_requests', function (Blueprint $table) {
            $table->dropColumn('swap_kind');
        });
    }
};
