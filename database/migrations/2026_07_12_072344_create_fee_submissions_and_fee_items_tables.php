<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generalisasi doctor_fee_submissions/doctor_fee_items (dihapus di migration setelah ini) —
 * bukan cuma buat dokter, generik buat klaim fee per kejadian apa pun. Lihat
 * planning/2026-07-12-redesign-payroll-simplifikasi-nama-dan-provenance.md §D.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_submissions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('m_companies')->cascadeOnDelete();

            $table->string('reference_number')->nullable();
            $table->string('subject_name')->nullable();
            $table->string('service_type')->nullable();
            $table->string('status')->nullable();
            $table->text('notes')->nullable();

            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date')->nullable();
            $table->timestamps();
        });

        Schema::create('fee_items', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('submission_id')->constrained('fee_submissions')->cascadeOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();

            // Nullable: banyak item input manual ad-hoc, tidak semua link ke katalog (lihat
            // analisis-payroll-2-redesign-struktur.md §2 - data lama "Visit Bersama"/"Operasi"/
            // "Bius" tidak link ke master sama sekali).
            $table->foreignUlid('salary_component_id')->nullable()->constrained('salary_components')->nullOnDelete();

            $table->boolean('is_manual')->default(false);
            $table->string('name');
            $table->decimal('amount', 15, 2);
            $table->boolean('is_paid')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_items');
        Schema::dropIfExists('fee_submissions');
    }
};
