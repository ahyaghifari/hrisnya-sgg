<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Kolom kelewat di migration awal - form_hasil_radiologi.php legacy punya 2 field terpisah:
 * foto_thorax (Normal/Abnormal, enum) dan file_path (upload aktual). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sikartap_mcu_radiology_results', function (Blueprint $table) {
            $table->string('foto_thorax')->nullable()->after('jenis_pemeriksaan');
        });
    }

    public function down(): void
    {
        Schema::table('sikartap_mcu_radiology_results', function (Blueprint $table) {
            $table->dropColumn('foto_thorax');
        });
    }
};
