<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Peleburan persons -> employees (roadmap_migrasi_bertahap_identity_hris.md): tidak ada lagi entitas
 * Person; data pribadi jadi kolom employees, dan person_identities/addresses/documents menjadi
 * employee_identities/addresses/documents. Dokter juga baris employees (tipe karyawan tersendiri).
 *
 * TIDAK ADA data yang dipindahkan: semua isi persons* hanyalah SALINAN dari users + user_profiles
 * (+ user_profile_documents), jadi tabelnya dibuang lalu dibuat ulang dan diisi lagi oleh
 * `php artisan identity:backfill`. Jalankan backfill SETELAH migrate; selama jeda itu belum ada fitur
 * yang membaca data pribadi di employees. users.person_id dibuang: tautan akun <-> karyawan cukup
 * employees.user_id. Dua akun Super Admin tetap tanpa baris employees.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Urutan: lepas FK -> lepas indeks unik -> hapus kolom (MySQL menolak melepas indeks yang
        // masih dipakai FK; SQLite menolak menghapus kolom yang masih punya indeks).
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['person_id']);
            $table->dropUnique(['person_id']);
            $table->dropColumn('person_id');
        });

        Schema::dropIfExists('person_documents');
        Schema::dropIfExists('person_addresses');
        Schema::dropIfExists('person_identities');

        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['person_id']);
            $table->dropColumn('person_id');
        });

        Schema::dropIfExists('persons');

        Schema::table('employees', function (Blueprint $table) {
            // Data pribadi (salinan dari users.name + user_profiles). Nullable: baris yang ada belum
            // terisi sampai identity:backfill dijalankan.
            $table->string('nama_lengkap')->nullable()->after('nip');
            $table->string('nickname')->nullable();
            $table->string('gender')->nullable();
            $table->string('marital_status')->nullable();
            $table->string('place_of_birth')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->foreignUlid('religion_id')->nullable()->constrained('m_religions')->nullOnDelete();
            $table->json('profile_picture')->nullable();
            $table->string('phone')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('email_pribadi')->nullable();
        });

        // Identitas + rekening bank: sama-sama data sensitif milik karyawan, 1:1 untuk sekarang.
        // NIK belum unique sampai data dibersihkan (lihat roadmap, Fase 0).
        Schema::create('employee_identities', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('employee_id')->unique()->constrained('employees')->cascadeOnDelete();
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
        Schema::create('employee_addresses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('employee_id')->constrained('employees')->cascadeOnDelete();
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

            $table->unique(['employee_id', 'type']);
        });

        // Lampiran KTP/KK/STR/SIP/RKK: file_path SAMA dengan user_profile_documents (file terenkripsi
        // di disk privat tidak dipindah). `type` string biasa karena label lama dulu bebas ketik.
        Schema::create('employee_documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('type');
            $table->string('file_path');
            $table->timestamps();

            $table->unique(['employee_id', 'type']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Peleburan persons -> employees tidak bisa di-rollback; pulihkan dari backup lalu jalankan identity:backfill.');
    }
};
