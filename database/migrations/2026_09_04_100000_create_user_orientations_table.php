<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ledger periode orientasi per karyawan - mirip user_contracts. 1 baris = 1 periode orientasi
 * yang berjalan. Dibuat HANYA lewat alur Rekrutmen (CandidateOnboardingService) saat kandidat
 * jadi karyawan; "Tambah Karyawan" manual TIDAK membuat baris ini.
 *
 * - duration_months : 3 / 6 / 12 (dipilih HR saat onboarding)
 * - source          : 'recruitment' (baris awal) | 'extension' (hasil keputusan "perpanjang"
 *   pada penilaian orientasi - lihat orientation_submissions / OrientationService::review())
 * - previous_orientation_id : rantai perpanjangan (app-level, tanpa FK DB karena self-reference)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_orientations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('company_id')->constrained('m_companies')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('duration_months');
            $table->string('source')->default('recruitment');
            $table->foreignUlid('source_submission_id')->nullable()->constrained('orientation_submissions')->nullOnDelete();
            $table->ulid('previous_orientation_id')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_orientations');
    }
};
