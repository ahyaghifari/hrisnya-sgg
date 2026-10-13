<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Perpanjangan: banyak baris per (employee, type), satu yang is_active; sisanya riwayat. */
    public function up(): void
    {
        Schema::table('employee_nakes_documents', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('berlaku_sampai');
            // Index baru dulu: FK employee_id sebelumnya bersandar pada unique (employee_id, type).
            $table->index(['employee_id', 'type', 'is_active'], 'nakes_docs_employee_type_active_idx');
        });

        Schema::table('employee_nakes_documents', function (Blueprint $table) {
            $table->dropUnique(['employee_id', 'type']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Tidak bisa di-rollback: riwayat perpanjangan melanggar unique (employee_id, type).');
    }
};
