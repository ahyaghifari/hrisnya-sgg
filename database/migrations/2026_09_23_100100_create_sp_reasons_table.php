<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master alasan SP (planning/2026-09-23-sp-dan-merit-point.md §2.2). Seed awal lewat
 * database/seeders/SpReasonSeeder.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sp_reasons', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sp_reasons');
    }
};
