<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Surat orientasi (SK/surat penugasan orientasi) — 1 file per periode, sejajar dengan
 * user_contracts.file_path.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_orientations', function (Blueprint $table) {
            $table->string('file_path')->nullable()->after('duration_months');
        });
    }

    public function down(): void
    {
        Schema::table('user_orientations', function (Blueprint $table) {
            $table->dropColumn('file_path');
        });
    }
};
