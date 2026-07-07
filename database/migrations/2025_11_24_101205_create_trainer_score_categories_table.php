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
        Schema::create('trainer_score_categories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('variable_id')->constrained('trainer_score_variables')->onDelete('cascade');
            $table->string('name');
            $table->integer('score')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trainer_score_categories');
    }
};
