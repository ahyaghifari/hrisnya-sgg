<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Matriks Tunjangan Jabatan (pangkat x kelas -> nominal). Dibuat KOSONG sengaja — lihat
     * catatan di 2026_07_16_150200_create_pangkat_salaries_table.php.
     */
    public function up(): void
    {
        Schema::create('position_allowances', function (Blueprint $table) {
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
        Schema::dropIfExists('position_allowances');
    }
};
