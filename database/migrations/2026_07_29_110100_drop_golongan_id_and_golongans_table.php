<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Golongan bukan lagi entitas/FK sendiri (lihat migration pasangan
 * 2026_07_29_110000_add_golongan_to_pangkats_table.php yang menambah kolom teks `golongan`
 * di m_pangkats) - data golongan_id lama di m_job_positions/user_profiles TIDAK dimigrasikan
 * (tidak ada pemetaan 1:1 yang aman, golongan_id sebelumnya dipilih bebas lepas dari
 * pangkat_id) - keputusan eksplisit user, admin isi ulang golongan lewat form Pangkat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_job_positions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('golongan_id');
        });

        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('golongan_id');
        });

        Schema::dropIfExists('m_golongans');
    }

    public function down(): void
    {
        Schema::create('m_golongans', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->foreignUlid('company_id')->nullable()->constrained('m_companies')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('m_job_positions', function (Blueprint $table) {
            $table->foreignUlid('golongan_id')->nullable()->after('pangkat_id')->constrained('m_golongans')->nullOnDelete();
        });

        Schema::table('user_profiles', function (Blueprint $table) {
            $table->ulid('golongan_id')->nullable();
            $table->foreign('golongan_id')->references('id')->on('m_golongans')->nullOnDelete();
        });
    }
};
