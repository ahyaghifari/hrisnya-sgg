<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Matriks Tunjangan Jabatan LAMA (pangkat x kelas -> nominal), dipakai IncentiveCalculator
     * metode "auto" buat hitung selisih (lama - baru, dikunci minimal 0) sebagai Insentif
     * penutup kekurangan transisi. Struktur identik position_allowances, cuma isi nominal lama.
     */
    public function up(): void
    {
        Schema::create('legacy_position_allowances', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('pangkat_id')->constrained('m_pangkats')->cascadeOnDelete();
            $table->foreignUlid('kelas_id')->constrained('m_kelas')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->timestamps();
            $table->unique(['pangkat_id', 'kelas_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_position_allowances');
    }
};
