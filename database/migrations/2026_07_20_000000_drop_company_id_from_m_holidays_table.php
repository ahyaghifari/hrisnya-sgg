<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hari Libur & Cuti Bersama diputuskan global untuk semua company, bukan per-company
 * (company_id sebelumnya sudah nullable = global, tapi opsi per-company tidak pernah
 * dipakai dan cuma bikin bingung) — kolomnya dihapus total.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_holidays', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropIndex(['company_id', 'date']);
            $table->dropColumn('company_id');
        });
    }

    public function down(): void
    {
        Schema::table('m_holidays', function (Blueprint $table) {
            $table->foreignUlid('company_id')->nullable()->constrained('m_companies')->nullOnDelete();
            $table->index(['company_id', 'date']);
        });
    }
};
