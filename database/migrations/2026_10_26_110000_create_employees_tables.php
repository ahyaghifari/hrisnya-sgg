<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Karyawan & riwayat karirnya (roadmap_migrasi_bertahap_identity_hris.md). Seperti persons: ini
 * SALINAN dari users + user_profiles + user_job_positions, tabel lama tetap sumber, diisi oleh
 * App\Services\Identity\EmployeeSyncService (observer + identity:backfill). Belum dibaca fitur apa pun.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Kondisi SAAT INI. Jabatan/pangkat/kelas boleh null (PHK mengosongkan ketiganya).
        Schema::create('employees', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('person_id')->constrained('persons')->cascadeOnDelete();
            $table->foreignUlid('user_id')->unique()->constrained('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('nip')->index(); // salinan users.nip; users.nip tetap sumber
            $table->foreignUlid('company_id')->nullable()->constrained('m_companies')->nullOnDelete();
            $table->foreignUlid('employee_type_id')->nullable()->constrained('m_employee_types')->nullOnDelete();
            $table->foreignUlid('job_position_id')->nullable()->constrained('m_job_positions')->nullOnDelete();
            $table->foreignUlid('pangkat_id')->nullable()->constrained('m_pangkats')->nullOnDelete();
            $table->foreignUlid('kelas_id')->nullable()->constrained('m_kelas')->nullOnDelete();
            $table->date('joining_date')->nullable();
            $table->date('tmt_tetap')->nullable();
            $table->string('status')->default('aktif'); // aktif | nonaktif (is_active + soft delete)
            $table->timestamps();
        });

        // RIWAYAT perubahan jabatan (+ snapshot pangkat/kelas), salinan user_job_positions.
        Schema::create('employee_careers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('employee_id')->constrained('employees')->cascadeOnDelete();
            // Kunci sinkron: id baris user_job_positions asalnya (tanpa FK, ledger bisa dihapus massal).
            $table->ulid('user_job_position_id')->nullable()->unique();
            $table->foreignUlid('job_position_id')->nullable()->constrained('m_job_positions')->nullOnDelete();
            // Snapshot pangkat/kelas: baris riwayat lama sengaja NULL (ledger lama tidak mencatatnya,
            // jangan menebak dari jabatan sekarang); hanya baris aktif yang diisi.
            $table->foreignUlid('pangkat_id')->nullable()->constrained('m_pangkats')->nullOnDelete();
            $table->foreignUlid('kelas_id')->nullable()->constrained('m_kelas')->nullOnDelete();
            $table->foreignUlid('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status')->nullable();
            $table->string('source')->default('user_job_positions');
            $table->timestamps();

            $table->index(['employee_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_careers');
        Schema::dropIfExists('employees');
    }
};
