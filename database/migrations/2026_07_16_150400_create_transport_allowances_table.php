<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tunjangan Transportasi (kelas -> nominal). Dibuat KOSONG sengaja — lihat catatan di
     * 2026_07_16_150200_create_pangkat_salaries_table.php.
     */
    public function up(): void
    {
        Schema::create('transport_allowances', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('kelas_id')->constrained('m_kelas')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->timestamps();
            $table->unique('kelas_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_allowances');
    }
};
