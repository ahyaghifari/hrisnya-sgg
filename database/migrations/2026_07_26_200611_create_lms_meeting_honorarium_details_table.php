<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Anak dari lms_meeting_honorarium_assessments — 1 baris per variabel yang dipilih
     * kategorinya (unique assessment_id+variable_id, cuma boleh 1 kategori per variabel per
     * pertemuan). `score` disalin dari lms_honorarium_categories.score saat dipilih.
     */
    public function up(): void
    {
        Schema::create('lms_meeting_honorarium_details', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('assessment_id')->constrained('lms_meeting_honorarium_assessments')->cascadeOnDelete();
            $table->foreignUlid('variable_id')->constrained('lms_honorarium_variables')->cascadeOnDelete();
            $table->foreignUlid('category_id')->constrained('lms_honorarium_categories')->cascadeOnDelete();
            $table->integer('score');
            $table->timestamps();
            $table->unique(['assessment_id', 'variable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_meeting_honorarium_details');
    }
};
