<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aturan transisi extra shift (mis. Pagi->Siang boleh, Malam->Pagi tidak boleh - baris tidak
 * ada = tidak boleh) + nominal insentifnya. Pasangan (from/to) dikelola di Presensi
 * (ExtraShiftRulePage), nominal (`amount`, terenkripsi) diisi belakangan di Payroll
 * (MappingKomponenGajiPage tab Extra Shift) - dua permukaan edit, satu tabel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extra_shift_rules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('m_companies')->cascadeOnDelete();
            $table->string('from_shift_type'); // ShiftType::PAGI|SIANG|MALAM
            $table->string('to_shift_type');
            $table->text('amount')->nullable(); // encrypted (PayrollEncrypted), diisi belakangan dari Payroll
            $table->timestamps();

            $table->unique(['company_id', 'from_shift_type', 'to_shift_type'], 'extra_shift_rules_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extra_shift_rules');
    }
};
