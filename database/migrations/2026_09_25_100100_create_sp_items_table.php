<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Poin-poin pelanggaran dalam 1 surat SP (N per SP, instruksi user 2026-09-25). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sp_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('sp_id')->constrained('employee_sps')->cascadeOnDelete();
            $table->text('text');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('sp_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sp_items');
    }
};
