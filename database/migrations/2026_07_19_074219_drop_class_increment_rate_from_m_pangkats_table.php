<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Digantikan setting company-scoped `gaji_pokok_kenaikan_per_kelas` (halaman Komponen
     * Gaji) — kenaikan per kelas ternyata satu nilai per company, bukan per pangkat. Kolom ini
     * sudah tidak dibaca GajiPokokCalculator lagi, dihapus bukan cuma dibiarkan mati.
     */
    public function up(): void
    {
        Schema::table('m_pangkats', function (Blueprint $table) {
            $table->dropColumn('class_increment_rate');
        });
    }

    public function down(): void
    {
        Schema::table('m_pangkats', function (Blueprint $table) {
            $table->decimal('class_increment_rate', 5, 4)->nullable();
        });
    }
};
