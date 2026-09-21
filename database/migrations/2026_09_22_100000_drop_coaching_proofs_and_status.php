<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coaching disederhanakan jadi satu arah (instruksi user 2026-09-22): tidak ada bukti
 * pelaksanaan dan tidak ada status. down() cuma mengembalikan struktur, bukan datanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('coaching_proofs');

        Schema::table('coachings', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }

    public function down(): void
    {
        Schema::table('coachings', function (Blueprint $table) {
            $table->string('status')->default('completed');
        });

        Schema::create('coaching_proofs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('coaching_id')->constrained('coachings')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }
};
