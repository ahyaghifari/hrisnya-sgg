<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rentang skor -> nominal honorarium, khusus 2 konteks yang honornya DIHITUNG (bukan
     * fixed): Dokter Spesialis & Biaya Pelatihan Eksternal. `context` = nilai
     * App\Enum\LMS\TrainingCategoryEnum::scoredCases() (dokter_spesialis/eksternal) — memisahkan
     * kolam skor 2 konteks itu supaya rentang skornya tidak tertukar. Global (tanpa company_id)
     * atas permintaan user — berlaku sama untuk semua perusahaan.
     */
    public function up(): void
    {
        Schema::create('lms_honorarium', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('context');
            $table->integer('min_score');
            $table->integer('max_score');
            $table->double('honor_amount');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_honorarium');
    }
};
