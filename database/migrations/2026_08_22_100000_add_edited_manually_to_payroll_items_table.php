<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * edited_manually = baris ini pernah di-override lewat EditAction/Add Adjustment di
 * PayrollItemsRelationManager (halaman detail payroll) - Generate/Regenerate WAJIB
 * mempertahankan baris ini apa adanya dan skip Calculator utk source_type yang sama, bukan
 * menimpa balik dengan hasil hitungan otomatis (lihat PayrollService::generateForUser()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->boolean('edited_manually')->default(false)->after('source_type');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropColumn('edited_manually');
        });
    }
};
