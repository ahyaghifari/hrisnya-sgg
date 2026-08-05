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
        Schema::create('training_request_honorarium_details', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('training_request_id')->constrained('training_requests')->cascadeOnDelete();
            $table->foreignUlid('variable_id')->constrained('lms_honorarium_variables')->cascadeOnDelete();
            $table->foreignUlid('category_id')->constrained('lms_honorarium_categories')->cascadeOnDelete();
            $table->integer('score');
            $table->timestamps();
            $table->unique(['training_request_id', 'variable_id'], 'training_req_honor_details_req_var_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('training_request_honorarium_details');
    }
};
