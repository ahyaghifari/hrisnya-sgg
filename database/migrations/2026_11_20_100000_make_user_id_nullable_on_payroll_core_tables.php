<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll kini dikunci employee_id sepenuhnya (kode berhenti menulis user_id). Kolom user_id dibuat
 * nullable supaya baris baru boleh tanpa user_id; kolom dan isinya TIDAK dihapus (baris lama tetap
 * terisi, dicabut di rilis terpisah). Unique/FK lama di user_id tetap ada; NULL tidak bentrok di unique.
 */
return new class extends Migration
{
    private const TABLES = ['payrolls', 'payroll_manual_lines', 'payroll_component_overrides', 'gaji_pokok_kenaikan_overrides'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->char('user_id', 26)->nullable()->change());
        }
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse: mengembalikan NOT NULL gagal begitu ada baris tanpa user_id.
    }
};
