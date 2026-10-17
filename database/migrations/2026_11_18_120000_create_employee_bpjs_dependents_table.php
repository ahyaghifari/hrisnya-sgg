<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * employee_bpjs_dependents menggantikan bpjs_dependents (dikunci employee_id, bukan user_id). Isinya
 * disalin SEKALI saat migration ini jalan, dengan id yang sama; bpjs_dependents dibiarkan apa adanya
 * (beku, tidak ditulis lagi oleh kode, dihapus di rilis terpisah). Kode pindah ke tabel baru di rilis
 * yang sama, jadi tidak ada dua sumber yang jalan bersamaan.
 */
return new class extends Migration
{
    public function up(): void
    {
        $orphans = DB::table('bpjs_dependents')
            ->leftJoin('employees', 'employees.user_id', '=', 'bpjs_dependents.user_id')
            ->whereNull('employees.id')
            ->count();

        if ($orphans > 0) {
            throw new RuntimeException("{$orphans} baris bpjs_dependents belum punya baris employees. Jalankan `php artisan identity:backfill` dulu.");
        }

        Schema::create('employee_bpjs_dependents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('employee_id')->unique()->constrained('employees')->cascadeOnDelete();
            $table->unsignedTinyInteger('count');
            $table->string('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::statement(
            'INSERT INTO employee_bpjs_dependents (id, employee_id, count, note, is_active, created_at, updated_at) '
            . 'SELECT b.id, e.id, b.count, b.note, b.is_active, b.created_at, b.updated_at '
            . 'FROM bpjs_dependents b JOIN employees e ON e.user_id = b.user_id'
        );

        if (DB::table('employee_bpjs_dependents')->count() !== DB::table('bpjs_dependents')->count()) {
            throw new RuntimeException('Jumlah baris employee_bpjs_dependents tidak sama dengan bpjs_dependents setelah salin.');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_bpjs_dependents');
    }
};
