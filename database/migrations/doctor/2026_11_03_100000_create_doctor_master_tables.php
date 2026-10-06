<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DB `doctor` (koneksi terpisah dari HRIS) - Fase 1: master dokter. Jalankan dengan
 * `php artisan migrate --database=doctor --path=database/migrations/doctor`.
 *
 * Referensi ke HRIS (hris_user_id, rumah_sakit_id) = ULID char(26) TANPA FK (lintas DB):
 * hris_user_id -> users.id (akun login dokter), rumah_sakit_id -> m_companies.id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('doctor')->create('doctors', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 150); // tanpa gelar
            $table->string('gelar_depan', 100)->nullable();
            $table->string('gelar_belakang', 100)->nullable();
            $table->string('nip', 50)->nullable()->index();
            $table->string('nik', 32)->nullable(); // sensitif: jangan tampil penuh di daftar
            $table->string('tempat_lahir', 100)->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->enum('jenis_kelamin', ['L', 'P'])->nullable();
            $table->string('agama', 50)->nullable();
            $table->char('hris_user_id', 26)->nullable()->unique(); // akun login HRIS
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif')->index();
            $table->timestamps();
            $table->softDeletes(); // riwayat lisensi/credentialing tidak boleh ikut hilang
            $table->index('nama');
        });

        Schema::connection('doctor')->create('specialties', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 150)->unique();
            $table->string('kode', 50)->nullable();
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif');
            $table->timestamps();
        });

        Schema::connection('doctor')->create('doctor_specialties', function (Blueprint $table) {
            $table->foreignId('doctor_id')->constrained('doctors')->cascadeOnDelete();
            $table->foreignId('specialty_id')->constrained('specialties')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->primary(['doctor_id', 'specialty_id']);
        });

        Schema::connection('doctor')->create('doctor_hospitals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained('doctors')->cascadeOnDelete();
            $table->char('rumah_sakit_id', 26)->index(); // m_companies.id (lintas DB, tanpa FK)
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('doctor')->dropIfExists('doctor_hospitals');
        Schema::connection('doctor')->dropIfExists('doctor_specialties');
        Schema::connection('doctor')->dropIfExists('specialties');
        Schema::connection('doctor')->dropIfExists('doctors');
    }
};
