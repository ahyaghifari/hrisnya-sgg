<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `m_pangkats.bpjs_basis` (Fase 0) ternyata salah tempat — kolom config payroll yang
     * numpang di tabel master. Dipindah ke tabel sendiri, 1:1 ke m_pangkats, pola sama
     * `pangkat_salaries` (Fase 0 §2.3). Kolom lama selalu NULL (belum pernah diisi lewat UI),
     * jadi drop aman tanpa migrasi data. Diisi lewat halaman Komponen Gaji (mapping BPJS per
     * pangkat), BUKAN form Pangkat biasa — lihat planning Fase 2.
     */
    public function up(): void
    {
        Schema::table('m_pangkats', function (Blueprint $table) {
            $table->dropColumn('bpjs_basis');
        });

        Schema::create('pangkat_bpjs_bases', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('pangkat_id')->constrained('m_pangkats')->cascadeOnDelete();
            $table->string('bpjs_basis')->default('ump'); // 'ump' | 'declared_value'
            $table->timestamps();
            $table->unique('pangkat_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pangkat_bpjs_bases');

        Schema::table('m_pangkats', function (Blueprint $table) {
            $table->string('bpjs_basis')->nullable()->after('class_increment_rate');
        });
    }
};
