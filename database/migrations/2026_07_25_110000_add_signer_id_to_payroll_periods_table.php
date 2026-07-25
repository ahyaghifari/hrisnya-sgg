<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Terpisah dari generated_by (yang otomatis ikut siapa terakhir klik Generate/Regenerate) -
 * signer_id dipilih manual di form Periode Gaji, dipakai buat "Dibuat oleh" di slip gaji
 * (resources/views/pdf/payroll/payroll.blade.php) supaya penandatangan tidak ikut berubah
 * cuma karena payroll di-regenerate oleh orang lain.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->foreignUlid('signer_id')->nullable()->after('generated_by')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->dropForeign(['signer_id']);
            $table->dropColumn('signer_id');
        });
    }
};
