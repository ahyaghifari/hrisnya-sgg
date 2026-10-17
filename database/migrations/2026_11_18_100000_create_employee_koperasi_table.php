<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * employee_koperasi menggantikan user_koperasi (dikunci employee_id, bukan user_id). Isinya disalin
 * SEKALI saat migration ini jalan, dengan id yang sama; user_koperasi dibiarkan apa adanya (beku,
 * tidak ditulis lagi oleh kode, dihapus di rilis terpisah). Kode pindah ke tabel baru di rilis yang
 * sama, jadi tidak ada dua sumber yang jalan bersamaan. Kolom amount = ciphertext PayrollEncrypted
 * (text), disalin apa adanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        $orphans = DB::table('user_koperasi')
            ->leftJoin('employees', 'employees.user_id', '=', 'user_koperasi.user_id')
            ->whereNull('employees.id')
            ->count();

        if ($orphans > 0) {
            throw new RuntimeException("{$orphans} baris user_koperasi belum punya baris employees. Jalankan `php artisan identity:backfill` dulu.");
        }

        Schema::create('employee_koperasi', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('employee_id')->unique()->constrained('employees')->cascadeOnDelete();
            $table->text('amount');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::statement(
            'INSERT INTO employee_koperasi (id, employee_id, amount, is_active, created_at, updated_at) '
            . 'SELECT k.id, e.id, k.amount, k.is_active, k.created_at, k.updated_at '
            . 'FROM user_koperasi k JOIN employees e ON e.user_id = k.user_id'
        );

        if (DB::table('employee_koperasi')->count() !== DB::table('user_koperasi')->count()) {
            throw new RuntimeException('Jumlah baris employee_koperasi tidak sama dengan user_koperasi setelah salin.');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_koperasi');
    }
};
