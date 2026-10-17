<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * payroll_jasa_unit_details menggantikan jasa_unit_details (dikunci employee_id, bukan user_id).
 * Isinya disalin SEKALI saat migration ini jalan, dengan id yang sama; jasa_unit_details dibiarkan
 * apa adanya (beku, tidak ditulis lagi oleh kode, dihapus di rilis terpisah). Kode pindah ke tabel
 * baru di rilis yang sama. Kolom amount = ciphertext PayrollEncrypted (text), disalin apa adanya;
 * created_by tetap FK ke users (aktor).
 */
return new class extends Migration
{
    public function up(): void
    {
        $orphans = DB::table('jasa_unit_details')
            ->leftJoin('employees', 'employees.user_id', '=', 'jasa_unit_details.user_id')
            ->whereNull('employees.id')
            ->count();

        if ($orphans > 0) {
            throw new RuntimeException("{$orphans} baris jasa_unit_details belum punya baris employees. Jalankan `php artisan identity:backfill` dulu.");
        }

        Schema::create('payroll_jasa_unit_details', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();
            $table->foreignUlid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignUlid('jasa_unit_type_id')->constrained('jasa_unit_types')->cascadeOnDelete();
            $table->text('amount');
            $table->text('note')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::statement(
            'INSERT INTO payroll_jasa_unit_details (id, payroll_period_id, employee_id, jasa_unit_type_id, amount, note, created_by, created_at, updated_at) '
            . 'SELECT d.id, d.payroll_period_id, e.id, d.jasa_unit_type_id, d.amount, d.note, d.created_by, d.created_at, d.updated_at '
            . 'FROM jasa_unit_details d JOIN employees e ON e.user_id = d.user_id'
        );

        if (DB::table('payroll_jasa_unit_details')->count() !== DB::table('jasa_unit_details')->count()) {
            throw new RuntimeException('Jumlah baris payroll_jasa_unit_details tidak sama dengan jasa_unit_details setelah salin.');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_jasa_unit_details');
    }
};
