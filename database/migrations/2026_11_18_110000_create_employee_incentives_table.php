<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * employee_incentives menggantikan incentives (dikunci employee_id, bukan user_id). Isinya disalin
 * SEKALI saat migration ini jalan, dengan id yang sama; incentives dibiarkan apa adanya (beku, tidak
 * ditulis lagi oleh kode, dihapus di rilis terpisah). Kode pindah ke tabel baru di rilis yang sama.
 * Kolom amount = ciphertext PayrollEncrypted (text), disalin apa adanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        $orphans = DB::table('incentives')
            ->leftJoin('employees', 'employees.user_id', '=', 'incentives.user_id')
            ->whereNull('employees.id')
            ->count();

        if ($orphans > 0) {
            throw new RuntimeException("{$orphans} baris incentives belum punya baris employees. Jalankan `php artisan identity:backfill` dulu.");
        }

        Schema::create('employee_incentives', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->text('note')->nullable();
            $table->date('incentive_date')->nullable();
            $table->text('amount');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::statement(
            'INSERT INTO employee_incentives (id, employee_id, name, note, incentive_date, amount, is_active, created_at, updated_at) '
            . 'SELECT i.id, e.id, i.name, i.note, i.incentive_date, i.amount, i.is_active, i.created_at, i.updated_at '
            . 'FROM incentives i JOIN employees e ON e.user_id = i.user_id'
        );

        if (DB::table('employee_incentives')->count() !== DB::table('incentives')->count()) {
            throw new RuntimeException('Jumlah baris employee_incentives tidak sama dengan incentives setelah salin.');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_incentives');
    }
};
