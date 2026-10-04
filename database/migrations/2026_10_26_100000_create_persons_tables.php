<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fondasi identitas orang (roadmap_migrasi_bertahap_identity_hris.md). Tiga tabel ini SALINAN
 * dari users + user_profiles - tabel lama tetap sumber & tetap diedit seperti biasa, penyalinnya
 * App\Services\Identity\PersonSyncService (dipicu observer model + command identity:backfill).
 * Tidak ada unique di NIK sampai data dibersihkan (lihat roadmap, Fase 0).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('persons', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->nullable()->unique()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->string('nama_lengkap');
            $table->string('nickname')->nullable();
            $table->string('gender')->nullable();
            $table->string('marital_status')->nullable();
            $table->string('place_of_birth')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->foreignUlid('religion_id')->nullable()->constrained('m_religions')->nullOnDelete();
            $table->json('profile_picture')->nullable();
            // Kontak dilipat di sini dulu (1:1); jadi person_contacts kalau butuh 1:N (kontak darurat).
            $table->string('phone')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('email_pribadi')->nullable();
            $table->timestamps();
        });

        // Identitas + rekening bank: sama-sama data sensitif milik orang, 1:1 untuk sekarang.
        Schema::create('person_identities', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('person_id')->unique()->constrained('persons')->cascadeOnDelete();
            $table->string('no_nik')->nullable();
            $table->string('no_kk')->nullable();
            $table->string('npwp')->nullable();
            $table->string('no_bpjs_kes')->nullable();
            $table->string('no_bpjs_ket')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->timestamps();
        });

        // 1:N dengan `type`; sementara semua disalin sebagai 'utama' karena form HRIS belum
        // membedakan alamat KTP dan domisili.
        Schema::create('person_addresses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('person_id')->constrained('persons')->cascadeOnDelete();
            $table->string('type')->default('utama');
            $table->boolean('is_primary')->default(true);
            $table->string('country')->nullable();
            $table->string('province')->nullable();
            $table->string('city')->nullable();
            $table->string('district')->nullable();
            $table->string('village')->nullable();
            $table->text('address')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('rt')->nullable();
            $table->string('rw')->nullable();
            $table->timestamps();

            $table->unique(['person_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_addresses');
        Schema::dropIfExists('person_identities');
        Schema::dropIfExists('persons');
    }
};
