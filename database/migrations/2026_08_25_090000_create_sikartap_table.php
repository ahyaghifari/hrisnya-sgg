<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengajuan karyawan tetap (rewrite Sikartap, bukan port). Kolom status per-tahap
 * dipertahankan terpisah (bukan 1 kolom overall) karena tiap tahap dilihat/diisi role
 * berbeda (HRD verifikasi berkas, tim tes, pewawancara, tim MCU) dan bisa berjalan
 * sebagian paralel — tapi keputusan akhir HARUS dihitung dari satu method di model
 * Sikartap, bukan diduplikasi di tiap Livewire/Resource seperti get_keputusan_akhir()
 * di legacy (lihat knowledge/markdown/analisis-sikartap-migrasi.md § I.7).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sikartap', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('status_berkas')->default('pending');
            $table->boolean('berkas_lengkap')->default(false);
            $table->string('status_tes')->default('pending');
            $table->string('status_wawancara')->default('pending');
            $table->string('status_mcu')->default('pending');
            $table->string('status_keputusan_direktur')->default('pending');

            $table->boolean('is_diskualifikasi')->default(false);
            $table->text('alasan_diskualifikasi')->nullable();

            $table->timestamps();

            $table->index('status_berkas');
            $table->index('status_keputusan_direktur');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sikartap');
    }
};
