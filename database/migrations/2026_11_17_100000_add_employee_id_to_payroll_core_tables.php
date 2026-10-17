<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migrasi payroll user_id -> employee_id (roadmap_migrasi_bertahap_identity_hris.md, Slice D), tahap 1:
 * TAMBAH employee_id (nullable) + isi dari employees.user_id. user_id TIDAK dihapus/diubah - tetap
 * terisi (model LinksEmployee menjaga keduanya sinkron) supaya bisa rollback. Kode membaca/menulis
 * lewat employee_id. Tabel lain (koperasi, insentif, tanggungan BPJS, dst.) belum ikut.
 *
 * Cuma tabel dengan unique/index berbasis user_id yang mendapat padanan employee_id.
 */
return new class extends Migration
{
    private const TABLES = ['payrolls', 'payroll_manual_lines', 'payroll_component_overrides', 'gaji_pokok_kenaikan_overrides'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreignUlid('employee_id')->nullable()->after('user_id')->constrained('employees');
            });

            DB::statement("UPDATE {$table} SET employee_id = (SELECT e.id FROM employees e WHERE e.user_id = {$table}.user_id)");
        }

        Schema::table('payroll_component_overrides', fn (Blueprint $t) => $t->unique(['employee_id', 'component']));
        Schema::table('gaji_pokok_kenaikan_overrides', fn (Blueprint $t) => $t->unique('employee_id'));
        Schema::table('payroll_manual_lines', fn (Blueprint $t) => $t->index(['payroll_period_id', 'employee_id', 'component'], 'payroll_manual_lines_period_employee_component_idx'));
    }

    public function down(): void
    {
        Schema::table('payroll_manual_lines', fn (Blueprint $t) => $t->dropIndex('payroll_manual_lines_period_employee_component_idx'));

        foreach (self::TABLES as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropConstrainedForeignId('employee_id'));
        }
    }
};
