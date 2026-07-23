<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bedain 3 status lowongan di portal (Menunggu Dibuka / Sedang Dibuka / Sudah Ditutup) —
 * `is_active` saja tidak cukup karena "belum pernah dibuka" dan "pernah dibuka lalu ditutup"
 * sama-sama is_active=false. `opened_at` null = belum pernah dibuka; terisi = pernah/sedang
 * dibuka (status Buka/Tutup ditentukan is_active). `is_active` tetap dipakai apa adanya oleh
 * admin panel Filament (JobOpeningsTable, filter, dst).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_openings', function (Blueprint $table) {
            $table->timestamp('opened_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('job_openings', function (Blueprint $table) {
            $table->dropColumn('opened_at');
        });
    }
};
