<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master aturan Merit Point (planning/2026-09-23-sp-dan-merit-point.md §3.2) - masih berstatus
 * DEMO. company_id WAJIB (tidak nullable, dikonfirmasi user: per company, bukan global).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merit_point_rules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('m_companies')->cascadeOnDelete();
            $table->string('type');
            $table->string('name');
            $table->integer('points');
            $table->string('trigger_type');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merit_point_rules');
    }
};
