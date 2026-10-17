<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * employee_executive_allowances menggantikan executive_allowances (dikunci employee_id, bukan user_id).
 * Isinya disalin SEKALI saat migration ini jalan, dengan id yang sama; executive_allowances dibiarkan
 * apa adanya (beku, tidak ditulis lagi oleh kode, dihapus di rilis terpisah). Kode pindah ke tabel baru
 * di rilis yang sama. Kolom amount = ciphertext PayrollEncrypted (text), disalin apa adanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        $orphans = DB::table('executive_allowances')
            ->leftJoin('employees', 'employees.user_id', '=', 'executive_allowances.user_id')
            ->whereNull('employees.id')
            ->count();

        if ($orphans > 0) {
            throw new RuntimeException("{$orphans} baris executive_allowances belum punya baris employees. Jalankan `php artisan identity:backfill` dulu.");
        }

        Schema::create('employee_executive_allowances', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('component', 50)->nullable();
            $table->string('type', 20);
            $table->string('label');
            $table->text('amount');
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::statement(
            'INSERT INTO employee_executive_allowances (id, employee_id, component, type, label, amount, note, is_active, created_at, updated_at) '
            . 'SELECT a.id, e.id, a.component, a.type, a.label, a.amount, a.note, a.is_active, a.created_at, a.updated_at '
            . 'FROM executive_allowances a JOIN employees e ON e.user_id = a.user_id'
        );

        if (DB::table('employee_executive_allowances')->count() !== DB::table('executive_allowances')->count()) {
            throw new RuntimeException('Jumlah baris employee_executive_allowances tidak sama dengan executive_allowances setelah salin.');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_executive_allowances');
    }
};
