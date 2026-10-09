<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pelanggaran (kind=pelanggaran) ditandai satu level SP (sp1/sp2/sp3); form SP hanya menawarkan
 * pelanggaran untuk level yang dipilih. NULL = berlaku untuk semua level (baris lama sebelum
 * penandaan ini, dan Dasar Klarifikasi yang memang tidak punya level).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sp_reasons', function (Blueprint $table) {
            $table->string('sp_level')->nullable()->after('kind');
        });
    }

    public function down(): void
    {
        Schema::table('sp_reasons', fn (Blueprint $table) => $table->dropColumn('sp_level'));
    }
};
