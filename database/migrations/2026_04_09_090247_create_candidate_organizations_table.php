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
        Schema::create('candt_organizations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->string('organization_name')->nullable();
            $table->string('field')->nullable();
            $table->string('position')->nullable();
            $table->string('duration')->nullable();
            $table->string('location')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candt_organizations');
    }
};
