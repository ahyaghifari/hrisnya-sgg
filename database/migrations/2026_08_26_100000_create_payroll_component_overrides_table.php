<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Override manual per karyawan+komponen (2026-08-26, instruksi user: "menimpa calculator
 * komponen gaji apapun") - kalau ada baris aktif di sini untuk (user_id, component), nilainya
 * langsung dipakai apa adanya di PayrollService::runComponent(), Calculator komponen itu SAMA
 * SEKALI TIDAK dijalankan (beda dari applyManualRevision()/edited_manually yang cuma koreksi
 * 1 payroll yang sudah digenerate - ini standing policy, dicek ulang tiap Generate/Regenerate).
 * Amount `text` (bukan decimal) - ciphertext PayrollEncrypted, sama pola user_koperasi/
 * payroll_items pasca migration encrypt_payroll_nominal_columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_component_overrides', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('component');
            $table->text('amount');
            $table->string('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'component']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_component_overrides');
    }
};
