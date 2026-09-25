<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Dasar Klarifikasi (instruksi user 2026-09-24) - beda-beda tiap SP, WYSIWYG. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sp_reports', function (Blueprint $table) {
            $table->text('nomor')->nullable()->after('sp_id');
        });
    }

    public function down(): void
    {
        Schema::table('sp_reports', function (Blueprint $table) {
            $table->dropColumn('nomor');
        });
    }
};
