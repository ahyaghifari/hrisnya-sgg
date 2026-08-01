<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ledger eksplisit riwayat kontrak per tahun/periode - beda dari kolom contract_start_date/
 * contract_end_date di user_profiles (yang cuma "kontrak AKTIF saat ini", dibaca cepat tanpa
 * JOIN oleh UserTransitionService::resolveContractEndDate() dkk). 1 baris = 1 periode kontrak
 * yang SUDAH disetujui (Pengangkatan Orientasi ke Kontrak, atau tiap Perpanjangan Kontrak) -
 * append-only, tidak pernah di-update, supaya "riwayat kontrak tahun ke berapa" bisa dikueri
 * langsung tanpa filter transition_type+status ke user_transitions setiap saat.
 *
 * source_transition_id nullable + nullOnDelete: link balik ke UserTransition yang men-generate
 * baris ini (buat audit/telusur alasan/dokumen pendukung), tapi baris ini tetap valid berdiri
 * sendiri kalau transition sumbernya somehow terhapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_contracts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('company_id')->constrained('m_companies')->cascadeOnDelete();
            $table->foreignUlid('employee_type_id')->constrained('m_employee_types')->cascadeOnDelete();
            $table->date('contract_start_date');
            $table->date('contract_end_date');
            $table->foreignUlid('source_transition_id')->nullable()->constrained('user_transitions')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'contract_start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_contracts');
    }
};
