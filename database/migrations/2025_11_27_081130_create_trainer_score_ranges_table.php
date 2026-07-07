<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('trainer_score_ranges', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->enum('type', ['honor', 'validity']);
            $table->integer('min_score');
            $table->integer('max_score');
            $table->double('honor_amount')->nullable();
            $table->integer('validity_months')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trainer_score_ranges');
    }
};
