<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DB `persuratan` (koneksi terpisah dari HRIS). `perusahaan` = penerbit surat; TIDAK harus 1:1
 * dengan m_companies — RSUSM & PT Syifa Medika Persada satu badan hukum tapi kode/counter
 * sendiri, dan ada penerbit di luar HRIS. `hris_company_id` nullable = jembatan opsional
 * (ULID m_companies, tanpa FK karena lintas DB).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('persuratan')->create('perusahaan', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 20)->unique();
            $table->string('nama');
            $table->char('hris_company_id', 26)->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('persuratan')->dropIfExists('perusahaan');
    }
};
