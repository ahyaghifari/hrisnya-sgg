<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Variabel penilai honorarium (mis. "Lama Praktik", "Reputasi") — pengganti peran
     * trainer_score_variables, tapi khusus 2 konteks yang honornya dihitung skor
     * (App\Enum\LMS\TrainingCategoryEnum::scoredCases(): dokter_spesialis/eksternal).
     * Global (tanpa company_id) atas permintaan user — berlaku sama untuk semua perusahaan.
     */
    public function up(): void
    {
        Schema::create('lms_honorarium_variables', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('context');
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_honorarium_variables');
    }
};
