<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Perpanjangan Kontrak (self-service, lepas Approval Engine — pola Orientasi).
 * Lihat planning/2026-09-06-modul-perpanjangan-kontrak.md.
 *
 * Hierarki: user_contracts (induk) -< contract_submissions -< contract_submission_documents.
 * `new_contract_id` = pointer maju ke baris user_contracts hasil perpanjangan (bukan FK induk).
 *
 * Daftar dokumen yang diminta = Setting `contract_extension_documents` per company
 * ([{label, required}], pola orientation_criteria) — bukan tabel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_submissions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->ulid('user_contract_id')->nullable();
            $table->foreign('user_contract_id', 'cs_user_contract_fk')
                ->references('id')->on('user_contracts')->nullOnDelete();
            $table->foreignUlid('company_id')->constrained('m_companies')->cascadeOnDelete();
            $table->string('status')->default('draft');
            $table->unsignedSmallInteger('usulan_durasi_bulan')->nullable();
            $table->text('alasan')->nullable();
            $table->string('decision')->nullable();
            $table->unsignedSmallInteger('decision_durasi_bulan')->nullable();
            $table->text('decision_note')->nullable();
            $table->foreignUlid('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->ulid('new_contract_id')->nullable();
            $table->foreign('new_contract_id', 'cs_new_contract_fk')
                ->references('id')->on('user_contracts')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('contract_submission_documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('contract_submission_id');
            $table->foreign('contract_submission_id', 'csd_submission_fk')
                ->references('id')->on('contract_submissions')->cascadeOnDelete();
            $table->string('label');
            $table->string('path');
            $table->string('original_name');
            $table->timestamps();

            $table->index('contract_submission_id', 'csd_submission_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_submission_documents');
        Schema::dropIfExists('contract_submissions');
    }
};
