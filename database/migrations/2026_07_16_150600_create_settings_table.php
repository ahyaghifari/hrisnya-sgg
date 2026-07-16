<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel config generik lintas modul (payroll, attendance, dst) — company_id nullable =
     * default global, override per company kalau ada baris company_id spesifik. Pola fallback
     * sama persis yang sudah dipakai PayrollService::handleExtraHourAllowanceComponent() untuk
     * attendance_policies (lihat knowledge/markdown/analisis-payroll-5-*.md §1).
     *
     * Baris `min_total_working_hours_per_month` dari `attendance_policies` di-copy ke sini
     * OTOMATIS supaya tersedia buat dipakai nanti — tapi `attendance_policies` TIDAK dihapus
     * dan PayrollService BELUM diarahkan ke sini (itu langkah terpisah yang perlu diverifikasi
     * angkanya identik dulu sebelum tabel lama di-drop, lihat planning Fase 0 §4.5).
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->nullable()->constrained('m_companies')->nullOnDelete();
            $table->string('module');
            $table->string('key');
            $table->string('type');
            $table->string('value');
            $table->string('label');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'key']);
        });

        if (Schema::hasTable('attendance_policies')) {
            $oldPolicy = DB::table('attendance_policies')
                ->where('type', 'min_total_working_hours_per_month')
                ->first();

            if ($oldPolicy) {
                DB::table('settings')->insert([
                    'id' => (string) \Illuminate\Support\Str::ulid(),
                    'company_id' => $oldPolicy->company_id,
                    'module' => 'payroll',
                    'key' => 'min_total_working_hours_per_month',
                    'type' => 'integer',
                    'value' => (string) $oldPolicy->value,
                    'label' => 'Minimum Total Jam Kerja Sebulan',
                    'description' => 'Dipindah otomatis dari attendance_policies — dipakai hitung tunjangan kelebihan jam kerja.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
