<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Anak dari lms_honorarium_variables — tiap kategori punya skor sendiri (mis.
     * "< 10 Tahun" = 1, "10-19 Tahun" = 2), pengganti peran trainer_score_categories.
     */
    public function up(): void
    {
        Schema::create('lms_honorarium_categories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('variable_id')->constrained('lms_honorarium_variables')->cascadeOnDelete();
            $table->string('name');
            $table->integer('score');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_honorarium_categories');
    }
};
